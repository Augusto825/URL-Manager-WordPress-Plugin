<?php
/*
Plugin Name: URL Manager 2024
Description: A plugin to manage and store URLs, remove duplicates, and upload URLs via CSV.
Version: 12.1
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

        // Handle manual URL input
        if (!empty($_POST['url_list'])) {
            $url_list = explode("\n", sanitize_textarea_field($_POST['url_list']));
            foreach ($url_list as $url) {
                $url = trim($url);
                if (!empty($url)) {
                    // Insert URL if not a duplicate
                    $wpdb->insert($table_name, array('url' => $url, 'url_list_name' => 'Manual'));
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
                    // Insert URL if not a duplicate
                    $wpdb->insert($table_name, array('url' => $url, 'url_list_name' => 'CSV'));
                }
            }
        }

        // After processing URLs, export them to CSV
        url_manager_2024_export_to_csv();

        echo '<div class="notice notice-success is-dismissible"><p>URLs processed and saved successfully.</p></div>';
    }
}
add_action('admin_init', 'url_manager_2024_handle_submission');

// Export data to CSV file
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
