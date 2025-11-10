<?php
/**
 * Product Creator
 *
 * Creates and updates WooCommerce products from book data
 *
 * @package Google_Books_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class GBI_Product_Creator {

    /**
     * Settings
     *
     * @var array
     */
    private $settings;

    /**
     * Image handler instance
     *
     * @var GBI_Image_Handler
     */
    private $image_handler;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('gbi_settings', array(
            'duplicate_action'  => 'skip',
            'default_category'  => 'books'
        ));

        $this->image_handler = new GBI_Image_Handler();
    }

    /**
     * Create or update a WooCommerce product from book data
     *
     * @param array $book_data Book data from API with price and quantity
     * @return array Result with success status and message
     */
    public function create_product($book_data) {
        // Validate required fields
        if (empty($book_data['title'])) {
            return array(
                'success' => false,
                'message' => __('Book title is required', 'google-books-importer')
            );
        }

        // Check for duplicates
        $existing_product_id = $this->find_existing_product($book_data);

        if ($existing_product_id) {
            $duplicate_action = $this->settings['duplicate_action'];

            if ($duplicate_action === 'skip') {
                return array(
                    'success' => false,
                    'title'   => $book_data['title'],
                    'message' => __('Book already exists (skipped)', 'google-books-importer'),
                    'product_id' => $existing_product_id
                );
            } elseif ($duplicate_action === 'update') {
                return $this->update_product($existing_product_id, $book_data);
            }
        }

        // Create new product
        return $this->create_new_product($book_data);
    }

    /**
     * Create a new WooCommerce product
     *
     * @param array $book_data Book data
     * @return array Result array
     */
    private function create_new_product($book_data) {
        // Create product
        $product = new WC_Product_Simple();

        // Set basic product data
        $product->set_name($book_data['title']);
        $product->set_status('publish');

        // Set description
        if (!empty($book_data['description'])) {
            $product->set_description($book_data['description']);
            $product->set_short_description($this->generate_short_description($book_data['description']));
        }

        // Set price
        if (isset($book_data['price'])) {
            $price = floatval($book_data['price']);
            $product->set_regular_price($price);
            $product->set_price($price);
        }

        // Set stock
        $product->set_manage_stock(true);
        $quantity = isset($book_data['quantity']) ? intval($book_data['quantity']) : 1;
        $product->set_stock_quantity($quantity);
        $product->set_stock_status($quantity > 0 ? 'instock' : 'outofstock');

        // Set SKU from ISBN
        // if (!empty($book_data['isbn'])) {
        //     $sku = 'BOOK-' . $book_data['isbn'];
        //     $product->set_sku($sku);
        // }

        // Save product to get ID
        $product_id = $product->save();

        if (!$product_id) {
            return array(
                'success' => false,
                'title'   => $book_data['title'],
                'message' => __('Failed to create product', 'google-books-importer')
            );
        }

        // Add to category
        $category_name = isset($book_data['category']) ? $book_data['category'] : '';
        $this->assign_category($product_id, $category_name);

        // Add custom fields (meta data)
        $this->update_custom_fields($product_id, $book_data);

        // Handle product image
        if (!empty($book_data['image_url'])) {
            $image_id = $this->image_handler->download_and_attach($book_data['image_url'], $book_data['title']);
            if ($image_id) {
                set_post_thumbnail($product_id, $image_id);
            }
        } else {
            // Use placeholder
            $placeholder_id = $this->image_handler->get_placeholder_image_id();
            if ($placeholder_id) {
                set_post_thumbnail($product_id, $placeholder_id);
            }
        }

        return array(
            'success'    => true,
            'title'      => $book_data['title'],
            'message'    => __('Product created successfully', 'google-books-importer'),
            'product_id' => $product_id,
            'product_url' => get_edit_post_link($product_id, 'raw')
        );
    }

    /**
     * Update an existing WooCommerce product
     *
     * @param int   $product_id Existing product ID
     * @param array $book_data  Book data
     * @return array Result array
     */
    private function update_product($product_id, $book_data) {
        $product = wc_get_product($product_id);

        if (!$product) {
            return array(
                'success' => false,
                'title'   => $book_data['title'],
                'message' => __('Product not found', 'google-books-importer')
            );
        }

        // Update description if provided
        if (!empty($book_data['description'])) {
            $product->set_description($book_data['description']);
            $product->set_short_description($this->generate_short_description($book_data['description']));
        }

        // Update price if provided
        if (isset($book_data['price'])) {
            $price = floatval($book_data['price']);
            $product->set_regular_price($price);
            $product->set_price($price);
        }

        // Update stock if provided
        if (isset($book_data['quantity'])) {
            $quantity = intval($book_data['quantity']);
            $product->set_stock_quantity($quantity);
            $product->set_stock_status($quantity > 0 ? 'instock' : 'outofstock');
        }

        $product->save();

        // Update category if provided
        if (isset($book_data['category'])) {
            $this->assign_category($product_id, $book_data['category']);
        }

        // Update custom fields
        $this->update_custom_fields($product_id, $book_data);

        // Update image if provided
        if (!empty($book_data['image_url'])) {
            $image_id = $this->image_handler->download_and_attach($book_data['image_url'], $book_data['title']);
            if ($image_id) {
                set_post_thumbnail($product_id, $image_id);
            }
        }

        return array(
            'success'    => true,
            'title'      => $book_data['title'],
            'message'    => __('Product updated successfully', 'google-books-importer'),
            'product_id' => $product_id,
            'product_url' => get_edit_post_link($product_id, 'raw')
        );
    }

    /**
     * Find existing product by ISBN or title
     *
     * @param array $book_data Book data
     * @return int|false Product ID or false if not found
     */
    private function find_existing_product($book_data) {
        // Try to find by ISBN first (most reliable)
        // if (!empty($book_data['isbn'])) {
        //     $sku = 'BOOK-' . $book_data['isbn'];
        //     $product_id = wc_get_product_id_by_sku($sku);
        //     if ($product_id) {
        //         return $product_id;
        //     }

        //     // Also check by meta field
        //     $args = array(
        //         'post_type'      => 'product',
        //         'posts_per_page' => 1,
        //         'post_status'    => 'any',
        //         'meta_query'     => array(
        //             array(
        //                 'key'   => '_gbi_isbn',
        //                 'value' => $book_data['isbn']
        //             )
        //         )
        //     );

        //     $products = get_posts($args);
        //     if (!empty($products)) {
        //         return $products[0]->ID;
        //     }
        // }

        // Try to find by exact title match
        if (!empty($book_data['title'])) {
            $args = array(
                'post_type'      => 'product',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'title'          => $book_data['title']
            );

            $products = get_posts($args);
            if (!empty($products)) {
                return $products[0]->ID;
            }
        }

        return false;
    }

    /**
     * Update custom fields for a product
     *
     * @param int   $product_id Product ID
     * @param array $book_data  Book data
     */
    private function update_custom_fields($product_id, $book_data) {
        $meta_fields = array(
            '_gbi_google_id'      => 'google_id',
            '_gbi_isbn'           => 'isbn',
            '_gbi_isbn_10'        => 'isbn_10',
            '_gbi_isbn_13'        => 'isbn_13',
            '_gbi_authors'        => 'authors',
            '_gbi_publisher'      => 'publisher',
            '_gbi_published_date' => 'published_date',
            '_gbi_page_count'     => 'page_count',
            '_gbi_categories'     => 'categories',
            '_gbi_language'       => 'language',
            '_gbi_preview_link'   => 'preview_link',
            '_gbi_info_link'      => 'info_link',
        );

        foreach ($meta_fields as $meta_key => $data_key) {
            if (isset($book_data[$data_key]) && !empty($book_data[$data_key])) {
                update_post_meta($product_id, $meta_key, sanitize_text_field($book_data[$data_key]));
            }
        }

        // Add import timestamp
        update_post_meta($product_id, '_gbi_imported_at', current_time('mysql'));
    }

    /**
     * Assign product to category (creates category if it doesn't exist)
     *
     * @param int    $product_id    Product ID
     * @param string $category_name Category name (supports Arabic)
     */
    private function assign_category($product_id, $category_name = '') {
        // Use provided category name or default to 'Uncategorized'
        if (empty($category_name)) {
            $category_name = 'Uncategorized';
        }

        // Try to get category by name first
        $category = term_exists($category_name, 'product_cat');

        if (!$category) {
            // Category doesn't exist, create it
            $category = wp_insert_term(
                $category_name,
                'product_cat',
                array(
                    'slug' => sanitize_title($category_name)
                )
            );

            // Check if creation was successful
            if (is_wp_error($category)) {
                // If error, try to get by slug as fallback
                $category = get_term_by('slug', sanitize_title($category_name), 'product_cat');
                if ($category) {
                    $category_id = $category->term_id;
                } else {
                    // If still fails, return without assigning
                    return;
                }
            } else {
                $category_id = $category['term_id'];
            }
        } else {
            $category_id = is_array($category) ? $category['term_id'] : $category;
        }

        // Assign product to category
        if ($category_id) {
            wp_set_object_terms($product_id, intval($category_id), 'product_cat');
        }
    }

    /**
     * Generate short description from full description
     *
     * @param string $description Full description
     * @return string Short description
     */
    private function generate_short_description($description) {
        // Remove HTML tags
        $text = wp_strip_all_tags($description);

        // Limit to 160 characters
        if (strlen($text) > 160) {
            $text = substr($text, 0, 157) . '...';
        }

        return $text;
    }

    /**
     * Bulk create products
     *
     * @param array $books Array of book data
     * @return array Array of results
     */
    public function bulk_create($books) {
        $results = array();

        foreach ($books as $book) {
            $result = $this->create_product($book);
            $results[] = $result;
        }

        return $results;
    }
}
