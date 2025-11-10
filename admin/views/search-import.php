<?php
/**
 * Search & Import Page Template
 *
 * @package Google_Books_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$settings = get_option('gbi_settings', array());
$api_key_set = !empty($settings['api_key']);
?>

<div class="wrap gbi-search-import">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (!$api_key_set): ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    __('Please configure your Google Books API key in <a href="%s">Settings</a> before searching books.', 'google-books-importer'),
                    admin_url('admin.php?page=gbi-settings')
                );
                ?>
            </p>
        </div>
    <?php else: ?>

        <!-- Search Form -->
        <div class="gbi-search-section">
            <h2><?php _e('Search Google Books', 'google-books-importer'); ?></h2>
            <form id="gbi-search-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="gbi_search_query"><?php _e('Search Query', 'google-books-importer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="gbi_search_query"
                                   name="query"
                                   class="regular-text"
                                   placeholder="<?php _e('Enter book title, author, or keyword...', 'google-books-importer'); ?>"
                                   required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="gbi_search_filter"><?php _e('Search Filter', 'google-books-importer'); ?></label>
                        </th>
                        <td>
                            <select id="gbi_search_filter" name="filter">
                                <option value=""><?php _e('All (default)', 'google-books-importer'); ?></option>
                                <option value="author"><?php _e('By Author', 'google-books-importer'); ?></option>
                                <option value="subject"><?php _e('By Subject/Category', 'google-books-importer'); ?></option>
                            </select>
                            <p class="description">
                                <?php _e('Narrow your search by author or subject category', 'google-books-importer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large" id="gbi_search_button">
                        <?php _e('Search Books', 'google-books-importer'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin: 0 10px;"></span>
                </p>
            </form>
        </div>

        <!-- Search Results -->
        <div id="gbi_search_results" style="display: none;">
            <h2>
                <?php _e('Search Results', 'google-books-importer'); ?>
                <span id="gbi_results_count"></span>
            </h2>

            <div class="gbi-bulk-actions">
                <label>
                    <input type="checkbox" id="gbi_select_all" />
                    <?php _e('Select All', 'google-books-importer'); ?>
                </label>
                <button type="button" class="button button-primary" id="gbi_import_selected">
                    <?php _e('Import Selected Books', 'google-books-importer'); ?>
                </button>
            </div>

            <div id="gbi_books_grid" class="gbi-books-grid"></div>
        </div>

        <!-- Import Progress -->
        <div id="gbi_import_progress_section" style="display: none;">
            <h3><?php _e('Import Progress', 'google-books-importer'); ?></h3>
            <div class="gbi-progress-bar">
                <div class="gbi-progress-fill" style="width: 0%;"></div>
            </div>
            <p class="gbi-progress-text">0%</p>
        </div>

        <!-- Import Results -->
        <div id="gbi_import_results_section" style="display: none;">
            <h3><?php _e('Import Results', 'google-books-importer'); ?></h3>
            <div class="gbi-results-summary">
                <span class="gbi-success-count">0</span> <?php _e('successful', 'google-books-importer'); ?>,
                <span class="gbi-error-count">0</span> <?php _e('failed', 'google-books-importer'); ?>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Status', 'google-books-importer'); ?></th>
                        <th><?php _e('Book Title', 'google-books-importer'); ?></th>
                        <th><?php _e('Message', 'google-books-importer'); ?></th>
                        <th><?php _e('Action', 'google-books-importer'); ?></th>
                    </tr>
                </thead>
                <tbody id="gbi_import_results_body"></tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<style>
.gbi-search-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccc;
    border-radius: 4px;
    margin-bottom: 20px;
}

.gbi-books-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.gbi-book-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    position: relative;
    transition: box-shadow 0.3s;
}

.gbi-book-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.gbi-book-card.selected {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
}

.gbi-book-checkbox {
    position: absolute;
    top: 10px;
    right: 10px;
}

.gbi-book-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-radius: 4px;
    margin-bottom: 10px;
    background: #f0f0f0;
}

.gbi-book-title {
    font-size: 14px;
    font-weight: bold;
    margin: 10px 0 5px;
    line-height: 1.4;
    height: 40px;
    overflow: hidden;
}

.gbi-book-author {
    font-size: 13px;
    color: #666;
    margin: 5px 0;
}

.gbi-book-meta {
    font-size: 12px;
    color: #999;
    margin: 5px 0;
}

.gbi-book-inputs {
    margin-top: 10px;
    display: none;
}

.gbi-book-card.selected .gbi-book-inputs {
    display: block;
}

.gbi-book-inputs label {
    display: block;
    margin: 5px 0;
    font-size: 12px;
}

.gbi-book-inputs input {
    width: 100%;
    padding: 5px;
    font-size: 13px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.gbi-bulk-actions {
    background: #f9f9f9;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 20px 0;
}

.gbi-bulk-actions label {
    margin-right: 20px;
    font-weight: 600;
}

.gbi-progress-bar {
    width: 100%;
    height: 30px;
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
    margin: 10px 0;
}

.gbi-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4caf50, #45a049);
    transition: width 0.3s ease;
}

.gbi-progress-text {
    text-align: center;
    font-weight: bold;
}

.gbi-results-summary {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 15px;
    margin: 15px 0;
    border-radius: 4px;
}

.gbi-success-count {
    color: #46b450;
    font-weight: bold;
    font-size: 18px;
}

.gbi-error-count {
    color: #dc3232;
    font-weight: bold;
    font-size: 18px;
}

.gbi-status-success {
    color: #46b450;
    font-weight: bold;
}

.gbi-status-error {
    color: #dc3232;
    font-weight: bold;
}
</style>

<script>
jQuery(document).ready(function($) {
    var searchResults = [];

    // Search form
    $('#gbi-search-form').on('submit', function(e) {
        e.preventDefault();

        var query = $('#gbi_search_query').val().trim();
        var filter = $('#gbi_search_filter').val();

        if (!query) {
            return;
        }

        $('#gbi_search_button').prop('disabled', true);
        $('.spinner').addClass('is-active');
        $('#gbi_books_grid').empty();
        $('#gbi_search_results').hide();

        $.ajax({
            url: gbiAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gbi_search_books',
                nonce: gbiAdmin.searchNonce,
                query: query,
                filter: filter
            },
            success: function(response) {
                if (response.success && response.data.books) {
                    searchResults = response.data.books;
                    displaySearchResults(searchResults);
                } else {
                    alert(response.data.message || gbiAdmin.strings.noResults);
                }
            },
            error: function() {
                alert('<?php _e('Connection error. Please try again.', 'google-books-importer'); ?>');
            },
            complete: function() {
                $('#gbi_search_button').prop('disabled', false);
                $('.spinner').removeClass('is-active');
            }
        });
    });

    function displaySearchResults(books) {
        if (!books || books.length === 0) {
            $('#gbi_books_grid').html('<p><?php _e('No books found.', 'google-books-importer'); ?></p>');
            $('#gbi_search_results').show();
            return;
        }

        $('#gbi_results_count').text('(' + books.length + ' books)');
        $('#gbi_books_grid').empty();

        books.forEach(function(book, index) {
            var imageUrl = book.image_url || '<?php echo GBI_PLUGIN_URL; ?>assets/images/placeholder-book.svg';
            var author = book.authors || '<?php _e('Unknown Author', 'google-books-importer'); ?>';
            var publisher = book.publisher || '';

            var card = $('<div class="gbi-book-card" data-index="' + index + '"></div>');

            card.html(
                '<input type="checkbox" class="gbi-book-checkbox" />' +
                '<img src="' + imageUrl + '" class="gbi-book-image" alt="' + book.title + '" />' +
                '<div class="gbi-book-title">' + book.title + '</div>' +
                '<div class="gbi-book-author">' + author + '</div>' +
                '<div class="gbi-book-meta">' + publisher + '</div>' +
                '<div class="gbi-book-inputs">' +
                    '<label><?php _e('Price:', 'google-books-importer'); ?> <input type="number" class="gbi-book-price" step="0.01" min="0" placeholder="0.00" required /></label>' +
                    '<label><?php _e('Quantity:', 'google-books-importer'); ?> <input type="number" class="gbi-book-quantity" min="1" value="1" required /></label>' +
                '</div>'
            );

            $('#gbi_books_grid').append(card);
        });

        $('#gbi_search_results').show();
    }

    // Select/deselect book cards
    $(document).on('change', '.gbi-book-checkbox', function() {
        $(this).closest('.gbi-book-card').toggleClass('selected', this.checked);
    });

    // Select all
    $('#gbi_select_all').on('change', function() {
        $('.gbi-book-checkbox').prop('checked', this.checked).trigger('change');
    });

    // Import selected
    $('#gbi_import_selected').on('click', function() {
        var selected = [];

        $('.gbi-book-card.selected').each(function() {
            var index = $(this).data('index');
            var price = parseFloat($(this).find('.gbi-book-price').val());
            var quantity = parseInt($(this).find('.gbi-book-quantity').val());

            if (!price || price <= 0 || !quantity || quantity <= 0) {
                return;
            }

            var book = $.extend({}, searchResults[index], {
                price: price,
                quantity: quantity
            });

            selected.push(book);
        });

        if (selected.length === 0) {
            alert(gbiAdmin.strings.selectBooks);
            return;
        }

        // Validate all have price and quantity
        var allValid = selected.every(function(book) {
            return book.price && book.quantity;
        });

        if (!allValid) {
            alert(gbiAdmin.strings.enterPrices);
            return;
        }

        importBooks(selected);
    });

    function importBooks(books) {
        $('#gbi_import_selected').prop('disabled', true);
        $('#gbi_import_progress_section').show();
        $('#gbi_import_results_section').hide();
        $('#gbi_import_results_body').empty();

        var successCount = 0;
        var errorCount = 0;
        var processed = 0;
        var total = books.length;

        processBook(0);

        function processBook(index) {
            if (index >= total) {
                $('#gbi_import_selected').prop('disabled', false);
                $('#gbi_import_progress_section').hide();
                $('#gbi_import_results_section').show();
                $('.gbi-success-count').text(successCount);
                $('.gbi-error-count').text(errorCount);
                return;
            }

            var book = books[index];
            processed = index + 1;
            var percent = Math.round((processed / total) * 100);
            $('.gbi-progress-fill').css('width', percent + '%');
            $('.gbi-progress-text').text(percent + '% - ' + processed + ' / ' + total);

            $.ajax({
                url: gbiAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gbi_import_selected',
                    nonce: gbiAdmin.importSelectedNonce,
                    books: [book]
                },
                success: function(response) {
                    if (response.success && response.data.results) {
                        var result = response.data.results[0];
                        addResultRow(result);
                        if (result.success) {
                            successCount++;
                        } else {
                            errorCount++;
                        }
                    }
                },
                error: function() {
                    errorCount++;
                    addResultRow({
                        success: false,
                        title: book.title,
                        message: '<?php _e('Connection error', 'google-books-importer'); ?>'
                    });
                },
                complete: function() {
                    setTimeout(function() {
                        processBook(index + 1);
                    }, 500);
                }
            });
        }

        function addResultRow(result) {
            var statusClass = result.success ? 'gbi-status-success' : 'gbi-status-error';
            var statusIcon = result.success ? '✓' : '✗';
            var statusText = result.success ? '<?php _e('Success', 'google-books-importer'); ?>' : '<?php _e('Failed', 'google-books-importer'); ?>';
            var actionHtml = '';

            if (result.success && result.product_url) {
                actionHtml = '<a href="' + result.product_url + '" target="_blank" class="button button-small"><?php _e('View Product', 'google-books-importer'); ?></a>';
            }

            var row = '<tr>' +
                '<td><span class="' + statusClass + '">' + statusIcon + ' ' + statusText + '</span></td>' +
                '<td>' + result.title + '</td>' +
                '<td>' + result.message + '</td>' +
                '<td>' + actionHtml + '</td>' +
                '</tr>';

            $('#gbi_import_results_body').append(row);
        }
    }
});
</script>
