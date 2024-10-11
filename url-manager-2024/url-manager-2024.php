<?php
/*
Plugin Name: URL Manager 2024
Description: A plugin to manage and store URLs, remove duplicates, and upload URLs via CSV.
Version: 3.2
Author: Luis Fernando
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Database Table Creation
function url_manager_2024_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'url_manager_2024';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        url_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        url VARCHAR(255) NOT NULL,
        url_list_name VARCHAR(255) NOT NULL,
        date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (url_id),
        UNIQUE (url)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'url_manager_2024_create_table');

// Create CSV directory on activation
function url_manager_2024_create_csv_directory() {
    $csv_dir = WP_CONTENT_DIR . '/url-manager-2024';
    if (!is_dir($csv_dir)) {
        mkdir($csv_dir, 0755, true);
    }
}
register_activation_hook(__FILE__, 'url_manager_2024_create_csv_directory');

// Admin Page for URL Input and CSV Upload
function url_manager_2024_admin_menu() {
    add_menu_page(
        'URL Manager 2024',  
        'URL Manager 2024',  
        'manage_options',    
        'url-manager-2024',  
        'url_manager_2024_page', 
        'dashicons-admin-links',  
        20                      
    );
}
add_action('admin_menu', 'url_manager_2024_admin_menu');

// Callback for Admin Page
function url_manager_2024_page() {
    ?>
    <div class="wrap">
        <h1>URL Manager 2024</h1>
        <form method="post" enctype="multipart/form-data">
            <h2>Enter URLs Manually</h2>
            <textarea name="url_list" rows="5" cols="50"></textarea>
            <h2>Upload CSV</h2>
            <input type="file" name="csv_file" accept=".csv">
            <br><br>
            <input type="submit" name="submit_urls" class="button-primary" value="Submit">
        </form>
    </div>
    <?php
}

// Handle Form Submission
function url_manager_2024_handle_submission() {
    if (isset($_POST['submit_urls'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'url_manager_2024';

        $new_urls = array();

        // Handle manual URL input
        if (!empty($_POST['url_list'])) {
            $url_list = explode("\n", sanitize_textarea_field($_POST['url_list']));
            foreach ($url_list as $url) {
                $url = trim($url);
                if (!empty($url)) {
                    // Check if URL already exists in the database
                    $existing_url = $wpdb->get_var($wpdb->prepare("SELECT url FROM $table_name WHERE url = %s", $url));
                    if (!$existing_url) {
                        $wpdb->insert($table_name, array('url' => $url, 'url_list_name' => 'Manual'));
                        $new_urls[] = $url;  // Store new URL for CSV creation later
                    }
                }
            }
        }

        // Handle CSV upload
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $csv_file = $_FILES['csv_file']['tmp_name'];
            $csv_data = array_map('str_getcsv', file($csv_file));

            foreach ($csv_data as $row) {
                $url = esc_url_raw(trim($row[0]));
                if (!empty($url)) {
                    // Check if URL already exists in the database
                    $existing_url = $wpdb->get_var($wpdb->prepare("SELECT url FROM $table_name WHERE url = %s", $url));
                    if (!$existing_url) {
                        $wpdb->insert($table_name, array('url' => $url, 'url_list_name' => 'CSV'));
                        $new_urls[] = $url;  // Store new URL for CSV creation later
                    }
                }
            }

            // Check if the original CSV file or folder is empty, if so, create or update the CSV
            url_manager_2024_create_or_update_csv($csv_file);
        }

        // If there are any new unique URLs, create a new CSV for them
        if (!empty($new_urls)) {
            url_manager_2024_create_new_csv_for_unique_urls($new_urls);
        }

        echo '<div class="notice notice-success is-dismissible"><p>URLs processed and saved successfully.</p></div>';
    }
}
add_action('admin_init', 'url_manager_2024_handle_submission');

// Export data to CSV file (creates or updates CSV based on uploaded file)
function url_manager_2024_create_or_update_csv($uploaded_csv) {
    $csv_dir = WP_CONTENT_DIR . '/url-manager-2024';
    $file = $csv_dir . '/url_manager_2024.csv';

    // Check if the directory and CSV file exist or are empty
    if (!file_exists($file) || filesize($file) === 0) {
        copy($uploaded_csv, $file);  // Copy uploaded CSV to the target location
    } else {
        // Otherwise, update CSV content (optional: merge logic can be added if needed)
        unlink($file);
        copy($uploaded_csv, $file);
    }
}

// Create a new CSV for unique URLs that are not duplicates in the database
function url_manager_2024_create_new_csv_for_unique_urls($new_urls) {
    $csv_dir = WP_CONTENT_DIR . '/url-manager-2024';
    $unique_file = $csv_dir . '/url_manager_unique_urls_' . time() . '.csv';

    // Open the file in write mode ('w')
    $csv = fopen($unique_file, 'w');
    if (!$csv) {
        error_log('Failed to open unique URLs CSV file: ' . $unique_file);
        return;
    }

    // Add headers to the unique URLs CSV
    fputcsv($csv, array('url'));

    // Add the unique URLs to the CSV
    foreach ($new_urls as $url) {
        fputcsv($csv, array($url));
    }

    // Close the CSV file
    fclose($csv);
}

// Export all database data to the main CSV file
function url_manager_2024_export_to_csv() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'url_manager_2024';
    $csv_dir = WP_CONTENT_DIR . '/url-manager-2024';

    // Get all URLs from the database
    $urls = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    // Set the CSV file path
    $file = $csv_dir . '/url_manager_2024.csv';

    // Open the file in write mode ('w')
    $csv = fopen($file, 'w');
    if (!$csv) {
        error_log('Failed to open CSV file: ' . $file);
        return;
    }

    // Add CSV headers
    fputcsv($csv, array('url_id', 'url', 'url_list_name', 'date_added'));

    // Add URL data to the CSV file
    foreach ($urls as $url) {
        fputcsv($csv, $url);
    }

    // Close the CSV file
    fclose($csv);
}
?>
