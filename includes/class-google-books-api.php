<?php
/**
 * Google Books API Handler
 *
 * Handles all interactions with the Google Books API v1
 *
 * @package Google_Books_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class GBI_Google_Books_API {

    /**
     * API base URL
     *
     * @var string
     */
    private $api_base_url = 'https://www.googleapis.com/books/v1';

    /**
     * API key from settings
     *
     * @var string
     */
    private $api_key;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('gbi_settings', array());
        $this->api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
    }

    /**
     * Search for a single book by title
     *
     * @param string $title Book title to search for
     * @return array|false Book data or false if not found
     */
    public function search_book($title) {
        if (empty($this->api_key)) {
            return false;
        }

        $title = sanitize_text_field($title);
        $url = add_query_arg(array(
            'q'      => urlencode($title),
            'key'    => $this->api_key,
            'maxResults' => 1
        ), $this->api_base_url . '/volumes');

        $response = wp_remote_get($url, array('timeout' => 30));

        if (is_wp_error($response)) {
            error_log('Google Books API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['items'][0])) {
            return false;
        }

        return $this->format_book_data($data['items'][0]);
    }

    /**
     * Search for books by query with filters
     *
     * @param string $query   Search query
     * @param string $filter  Filter type (author, category, or empty)
     * @param int    $max     Maximum results (default 40)
     * @return array Array of book data
     */
    public function search($query, $filter = '', $max = 40) {
        if (empty($this->api_key)) {
            return array();
        }

        $query = sanitize_text_field($query);
        $search_query = $query;

        // Add filter prefix if specified
        if (!empty($filter) && in_array($filter, array('author', 'subject'))) {
            $search_query = 'in' . $filter . ':' . $query;
        }

        $url = add_query_arg(array(
            'q'          => urlencode($search_query),
            'key'        => $this->api_key,
            'maxResults' => min($max, 40), // API max is 40
            'orderBy'    => 'relevance'
        ), $this->api_base_url . '/volumes');

        $response = wp_remote_get($url, array('timeout' => 30));

        if (is_wp_error($response)) {
            error_log('Google Books API Error: ' . $response->get_error_message());
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['items'])) {
            return array();
        }

        $books = array();
        foreach ($data['items'] as $item) {
            $books[] = $this->format_book_data($item);
        }

        return $books;
    }

    /**
     * Get book details by ISBN
     *
     * @param string $isbn ISBN number
     * @return array|false Book data or false if not found
     */
    public function get_by_isbn($isbn) {
        if (empty($this->api_key)) {
            return false;
        }

        $isbn = sanitize_text_field($isbn);
        $url = add_query_arg(array(
            'q'   => 'isbn:' . urlencode($isbn),
            'key' => $this->api_key
        ), $this->api_base_url . '/volumes');

        $response = wp_remote_get($url, array('timeout' => 30));

        if (is_wp_error($response)) {
            error_log('Google Books API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['items'][0])) {
            return false;
        }

        return $this->format_book_data($data['items'][0]);
    }

    /**
     * Test if API key is valid
     *
     * @param string $api_key Optional API key to test (uses stored key if empty)
     * @return bool True if valid, false otherwise
     */
    public function test_api_key($api_key = '') {
        $key = !empty($api_key) ? $api_key : $this->api_key;

        if (empty($key)) {
            return false;
        }

        $url = add_query_arg(array(
            'q'   => 'test',
            'key' => $key,
            'maxResults' => 1
        ), $this->api_base_url . '/volumes');

        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code === 200;
    }

    /**
     * Format book data from API response
     *
     * @param array $item Raw API item data
     * @return array Formatted book data
     */
    private function format_book_data($item) {
        $volume_info = isset($item['volumeInfo']) ? $item['volumeInfo'] : array();
        $sale_info = isset($item['saleInfo']) ? $item['saleInfo'] : array();

        // Extract ISBNs
        $isbn_10 = '';
        $isbn_13 = '';
        if (isset($volume_info['industryIdentifiers'])) {
            foreach ($volume_info['industryIdentifiers'] as $identifier) {
                if ($identifier['type'] === 'ISBN_10') {
                    $isbn_10 = $identifier['identifier'];
                } elseif ($identifier['type'] === 'ISBN_13') {
                    $isbn_13 = $identifier['identifier'];
                }
            }
        }

        // Get the best available ISBN
        $isbn = !empty($isbn_13) ? $isbn_13 : $isbn_10;

        // Extract authors
        $authors = isset($volume_info['authors']) ? implode(', ', $volume_info['authors']) : '';

        // Extract categories
        $categories = isset($volume_info['categories']) ? implode(', ', $volume_info['categories']) : '';

        // Get the best quality image
        $image_url = '';
        if (isset($volume_info['imageLinks'])) {
            if (isset($volume_info['imageLinks']['large'])) {
                $image_url = $volume_info['imageLinks']['large'];
            } elseif (isset($volume_info['imageLinks']['medium'])) {
                $image_url = $volume_info['imageLinks']['medium'];
            } elseif (isset($volume_info['imageLinks']['thumbnail'])) {
                $image_url = $volume_info['imageLinks']['thumbnail'];
            } elseif (isset($volume_info['imageLinks']['smallThumbnail'])) {
                $image_url = $volume_info['imageLinks']['smallThumbnail'];
            }
        }

        // Replace http with https for images
        if (!empty($image_url)) {
            $image_url = str_replace('http://', 'https://', $image_url);
        }

        // Get description
        $description = isset($volume_info['description']) ? $volume_info['description'] : '';

        // Get list price from API (if available)
        $list_price = '';
        if (isset($sale_info['listPrice']['amount'])) {
            $list_price = $sale_info['listPrice']['amount'];
        }

        return array(
            'google_id'      => isset($item['id']) ? $item['id'] : '',
            'title'          => isset($volume_info['title']) ? $volume_info['title'] : '',
            'subtitle'       => isset($volume_info['subtitle']) ? $volume_info['subtitle'] : '',
            'authors'        => $authors,
            'publisher'      => isset($volume_info['publisher']) ? $volume_info['publisher'] : '',
            'published_date' => isset($volume_info['publishedDate']) ? $volume_info['publishedDate'] : '',
            'description'    => $description,
            'isbn'           => $isbn,
            'isbn_10'        => $isbn_10,
            'isbn_13'        => $isbn_13,
            'page_count'     => isset($volume_info['pageCount']) ? $volume_info['pageCount'] : 0,
            'categories'     => $categories,
            'language'       => isset($volume_info['language']) ? $volume_info['language'] : '',
            'image_url'      => $image_url,
            'list_price'     => $list_price,
            'preview_link'   => isset($volume_info['previewLink']) ? $volume_info['previewLink'] : '',
            'info_link'      => isset($volume_info['infoLink']) ? $volume_info['infoLink'] : '',
        );
    }
}
