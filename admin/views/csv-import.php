<?php
/**
 * CSV Import Page Template
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

<div class="wrap gbi-csv-import">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (!$api_key_set): ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    __('Please configure your Google Books API key in <a href="%s">Settings</a> before importing books.', 'google-books-importer'),
                    admin_url('admin.php?page=gbi-settings')
                );
                ?>
            </p>
        </div>
    <?php else: ?>

        <div class="gbi-import-section">
            <h2><?php _e('Bulk Import from List', 'google-books-importer'); ?></h2>
            <p class="description">
                <?php _e('Paste your book list below. Each line should contain: Book Title | Quantity | Price', 'google-books-importer'); ?>
            </p>

            <!-- Format Example -->
            <div class="gbi-format-example">
                <h3><?php _e('Format Example:', 'google-books-importer'); ?></h3>
                <pre><?php echo esc_html(GBI_CSV_Parser::get_sample_format()); ?></pre>
                <p class="description">
                    <?php _e('The plugin will search each title in Google Books API and fetch the description, author, image, and other details.', 'google-books-importer'); ?>
                </p>
            </div>

            <!-- Import Form -->
            <form id="gbi-csv-import-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="gbi_csv_data"><?php _e('Book List', 'google-books-importer'); ?></label>
                        </th>
                        <td>
                            <textarea id="gbi_csv_data"
                                      name="csv_data"
                                      rows="15"
                                      class="large-text code"
                                      placeholder="<?php echo esc_attr(GBI_CSV_Parser::get_sample_format()); ?>"></textarea>
                            <p class="description">
                                <?php _e('One book per line, separated by pipe character (|)', 'google-books-importer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large" id="gbi_start_import">
                        <?php _e('Start Import', 'google-books-importer'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin: 0 10px;"></span>
                </p>
            </form>

            <!-- Progress Section -->
            <div id="gbi_import_progress" style="display: none;">
                <h3><?php _e('Import Progress', 'google-books-importer'); ?></h3>
                <div class="gbi-progress-bar">
                    <div class="gbi-progress-fill" style="width: 0%;"></div>
                </div>
                <p class="gbi-progress-text">0%</p>
            </div>

            <!-- Results Section -->
            <div id="gbi_import_results" style="display: none;">
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
                    <tbody id="gbi_results_body">
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
.gbi-format-example {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 20px 0;
}

.gbi-format-example pre {
    background: #fff;
    border: 1px solid #ddd;
    padding: 10px;
    overflow-x: auto;
    font-size: 13px;
    line-height: 1.6;
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
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.gbi-progress-text {
    text-align: center;
    font-size: 14px;
    font-weight: bold;
    color: #666;
}

.gbi-results-summary {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 15px;
    margin: 15px 0;
    border-radius: 4px;
    font-size: 16px;
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

.gbi-status-icon {
    font-size: 20px;
    margin-right: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    var importing = false;

    $('#gbi-csv-import-form').on('submit', function(e) {
        e.preventDefault();

        if (importing) {
            return;
        }

        var csvData = $('#gbi_csv_data').val().trim();

        if (!csvData) {
            alert('<?php _e('Please enter book data', 'google-books-importer'); ?>');
            return;
        }

        // Parse lines
        var lines = csvData.split('\n').filter(function(line) {
            return line.trim() !== '';
        });

        if (lines.length === 0) {
            alert('<?php _e('No valid book entries found', 'google-books-importer'); ?>');
            return;
        }

        importing = true;
        $('#gbi_start_import').prop('disabled', true);
        $('.spinner').addClass('is-active');
        $('#gbi_import_progress').show();
        $('#gbi_import_results').hide();
        $('#gbi_results_body').empty();

        var successCount = 0;
        var errorCount = 0;
        var processed = 0;
        var total = lines.length;

        // Process each line
        processLine(0);

        function processLine(index) {
            if (index >= total) {
                // All done
                importing = false;
                $('#gbi_start_import').prop('disabled', false);
                $('.spinner').removeClass('is-active');
                $('#gbi_import_progress').hide();
                $('#gbi_import_results').show();
                $('.gbi-success-count').text(successCount);
                $('.gbi-error-count').text(errorCount);
                return;
            }

            var line = lines[index];

            // Update progress
            processed = index + 1;
            var percent = Math.round((processed / total) * 100);
            $('.gbi-progress-fill').css('width', percent + '%');
            $('.gbi-progress-text').text(percent + '% - ' + processed + ' / ' + total);

            // Import this line
            $.ajax({
                url: gbiAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gbi_import_csv',
                    nonce: gbiAdmin.importNonce,
                    csv_data: line
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
                    } else {
                        errorCount++;
                        addResultRow({
                            success: false,
                            title: line.split('|')[0],
                            message: response.data.message || '<?php _e('Unknown error', 'google-books-importer'); ?>'
                        });
                    }
                },
                error: function() {
                    errorCount++;
                    addResultRow({
                        success: false,
                        title: line.split('|')[0],
                        message: '<?php _e('Connection error', 'google-books-importer'); ?>'
                    });
                },
                complete: function() {
                    // Process next line
                    setTimeout(function() {
                        processLine(index + 1);
                    }, 500); // Small delay to avoid rate limiting
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
                '<td><span class="' + statusClass + '"><span class="gbi-status-icon">' + statusIcon + '</span>' + statusText + '</span></td>' +
                '<td>' + result.title + '</td>' +
                '<td>' + result.message + '</td>' +
                '<td>' + actionHtml + '</td>' +
                '</tr>';

            $('#gbi_results_body').append(row);
            $('#gbi_import_results').show();

            // Scroll to bottom
            var resultsTable = $('#gbi_results_body').parent();
            resultsTable.scrollTop(resultsTable[0].scrollHeight);
        }
    });
});
</script>
