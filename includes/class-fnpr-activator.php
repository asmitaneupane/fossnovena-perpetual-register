<?php
if ( ! defined('ABSPATH') ) exit;

class FNPR_Activator {
    public static function activate() {
        global $wpdb;
        $table = FNPR_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(191) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_name (full_name)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Ensure uploads dir exists
        if ( ! file_exists(FNPR_UPLOAD_DIR) ) {
            wp_mkdir_p(FNPR_UPLOAD_DIR);
        }

        // Create empty CSV if missing
        if ( ! file_exists(FNPR_CSV_PATH) ) {
            file_put_contents(FNPR_CSV_PATH, "name\n");
        }
    }
}
