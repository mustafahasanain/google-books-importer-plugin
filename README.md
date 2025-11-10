# Google Books Importer for WooCommerce

A powerful WordPress plugin that automatically imports books from the Google Books API into your WooCommerce store. Perfect for online bookstores, libraries, and book retailers.

## Features

### 🚀 Dual Import Methods
- **CSV Bulk Import**: Paste a list of books with titles, quantities, and prices - the plugin fetches all book data from Google Books API
- **Manual Search & Import**: Search Google Books directly, preview results, select books, and import them with custom pricing

### 📚 Comprehensive Book Data
- Book title, subtitle, and full description (English from Google Books API)
- Author(s), publisher, and publication date
- ISBN-10 and ISBN-13
- Page count and language
- Categories/subjects
- Book cover images (automatically downloaded and resized)
- Preview and info links

### 🎨 Smart Image Handling
- Automatically downloads book cover images from Google Books
- Resizes all images to standard dimensions (configurable)
- Uses placeholder image for books without covers
- Custom placeholder upload support

### 🔧 Advanced Features
- Duplicate detection (by ISBN or title)
- Choose to skip or update existing products
- Real-time import progress tracking
- Detailed import results with success/error reporting
- Bulk selection and import
- AJAX-powered for smooth user experience
- WooCommerce custom fields for all book metadata

## Requirements

- WordPress 5.8 or higher
- WooCommerce 6.0 or higher
- PHP 7.4 or higher
- Google Books API Key (free from Google Cloud Console)

## Installation

### Method 1: Upload via WordPress Admin

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" button
4. Choose the ZIP file and click "Install Now"
5. Click "Activate Plugin"

### Method 2: Manual Installation

1. Download and extract the plugin
2. Upload the `google-books-importer-plugin` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress

### Method 3: Direct Clone (for developers)

```bash
cd wp-content/plugins/
git clone https://github.com/mustafahasanain/google-books-importer-plugin.git
```

## Configuration

### 1. Get Google Books API Key

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (or select existing)
3. Enable the **Google Books API**
4. Go to Credentials → Create Credentials → API Key
5. Copy your API key

### 2. Configure Plugin Settings

1. Go to WordPress Admin → **Books Importer** → **Settings**
2. Paste your Google Books API Key
3. Configure image dimensions (default: 400x600px)
4. Set duplicate handling preference:
   - **Skip**: Don't import duplicates
   - **Update**: Overwrite existing products
5. Choose default product category
6. (Optional) Upload custom placeholder image
7. Click "Save Settings"
8. Test your API key using the "Test API Key" button

## Usage

### Method 1: CSV Bulk Import

Perfect for importing large lists of books with your own prices.

1. Go to **Books Importer** → **CSV Import**
2. Prepare your book list in this format:
   ```
   Book Title | Quantity | Price
   ```

3. Example:
   ```
   1000 First Words in German | 1 | 16000
   101 Video Games to Play Before You Grow Up | 1 | 8000
   1, 2, 3, Do the Dinosaur | 2 | 4500
   12th of Never | 1 | 3000
   30 Book Samples to Change Your Life | 1 | 7000
   ```

4. Paste your list into the text area
5. Click "Start Import"
6. Watch the real-time progress
7. Review the results

**How it works:**
- Plugin searches each title in Google Books API
- Fetches: description, author, image, ISBN, publisher, etc.
- Creates WooCommerce product with your price and quantity
- Downloads and resizes book cover image
- Adds all metadata as custom fields

### Method 2: Manual Search & Import

Perfect for carefully selecting specific books.

1. Go to **Books Importer** → **Search & Import**
2. Enter search query (book title, author, or keyword)
3. Optional: Filter by author or subject/category
4. Click "Search Books"
5. Browse the results with book cards showing:
   - Cover image
   - Title and author
   - Publisher
6. Check the boxes for books you want to import
7. Enter **Price** and **Quantity** for each selected book
8. Click "Import Selected Books"
9. Watch the progress and review results

## Book Product Structure

Each imported book becomes a WooCommerce product with:

### Standard WooCommerce Fields
- **Product Name**: Book title
- **Description**: Full description from Google Books (English)
- **Short Description**: Auto-generated excerpt
- **Regular Price**: Your price (from CSV or manual input)
- **Stock Quantity**: From CSV or manual input
- **SKU**: Auto-generated from ISBN (format: `BOOK-{ISBN}`)
- **Category**: Assigned to "Books" category (or custom)
- **Featured Image**: Downloaded book cover (or placeholder)

### Custom Fields (Meta Data)
All stored with `_gbi_` prefix for easy identification:

- `_gbi_google_id`: Google Books volume ID
- `_gbi_isbn`: Primary ISBN
- `_gbi_isbn_10`: ISBN-10
- `_gbi_isbn_13`: ISBN-13
- `_gbi_authors`: Author name(s)
- `_gbi_publisher`: Publisher name
- `_gbi_published_date`: Publication date
- `_gbi_page_count`: Number of pages
- `_gbi_categories`: Book categories/subjects
- `_gbi_language`: Language code
- `_gbi_preview_link`: Google Books preview URL
- `_gbi_info_link`: Google Books info URL
- `_gbi_imported_at`: Import timestamp

## Troubleshooting

### API Key Issues

**Problem**: "API key is invalid"
- **Solution**: Double-check your API key in Settings
- Ensure Google Books API is enabled in Google Cloud Console
- Check for extra spaces when copying the key

**Problem**: "API quota exceeded"
- **Solution**: Google Books API has daily limits (1000 requests/day for free tier)
- Wait 24 hours or upgrade your Google Cloud plan

### Import Issues

**Problem**: "Book not found in Google Books API"
- **Solution**: Try different search terms
- Some books may not be in Google Books database
- Try searching by ISBN instead of title

**Problem**: "Image download failed"
- **Solution**: Placeholder image will be used automatically
- Check your server's outbound connection settings
- Verify GD library is installed for image processing

**Problem**: "Duplicate book skipped"
- **Solution**: This is expected behavior if "Skip" is selected in settings
- Change to "Update" in Settings if you want to overwrite
- Or delete the existing product first

### Performance Issues

**Problem**: Import is slow
- **Solution**: This is normal - each book requires:
  - API request to Google Books
  - Image download and resize
  - Product creation in WooCommerce
- Recommended: Import in batches of 20-50 books
- Large imports may take several minutes

## Price Format Support

The plugin intelligently parses various price formats:

```
16000          → 16000.00
16,000         → 16000.00
16.50          → 16.50
$16.50         → 16.50
16,000.50      → 16000.50
16.000,50      → 16000.50 (European format)
16,000 د.ع.    → 16000.00 (Iraqi Dinar)
16,000 IQD     → 16000.00
```

## Extending the Plugin

### Display Custom Fields on Product Page

Add this to your theme's `functions.php`:

```php
add_action('woocommerce_single_product_summary', 'display_book_metadata', 25);
function display_book_metadata() {
    global $product;

    $author = get_post_meta($product->get_id(), '_gbi_authors', true);
    $publisher = get_post_meta($product->get_id(), '_gbi_publisher', true);
    $isbn = get_post_meta($product->get_id(), '_gbi_isbn', true);
    $pages = get_post_meta($product->get_id(), '_gbi_page_count', true);

    if ($author || $publisher || $isbn || $pages) {
        echo '<div class="book-metadata">';
        if ($author) echo '<p><strong>Author:</strong> ' . esc_html($author) . '</p>';
        if ($publisher) echo '<p><strong>Publisher:</strong> ' . esc_html($publisher) . '</p>';
        if ($isbn) echo '<p><strong>ISBN:</strong> ' . esc_html($isbn) . '</p>';
        if ($pages) echo '<p><strong>Pages:</strong> ' . esc_html($pages) . '</p>';
        echo '</div>';
    }
}
```

### Custom Placeholder Image

You can replace the default placeholder:

1. Go to **Settings** → Upload custom placeholder image
2. Or replace `assets/images/placeholder-book.svg` with your own image

## Developer Notes

### File Structure

```
google-books-importer-plugin/
├── google-books-importer.php          # Main plugin file
├── includes/
│   ├── class-google-books-api.php     # API handler
│   ├── class-product-creator.php      # Product creation logic
│   ├── class-csv-parser.php           # CSV parsing
│   └── class-image-handler.php        # Image processing
├── admin/
│   ├── class-admin-pages.php          # Admin menu & pages
│   ├── views/
│   │   ├── settings.php               # Settings UI
│   │   ├── csv-import.php             # CSV import UI
│   │   └── search-import.php          # Search UI
│   ├── css/admin-style.css            # Admin styles
│   └── js/admin-script.js             # Admin JavaScript
├── assets/images/
│   └── placeholder-book.svg           # Default placeholder
└── README.md
```

### Filters & Hooks

**Filter product data before creation:**
```php
add_filter('gbi_before_create_product', 'custom_modify_product_data', 10, 1);
function custom_modify_product_data($book_data) {
    // Modify $book_data array
    return $book_data;
}
```

**Action after product created:**
```php
add_action('gbi_after_product_created', 'custom_after_import', 10, 2);
function custom_after_import($product_id, $book_data) {
    // Do something after import
}
```

## FAQ

**Q: Is the Google Books API free?**
A: Yes, Google Books API has a free tier with 1000 requests per day.

**Q: Can I translate book descriptions to other languages?**
A: The plugin uses English descriptions from Google Books API as-is. For translations, consider using a translation plugin like WPML or Polylang.

**Q: Does it support other e-commerce plugins besides WooCommerce?**
A: No, currently only WooCommerce is supported.

**Q: Can I import books with my own descriptions?**
A: Not directly. The plugin fetches descriptions from Google Books API. You can manually edit product descriptions after import.

**Q: What happens if a book doesn't have an image?**
A: The plugin will use the placeholder image (configurable in Settings).

**Q: Can I schedule automatic imports?**
A: Not built-in, but you can use WordPress cron jobs with custom code.

## Changelog

### Version 1.0.0 (2025)
- Initial release
- CSV bulk import functionality
- Manual search and import
- Google Books API integration
- Image download and resize
- Duplicate detection
- Custom fields for book metadata
- Configurable settings
- AJAX-powered UI
- Real-time progress tracking

## Support

For issues, questions, or contributions:

- **GitHub**: [https://github.com/mustafahasanain/google-books-importer-plugin](https://github.com/mustafahasanain/google-books-importer-plugin)
- **Issues**: [Report a bug](https://github.com/mustafahasanain/google-books-importer-plugin/issues)

## License

GPL v2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

## Credits

- Developed by [Mustafa Hasanain](https://github.com/mustafahasanain)
- Powered by [Google Books API](https://developers.google.com/books)
- Built for [WooCommerce](https://woocommerce.com/)

---

**Made with ❤️ for bookstores and book lovers**
