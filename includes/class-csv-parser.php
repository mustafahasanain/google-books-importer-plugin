<?php
/**
 * CSV Parser
 *
 * Parses CSV/text input in the format: "Title | Quantity | Price"
 *
 * @package Google_Books_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class GBI_CSV_Parser {

    /**
     * Parse CSV data
     *
     * Expected format: "Book Title | Quantity | Price"
     * Example: "1000 First Words in German | 1 | 16,000 د.ع."
     *
     * @param string $csv_data Raw CSV/text data
     * @return array Array of parsed books
     */
    public function parse($csv_data) {
        if (empty($csv_data)) {
            return array();
        }

        // Split by lines
        $lines = array_filter(array_map('trim', explode("\n", $csv_data)));
        $books = array();

        foreach ($lines as $line_number => $line) {
            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Parse the line
            $parsed = $this->parse_line($line);

            if ($parsed) {
                $parsed['line_number'] = $line_number + 1;
                $books[] = $parsed;
            }
        }

        return $books;
    }

    /**
     * Parse a single line
     *
     * @param string $line Single line from CSV
     * @return array|false Parsed data or false if invalid
     */
    private function parse_line($line) {
        // Split by pipe character
        $parts = array_map('trim', explode('|', $line));

        // Must have exactly 3 parts: title, quantity, price
        if (count($parts) !== 3) {
            return false;
        }

        list($title, $quantity, $price) = $parts;

        // Validate title
        if (empty($title)) {
            return false;
        }

        // Parse quantity
        $quantity = $this->parse_quantity($quantity);

        // Parse price
        $price = $this->parse_price($price);

        return array(
            'title'    => sanitize_text_field($title),
            'quantity' => $quantity,
            'price'    => $price,
            'raw_line' => $line
        );
    }

    /**
     * Parse quantity value
     *
     * @param string $quantity Raw quantity value
     * @return int Parsed quantity (default 1 if invalid)
     */
    private function parse_quantity($quantity) {
        // Remove any non-digit characters
        $quantity = preg_replace('/[^0-9]/', '', $quantity);

        // Convert to integer
        $quantity = intval($quantity);

        // Default to 1 if invalid or zero
        if ($quantity <= 0) {
            $quantity = 1;
        }

        return $quantity;
    }

    /**
     * Parse price value
     *
     * Handles various formats:
     * - "16,000 د.ع."
     * - "16000 IQD"
     * - "16.50"
     * - "$16.50"
     *
     * @param string $price Raw price value
     * @return float Parsed price
     */
    private function parse_price($price) {
        // Remove currency symbols and text
        // Common currency symbols: $ £ € ¥ ₹ ₽ د.ع IQD USD etc.
        $price = preg_replace('/[^\d.,]/', '', $price);

        // Remove thousands separators (commas)
        // Assumes format like "16,000.50" or "16.000,50"
        // We'll handle both comma and dot as decimal separator

        // Count commas and dots
        $comma_count = substr_count($price, ',');
        $dot_count = substr_count($price, '.');

        if ($comma_count > 0 && $dot_count > 0) {
            // Both present - the last one is decimal separator
            $last_comma_pos = strrpos($price, ',');
            $last_dot_pos = strrpos($price, '.');

            if ($last_comma_pos > $last_dot_pos) {
                // Comma is decimal separator (e.g., "1.000,50")
                $price = str_replace('.', '', $price); // Remove thousands separator
                $price = str_replace(',', '.', $price); // Convert decimal separator
            } else {
                // Dot is decimal separator (e.g., "1,000.50")
                $price = str_replace(',', '', $price); // Remove thousands separator
            }
        } elseif ($comma_count > 1) {
            // Multiple commas - all are thousands separators
            $price = str_replace(',', '', $price);
        } elseif ($dot_count > 1) {
            // Multiple dots - all are thousands separators
            $price = str_replace('.', '', $price);
        } elseif ($comma_count === 1) {
            // Single comma - check position
            $comma_pos = strpos($price, ',');
            if (strlen($price) - $comma_pos <= 3) {
                // Likely decimal separator
                $price = str_replace(',', '.', $price);
            } else {
                // Likely thousands separator
                $price = str_replace(',', '', $price);
            }
        }

        // Convert to float
        $price = floatval($price);

        // Ensure non-negative
        if ($price < 0) {
            $price = 0;
        }

        return $price;
    }

    /**
     * Validate CSV data before parsing
     *
     * @param string $csv_data Raw CSV data
     * @return array Validation result with 'valid' and 'errors' keys
     */
    public function validate($csv_data) {
        $result = array(
            'valid'  => true,
            'errors' => array()
        );

        if (empty($csv_data)) {
            $result['valid'] = false;
            $result['errors'][] = __('CSV data is empty.', 'google-books-importer');
            return $result;
        }

        $lines = array_filter(array_map('trim', explode("\n", $csv_data)));

        if (empty($lines)) {
            $result['valid'] = false;
            $result['errors'][] = __('No valid lines found in CSV data.', 'google-books-importer');
            return $result;
        }

        $valid_lines = 0;
        foreach ($lines as $line_number => $line) {
            if (empty($line)) {
                continue;
            }

            $parts = explode('|', $line);
            if (count($parts) !== 3) {
                $result['errors'][] = sprintf(
                    __('Line %d: Invalid format. Expected "Title | Quantity | Price"', 'google-books-importer'),
                    $line_number + 1
                );
            } else {
                $valid_lines++;
            }
        }

        if ($valid_lines === 0) {
            $result['valid'] = false;
            $result['errors'][] = __('No valid book entries found. Please check the format: "Title | Quantity | Price"', 'google-books-importer');
        }

        return $result;
    }

    /**
     * Generate a sample CSV format for reference
     *
     * @return string Sample CSV text
     */
    public static function get_sample_format() {
        return "1000 First Words in German | 1 | 16,000\n" .
               "101 Video Games to Play Before You Grow Up | 1 | 8,000\n" .
               "1, 2, 3, Do the Dinosaur | 2 | 4,500\n" .
               "12th of Never | 1 | 3,000\n" .
               "30 Book Samples to Change Your Life | 1 | 7,000";
    }
}
