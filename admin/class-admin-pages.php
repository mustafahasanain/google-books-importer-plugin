<?php
/**
 * Admin Pages
 *
 * Manages WordPress admin menu and pages
 *
 * @package Google_Books_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class GBI_Admin_Pages {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers
        add_action('wp_ajax_gbi_test_api_key', array($this, 'ajax_test_api_key'));
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu page - CSV Import
        add_menu_page(
            __('Google Books Importer', 'google-books-importer'),
            __('Books Importer', 'google-books-importer'),
            'manage_woocommerce',
            'gbi-csv-import',
            array($this, 'render_csv_import_page'),
            'dashicons-book-alt',
            56
        );

        // CSV Import submenu (same as parent)
        add_submenu_page(
            'gbi-csv-import',
            __('CSV Import', 'google-books-importer'),
            __('CSV Import', 'google-books-importer'),
            'manage_woocommerce',
            'gbi-csv-import',
            array($this, 'render_csv_import_page')
        );

        // Search Import submenu
        add_submenu_page(
            'gbi-csv-import',
            __('Search & Import', 'google-books-importer'),
            __('Search & Import', 'google-books-importer'),
            'manage_woocommerce',
            'gbi-search-import',
            array($this, 'render_search_import_page')
        );

        // Settings submenu
        add_submenu_page(
            'gbi-csv-import',
            __('Settings', 'google-books-importer'),
            __('Settings', 'google-books-importer'),
            'manage_woocommerce',
            'gbi-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'gbi_settings_group',
            'gbi_settings',
            array($this, 'sanitize_settings')
        );
    }

    /**
     * Sanitize settings before saving
     *
     * @param array $input Raw input data
     * @return array Sanitized data
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        $sanitized['image_width'] = isset($input['image_width']) ? absint($input['image_width']) : 400;
        $sanitized['image_height'] = isset($input['image_height']) ? absint($input['image_height']) : 600;
        $sanitized['duplicate_action'] = isset($input['duplicate_action']) && in_array($input['duplicate_action'], array('skip', 'update')) ? $input['duplicate_action'] : 'skip';
        $sanitized['default_category'] = isset($input['default_category']) ? sanitize_text_field($input['default_category']) : 'books';
        $sanitized['placeholder_image_id'] = isset($input['placeholder_image_id']) ? absint($input['placeholder_image_id']) : 0;

        return $sanitized;
    }

    /**
     * Enqueue admin CSS and JavaScript
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'gbi-') === false && strpos($hook, 'books-importer') === false) {
            return;
        }

        // Enqueue WordPress media uploader
        wp_enqueue_media();

        // Enqueue our CSS
        wp_enqueue_style(
            'gbi-admin-style',
            GBI_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            GBI_VERSION
        );

        // Enqueue our JavaScript
        wp_enqueue_script(
            'gbi-admin-script',
            GBI_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery'),
            GBI_VERSION,
            true
        );

        // Localize script with AJAX URL and nonces
        wp_localize_script('gbi-admin-script', 'gbiAdmin', array(
            'ajaxUrl'               => admin_url('admin-ajax.php'),
            'importNonce'           => wp_create_nonce('gbi_import_nonce'),
            'searchNonce'           => wp_create_nonce('gbi_search_nonce'),
            'importSelectedNonce'   => wp_create_nonce('gbi_import_selected_nonce'),
            'strings'               => array(
                'importing'         => __('Importing...', 'google-books-importer'),
                'imported'          => __('Imported', 'google-books-importer'),
                'failed'            => __('Failed', 'google-books-importer'),
                'searching'         => __('Searching...', 'google-books-importer'),
                'noResults'         => __('No books found', 'google-books-importer'),
                'selectBooks'       => __('Please select at least one book', 'google-books-importer'),
                'enterPrices'       => __('Please enter price and quantity for all selected books', 'google-books-importer'),
            )
        ));
    }

    /**
     * Render CSV Import page
     */
    public function render_csv_import_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'google-books-importer'));
        }

        include GBI_PLUGIN_DIR . 'admin/views/csv-import.php';
    }

    /**
     * Render Search Import page
     */
    public function render_search_import_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'google-books-importer'));
        }

        include GBI_PLUGIN_DIR . 'admin/views/search-import.php';
    }

    /**
     * Render Settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'google-books-importer'));
        }

        include GBI_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * AJAX handler to test API key
     */
    public function ajax_test_api_key() {
        check_ajax_referer('gbi_test_api_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'google-books-importer')));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required', 'google-books-importer')));
        }

        $api = new GBI_Google_Books_API();
        $is_valid = $api->test_api_key($api_key);

        if ($is_valid) {
            wp_send_json_success(array('message' => __('API key is valid!', 'google-books-importer')));
        } else {
            wp_send_json_error(array('message' => __('API key is invalid or connection failed', 'google-books-importer')));
        }
    }
}
