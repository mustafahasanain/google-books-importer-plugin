<?php
/**
 * Settings Page Template
 *
 * @package Google_Books_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$settings = get_option('gbi_settings', array(
    'api_key'           => '',
    'image_width'       => 400,
    'image_height'      => 600,
    'duplicate_action'  => 'skip',
    'default_category'  => 'books'
));
?>

<div class="wrap gbi-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully!', 'google-books-importer'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields('gbi_settings_group'); ?>

        <table class="form-table" role="presentation">
            <!-- Google Books API Key -->
            <tr>
                <th scope="row">
                    <label for="gbi_api_key"><?php _e('Google Books API Key', 'google-books-importer'); ?> *</label>
                </th>
                <td>
                    <input type="text"
                           id="gbi_api_key"
                           name="gbi_settings[api_key]"
                           value="<?php echo esc_attr($settings['api_key']); ?>"
                           class="regular-text"
                           required />
                    <p class="description">
                        <?php
                        printf(
                            __('Get your API key from <a href="%s" target="_blank">Google Cloud Console</a>', 'google-books-importer'),
                            'https://console.cloud.google.com/apis/credentials'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <!-- Image Dimensions -->
            <tr>
                <th scope="row">
                    <label><?php _e('Standard Image Size', 'google-books-importer'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php _e('Standard Image Size', 'google-books-importer'); ?></span>
                        </legend>
                        <label for="gbi_image_width">
                            <?php _e('Width:', 'google-books-importer'); ?>
                            <input type="number"
                                   id="gbi_image_width"
                                   name="gbi_settings[image_width]"
                                   value="<?php echo esc_attr($settings['image_width']); ?>"
                                   class="small-text"
                                   min="100"
                                   max="2000" /> px
                        </label>
                        <br><br>
                        <label for="gbi_image_height">
                            <?php _e('Height:', 'google-books-importer'); ?>
                            <input type="number"
                                   id="gbi_image_height"
                                   name="gbi_settings[image_height]"
                                   value="<?php echo esc_attr($settings['image_height']); ?>"
                                   class="small-text"
                                   min="100"
                                   max="2000" /> px
                        </label>
                        <p class="description">
                            <?php _e('All book images will be resized to these dimensions.', 'google-books-importer'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <!-- Duplicate Handling -->
            <tr>
                <th scope="row">
                    <label for="gbi_duplicate_action"><?php _e('Duplicate Book Handling', 'google-books-importer'); ?></label>
                </th>
                <td>
                    <select id="gbi_duplicate_action" name="gbi_settings[duplicate_action]">
                        <option value="skip" <?php selected($settings['duplicate_action'], 'skip'); ?>>
                            <?php _e('Skip (do not import)', 'google-books-importer'); ?>
                        </option>
                        <option value="update" <?php selected($settings['duplicate_action'], 'update'); ?>>
                            <?php _e('Update (overwrite existing)', 'google-books-importer'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('How to handle books that already exist (matched by ISBN or title).', 'google-books-importer'); ?>
                    </p>
                </td>
            </tr>

            <!-- Default Category -->
            <tr>
                <th scope="row">
                    <label for="gbi_default_category"><?php _e('Default Category', 'google-books-importer'); ?></label>
                </th>
                <td>
                    <?php
                    $categories = get_terms(array(
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => false,
                    ));
                    ?>
                    <select id="gbi_default_category" name="gbi_settings[default_category]">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo esc_attr($category->slug); ?>"
                                    <?php selected($settings['default_category'], $category->slug); ?>>
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('All imported books will be assigned to this WooCommerce category.', 'google-books-importer'); ?>
                    </p>
                </td>
            </tr>

            <!-- Placeholder Image Upload -->
            <tr>
                <th scope="row">
                    <label for="gbi_placeholder_image"><?php _e('Placeholder Image', 'google-books-importer'); ?></label>
                </th>
                <td>
                    <?php
                    $placeholder_id = isset($settings['placeholder_image_id']) ? $settings['placeholder_image_id'] : 0;
                    $placeholder_url = $placeholder_id ? wp_get_attachment_url($placeholder_id) : GBI_PLUGIN_URL . 'assets/images/placeholder-book.svg';
                    ?>
                    <div class="gbi-image-upload">
                        <img src="<?php echo esc_url($placeholder_url); ?>"
                             id="gbi_placeholder_preview"
                             style="max-width: 150px; height: auto; display: block; margin-bottom: 10px;" />
                        <input type="hidden"
                               id="gbi_placeholder_image"
                               name="gbi_settings[placeholder_image_id]"
                               value="<?php echo esc_attr($placeholder_id); ?>" />
                        <button type="button" class="button" id="gbi_upload_placeholder">
                            <?php _e('Upload Placeholder Image', 'google-books-importer'); ?>
                        </button>
                        <button type="button" class="button" id="gbi_remove_placeholder">
                            <?php _e('Remove', 'google-books-importer'); ?>
                        </button>
                        <p class="description">
                            <?php _e('This image will be used for books without cover images.', 'google-books-importer'); ?>
                        </p>
                    </div>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Settings', 'google-books-importer')); ?>
    </form>

    <!-- API Key Test Section -->
    <hr>
    <h2><?php _e('Test API Connection', 'google-books-importer'); ?></h2>
    <p><?php _e('Test if your Google Books API key is working correctly.', 'google-books-importer'); ?></p>
    <button type="button" class="button button-secondary" id="gbi_test_api">
        <?php _e('Test API Key', 'google-books-importer'); ?>
    </button>
    <div id="gbi_api_test_result" style="margin-top: 10px;"></div>
</div>

<script>
jQuery(document).ready(function($) {
    // Media uploader for placeholder image
    var mediaUploader;

    $('#gbi_upload_placeholder').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: '<?php _e('Choose Placeholder Image', 'google-books-importer'); ?>',
            button: {
                text: '<?php _e('Use this image', 'google-books-importer'); ?>'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#gbi_placeholder_image').val(attachment.id);
            $('#gbi_placeholder_preview').attr('src', attachment.url);
        });

        mediaUploader.open();
    });

    $('#gbi_remove_placeholder').on('click', function(e) {
        e.preventDefault();
        $('#gbi_placeholder_image').val('');
        $('#gbi_placeholder_preview').attr('src', '<?php echo esc_url(GBI_PLUGIN_URL . 'assets/images/placeholder-book.svg'); ?>');
    });

    // Test API key
    $('#gbi_test_api').on('click', function() {
        var $button = $(this);
        var $result = $('#gbi_api_test_result');
        var apiKey = $('#gbi_api_key').val();

        if (!apiKey) {
            $result.html('<div class="notice notice-error"><p><?php _e('Please enter an API key first.', 'google-books-importer'); ?></p></div>');
            return;
        }

        $button.prop('disabled', true).text('<?php _e('Testing...', 'google-books-importer'); ?>');
        $result.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gbi_test_api_key',
                nonce: '<?php echo wp_create_nonce('gbi_test_api_nonce'); ?>',
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p><?php _e('API key is valid!', 'google-books-importer'); ?></p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p><?php _e('Connection error. Please try again.', 'google-books-importer'); ?></p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e('Test API Key', 'google-books-importer'); ?>');
            }
        });
    });
});
</script>
