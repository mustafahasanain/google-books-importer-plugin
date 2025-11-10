/**
 * Google Books Importer - Admin JavaScript
 *
 * @package Google_Books_Importer
 */

(function($) {
    'use strict';

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Initialize components
        initImageUploader();
        initApiTester();
        initFormValidation();
    });

    /**
     * Initialize image uploader for placeholder
     */
    function initImageUploader() {
        var mediaUploader;

        $('#gbi_upload_placeholder').on('click', function(e) {
            e.preventDefault();

            // If the uploader object has already been created, reopen the dialog
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            // Extend the wp.media object
            mediaUploader = wp.media({
                title: 'Choose Placeholder Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            // When an image is selected, run a callback
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#gbi_placeholder_image').val(attachment.id);
                $('#gbi_placeholder_preview').attr('src', attachment.url);
            });

            // Open the uploader dialog
            mediaUploader.open();
        });

        // Remove placeholder image
        $('#gbi_remove_placeholder').on('click', function(e) {
            e.preventDefault();
            $('#gbi_placeholder_image').val('');

            // Set to default plugin placeholder
            var defaultPlaceholder = gbiAdmin && gbiAdmin.pluginUrl ?
                gbiAdmin.pluginUrl + 'assets/images/placeholder-book.svg' :
                '';

            if (defaultPlaceholder) {
                $('#gbi_placeholder_preview').attr('src', defaultPlaceholder);
            }
        });
    }

    /**
     * Initialize API key tester
     */
    function initApiTester() {
        $('#gbi_test_api').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#gbi_api_test_result');
            var apiKey = $('#gbi_api_key').val();

            if (!apiKey) {
                showNotice($result, 'error', 'Please enter an API key first.');
                return;
            }

            $button.prop('disabled', true).text('Testing...');
            $result.html('');

            $.ajax({
                url: gbiAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gbi_test_api_key',
                    nonce: $('#gbi_test_api').data('nonce') || '',
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        showNotice($result, 'success', 'API key is valid!');
                    } else {
                        showNotice($result, 'error', response.data.message || 'API key is invalid.');
                    }
                },
                error: function() {
                    showNotice($result, 'error', 'Connection error. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test API Key');
                }
            });
        });
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        // CSV Import form
        $('#gbi-csv-import-form').on('submit', function(e) {
            var csvData = $('#gbi_csv_data').val().trim();

            if (!csvData) {
                e.preventDefault();
                alert('Please enter book data.');
                return false;
            }
        });

        // Search form
        $('#gbi-search-form').on('submit', function(e) {
            var query = $('#gbi_search_query').val().trim();

            if (!query) {
                e.preventDefault();
                alert('Please enter a search query.');
                return false;
            }
        });
    }

    /**
     * Show a WordPress-style notice
     *
     * @param {jQuery} $container Container element
     * @param {string} type Notice type (success, error, warning, info)
     * @param {string} message Notice message
     */
    function showNotice($container, type, message) {
        var noticeClass = 'notice notice-' + type;
        var notice = '<div class="' + noticeClass + '"><p>' + message + '</p></div>';
        $container.html(notice);
    }

    /**
     * Format price with currency
     *
     * @param {number} price Price value
     * @param {string} currency Currency symbol (default: '$')
     * @return {string} Formatted price
     */
    function formatPrice(price, currency) {
        currency = currency || '$';
        return currency + parseFloat(price).toFixed(2);
    }

    /**
     * Sanitize filename
     *
     * @param {string} filename Original filename
     * @return {string} Sanitized filename
     */
    function sanitizeFilename(filename) {
        return filename
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    /**
     * Debounce function
     *
     * @param {Function} func Function to debounce
     * @param {number} wait Wait time in milliseconds
     * @return {Function} Debounced function
     */
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

    /**
     * Show loading spinner
     *
     * @param {jQuery} $element Element to show spinner in
     */
    function showLoading($element) {
        $element.html('<div class="gbi-loading">Loading...</div>');
    }

    /**
     * Show empty state message
     *
     * @param {jQuery} $element Element to show message in
     * @param {string} title Title text
     * @param {string} message Message text
     */
    function showEmptyState($element, title, message) {
        var html = '<div class="gbi-empty-state">' +
            '<h3>' + title + '</h3>' +
            '<p>' + message + '</p>' +
            '</div>';
        $element.html(html);
    }

    /**
     * Validate URL
     *
     * @param {string} url URL to validate
     * @return {boolean} True if valid URL
     */
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    }

    // Export utility functions to global scope if needed
    window.GBI_Utils = {
        formatPrice: formatPrice,
        sanitizeFilename: sanitizeFilename,
        debounce: debounce,
        showLoading: showLoading,
        showEmptyState: showEmptyState,
        isValidUrl: isValidUrl,
        showNotice: showNotice
    };

})(jQuery);
