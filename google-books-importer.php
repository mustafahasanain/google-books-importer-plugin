<?php
/**
 * Plugin Name: Google Books Importer for WooCommerce
 * Plugin URI: https://github.com/mustafahasanain/google-books-importer-plugin.git
 * Description: Import books from Google Books API into WooCommerce with CSV bulk import and manual search features.
 * Version: 1.0.0
 * Author: Mustafa Hasanain
 * Author URI: https://mustafahasanain.com
 * Text Domain: google-books-importer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('GBI_VERSION', '1.0.0');
define('GBI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GBI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GBI_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function gbi_check_woocommerce() {
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        deactivate_plugins(GBI_PLUGIN_BASENAME);
        wp_die(
            __('Google Books Importer requires WooCommerce to be installed and active.', 'google-books-importer'),
            __('Plugin Activation Error', 'google-books-importer'),
            array('back_link' => true)
        );
    }
}
register_activation_hook(__FILE__, 'gbi_check_woocommerce');

/**
 * Create Books category on activation
 */
function gbi_activate() {
    gbi_check_woocommerce();

    // Create default Books category if it doesn't exist
    if (!term_exists('Books', 'product_cat')) {
        wp_insert_term(
            'Books',
            'product_cat',
            array(
                'description' => __('Books imported from Google Books API', 'google-books-importer'),
                'slug'        => 'books'
            )
        );
    }

    // Set default options
    if (!get_option('gbi_settings')) {
        add_option('gbi_settings', array(
            'api_key'           => '',
            'image_width'       => 400,
            'image_height'      => 600,
            'duplicate_action'  => 'skip',
            'default_category'  => 'books'
        ));
    }
}
register_activation_hook(__FILE__, 'gbi_activate');

/**
 * Load plugin text domain for translations
 */
function gbi_load_textdomain() {
    load_plugin_textdomain('google-books-importer', false, dirname(GBI_PLUGIN_BASENAME) . '/languages');
}
add_action('plugins_loaded', 'gbi_load_textdomain');

/**
 * Include required files
 */
require_once GBI_PLUGIN_DIR . 'includes/class-google-books-api.php';
require_once GBI_PLUGIN_DIR . 'includes/class-product-creator.php';
require_once GBI_PLUGIN_DIR . 'includes/class-csv-parser.php';
require_once GBI_PLUGIN_DIR . 'includes/class-image-handler.php';
require_once GBI_PLUGIN_DIR . 'admin/class-admin-pages.php';

/**
 * Initialize the plugin
 */
function gbi_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'gbi_woocommerce_missing_notice');
        return;
    }

    // Initialize admin pages
    if (is_admin()) {
        new GBI_Admin_Pages();
    }
}
add_action('plugins_loaded', 'gbi_init');

/**
 * Admin notice if WooCommerce is not active
 */
function gbi_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            echo wp_kses_post(
                sprintf(
                    __('<strong>Google Books Importer</strong> requires <strong>WooCommerce</strong> to be installed and active. Please <a href="%s">install WooCommerce</a> first.', 'google-books-importer'),
                    admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')
                )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Add settings link on plugin page
 */
function gbi_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=gbi-settings') . '">' . __('Settings', 'google-books-importer') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . GBI_PLUGIN_BASENAME, 'gbi_settings_link');

/**
 * AJAX handler for CSV import
 */
add_action('wp_ajax_gbi_import_csv', 'gbi_ajax_import_csv');
function gbi_ajax_import_csv() {
    check_ajax_referer('gbi_import_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('Permission denied', 'google-books-importer')));
    }

    $csv_data = isset($_POST['csv_data']) ? sanitize_textarea_field($_POST['csv_data']) : '';

    if (empty($csv_data)) {
        wp_send_json_error(array('message' => __('No data provided', 'google-books-importer')));
    }

    $parser = new GBI_CSV_Parser();
    $books = $parser->parse($csv_data);

    if (empty($books)) {
        wp_send_json_error(array('message' => __('No valid books found in CSV data', 'google-books-importer')));
    }

    $api = new GBI_Google_Books_API();
    $creator = new GBI_Product_Creator();
    $results = array();

    foreach ($books as $book) {
        $book_data = $api->search_book($book['title']);

        if ($book_data) {
            $product_data = array_merge($book_data, array(
                'price'    => $book['price'],
                'quantity' => $book['quantity'],
                'category' => $book['category']
            ));

            $result = $creator->create_product($product_data);
            $results[] = $result;
        } else {
            $results[] = array(
                'success' => false,
                'title'   => $book['title'],
                'message' => __('Book not found in Google Books API', 'google-books-importer')
            );
        }
    }

    wp_send_json_success(array('results' => $results));
}

/**
 * AJAX handler for book search
 */
add_action('wp_ajax_gbi_search_books', 'gbi_ajax_search_books');
function gbi_ajax_search_books() {
    check_ajax_referer('gbi_search_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('Permission denied', 'google-books-importer')));
    }

    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : '';

    if (empty($query)) {
        wp_send_json_error(array('message' => __('Search query is required', 'google-books-importer')));
    }

    $api = new GBI_Google_Books_API();
    $results = $api->search($query, $filter);

    wp_send_json_success(array('books' => $results));
}

/**
 * AJAX handler for importing selected books
 */
add_action('wp_ajax_gbi_import_selected', 'gbi_ajax_import_selected');
function gbi_ajax_import_selected() {
    check_ajax_referer('gbi_import_selected_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('Permission denied', 'google-books-importer')));
    }

    $books = isset($_POST['books']) ? $_POST['books'] : array();

    if (empty($books)) {
        wp_send_json_error(array('message' => __('No books selected', 'google-books-importer')));
    }

    $creator = new GBI_Product_Creator();
    $results = array();

    foreach ($books as $book) {
        $result = $creator->create_product($book);
        $results[] = $result;
    }

    wp_send_json_success(array('results' => $results));
}
