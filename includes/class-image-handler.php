<?php
/**
 * Image Handler
 *
 * Handles downloading, resizing, and managing book cover images
 *
 * @package Google_Books_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class GBI_Image_Handler {

    /**
     * Settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('gbi_settings', array(
            'image_width'          => 400,
            'image_height'         => 600,
            'placeholder_image_id' => 0
        ));
    }

    /**
     * Download and process an image from URL
     *
     * @param string $image_url Image URL from Google Books API
     * @param string $book_title Book title for filename
     * @return int|false Attachment ID or false on failure
     */
    public function download_and_attach($image_url, $book_title) {
        if (empty($image_url)) {
            return $this->get_placeholder_image_id();
        }

        // Download the image
        $tmp_file = $this->download_image($image_url);

        if (!$tmp_file) {
            return $this->get_placeholder_image_id();
        }

        // Resize the image
        $resized_file = $this->resize_image($tmp_file);

        if (!$resized_file) {
            @unlink($tmp_file);
            return $this->get_placeholder_image_id();
        }

        // Upload to WordPress media library
        $attachment_id = $this->upload_to_media_library($resized_file, $book_title);

        // Clean up temporary files
        @unlink($tmp_file);
        if ($resized_file !== $tmp_file) {
            @unlink($resized_file);
        }

        if (!$attachment_id) {
            return $this->get_placeholder_image_id();
        }

        return $attachment_id;
    }

    /**
     * Download image from URL to temporary file
     *
     * @param string $url Image URL
     * @return string|false Path to temporary file or false on failure
     */
    private function download_image($url) {
        // Download the file
        $response = wp_remote_get($url, array(
            'timeout'  => 30,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            error_log('Image download error: ' . $response->get_error_message());
            return false;
        }

        $image_data = wp_remote_retrieve_body($response);

        if (empty($image_data)) {
            return false;
        }

        // Get file extension from content type
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $extension = $this->get_extension_from_mime($content_type);

        // Create temporary file
        $tmp_file = wp_tempnam();

        // Rename with proper extension
        $tmp_file_with_ext = $tmp_file . '.' . $extension;
        @rename($tmp_file, $tmp_file_with_ext);

        // Write image data to file
        if (file_put_contents($tmp_file_with_ext, $image_data) === false) {
            return false;
        }

        return $tmp_file_with_ext;
    }

    /**
     * Resize image to standard dimensions
     *
     * @param string $file_path Path to image file
     * @return string|false Path to resized file or false on failure
     */
    private function resize_image($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        $target_width = intval($this->settings['image_width']);
        $target_height = intval($this->settings['image_height']);

        // Get image info
        $image_info = getimagesize($file_path);
        if (!$image_info) {
            return false;
        }

        list($width, $height, $type) = $image_info;

        // If already the correct size, return original file
        if ($width === $target_width && $height === $target_height) {
            return $file_path;
        }

        // Create image resource based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($file_path);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($file_path);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($file_path);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $source = imagecreatefromwebp($file_path);
                } else {
                    return false;
                }
                break;
            default:
                return false;
        }

        if (!$source) {
            return false;
        }

        // Create new image with target dimensions
        $resized = imagecreatetruecolor($target_width, $target_height);

        // Preserve transparency for PNG and GIF
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $target_width, $target_height, $transparent);
        }

        // Resize image
        imagecopyresampled(
            $resized,
            $source,
            0, 0, 0, 0,
            $target_width,
            $target_height,
            $width,
            $height
        );

        // Save resized image
        $resized_path = $file_path . '_resized';

        $saved = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $saved = imagejpeg($resized, $resized_path, 90);
                break;
            case IMAGETYPE_PNG:
                $saved = imagepng($resized, $resized_path, 9);
                break;
            case IMAGETYPE_GIF:
                $saved = imagegif($resized, $resized_path);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    $saved = imagewebp($resized, $resized_path, 90);
                }
                break;
        }

        // Free memory
        imagedestroy($source);
        imagedestroy($resized);

        return $saved ? $resized_path : false;
    }

    /**
     * Upload file to WordPress media library
     *
     * @param string $file_path Path to file
     * @param string $book_title Book title for filename
     * @return int|false Attachment ID or false on failure
     */
    private function upload_to_media_library($file_path, $book_title) {
        if (!file_exists($file_path)) {
            return false;
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Generate filename
        $filename = sanitize_file_name($book_title) . '-cover.jpg';

        // Prepare file array
        $file = array(
            'name'     => $filename,
            'tmp_name' => $file_path,
            'error'    => 0,
            'size'     => filesize($file_path)
        );

        // Upload file
        $upload = wp_handle_sideload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            error_log('Media upload error: ' . $upload['error']);
            return false;
        }

        // Create attachment
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => $book_title,
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attachment_id)) {
            return false;
        }

        // Generate metadata
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return $attachment_id;
    }

    /**
     * Get placeholder image attachment ID
     *
     * @return int|false Placeholder image ID or false
     */
    public function get_placeholder_image_id() {
        $placeholder_id = isset($this->settings['placeholder_image_id']) ? intval($this->settings['placeholder_image_id']) : 0;

        if ($placeholder_id && wp_attachment_is_image($placeholder_id)) {
            return $placeholder_id;
        }

        // If no custom placeholder, try to use default plugin placeholder
        $default_placeholder = GBI_PLUGIN_DIR . 'assets/images/placeholder-book.svg';
        if (file_exists($default_placeholder)) {
            // Upload default placeholder to media library if not already done
            $placeholder_id = $this->upload_default_placeholder();
            if ($placeholder_id) {
                // Save it in settings
                $this->settings['placeholder_image_id'] = $placeholder_id;
                update_option('gbi_settings', $this->settings);
                return $placeholder_id;
            }
        }

        return false;
    }

    /**
     * Upload default placeholder image to media library
     *
     * @return int|false Attachment ID or false
     */
    private function upload_default_placeholder() {
        $placeholder_path = GBI_PLUGIN_DIR . 'assets/images/placeholder-book.svg';

        if (!file_exists($placeholder_path)) {
            return false;
        }

        // Check if already uploaded
        $existing = get_posts(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'   => '_gbi_default_placeholder',
                    'value' => '1'
                )
            )
        ));

        if (!empty($existing)) {
            return $existing[0]->ID;
        }

        // Upload the file
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $file_array = array(
            'name'     => 'book-placeholder.png',
            'tmp_name' => $placeholder_path
        );

        // Copy to temp location since wp_handle_sideload moves the file
        $tmp = wp_tempnam('placeholder-');
        @copy($placeholder_path, $tmp);
        $file_array['tmp_name'] = $tmp;

        $attachment_id = media_handle_sideload($file_array, 0);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return false;
        }

        // Mark as default placeholder
        update_post_meta($attachment_id, '_gbi_default_placeholder', '1');

        return $attachment_id;
    }

    /**
     * Get file extension from MIME type
     *
     * @param string $mime MIME type
     * @return string File extension
     */
    private function get_extension_from_mime($mime) {
        $mime_map = array(
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp'
        );

        return isset($mime_map[$mime]) ? $mime_map[$mime] : 'jpg';
    }
}
