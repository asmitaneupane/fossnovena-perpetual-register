<?php
if ( ! defined('ABSPATH') ) exit;

class FNPR_Admin {

    public function hooks() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_fnpr_import', [$this, 'handle_import']); // form action
    }

    public function menu() {
        add_menu_page(
            __('Fossnovena Register', 'fossnovena-pr'),
            __('Fossnovena', 'fossnovena-pr'),
            'manage_options',
            'fossnovena-register',
            [$this, 'render_page'],
            'dashicons-list-view',
            56
        );
    }

    public function render_page() {
        if ( ! current_user_can('manage_options') ) return;
        $last_csv = file_exists(FNPR_CSV_PATH) ? basename(FNPR_CSV_PATH) : __('No CSV yet','fossnovena-pr');
        ?>
        <div class="wrap fnpr-wrap">
            <h1><?php esc_html_e('Perpetual Register — CSV Import', 'fossnovena-pr'); ?></h1>
            <p><?php esc_html_e('Upload a CSV to Replace or Append to the Perpetual Register.', 'fossnovena-pr'); ?></p>

            <?php if (!empty($_GET['fnpr_status'])): ?>
                <div class="notice notice-success">
                    <p><?php echo esc_html($_GET['fnpr_status']); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="fnpr-form">
                <?php wp_nonce_field('fnpr_import'); ?>
                <input type="hidden" name="action" value="fnpr_import" />

                <table class="form-table">
                    <tr>
                        <th><label for="csv_file"><?php esc_html_e('CSV File', 'fossnovena-pr'); ?></label></th>
                        <td><input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Mode', 'fossnovena-pr'); ?></th>
                        <td>
                            <label><input type="radio" name="mode" value="replace" required> <?php esc_html_e('Replace existing register', 'fossnovena-pr'); ?></label><br>
                            <label><input type="radio" name="mode" value="append" required> <?php esc_html_e('Append to existing register', 'fossnovena-pr'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Duplicate Handling', 'fossnovena-pr'); ?></th>
                        <td>
                            <label><input type="checkbox" name="ignore_duplicates" value="1" checked> <?php esc_html_e('Ignore duplicates (recommended)', 'fossnovena-pr'); ?></label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Import CSV', 'fossnovena-pr')); ?>
            </form>

            <hr>
            <p><strong><?php esc_html_e('Current master CSV: ', 'fossnovena-pr'); ?></strong> <?php echo esc_html($last_csv); ?>
            <?php if (file_exists(FNPR_CSV_PATH)): ?>
                — <a href="<?php echo esc_url(FNPR_UPLOAD_URL . '/perpetual-register.csv'); ?>" target="_blank"><?php esc_html_e('Download', 'fossnovena-pr'); ?></a>
            <?php endif; ?>
            </p>
        </div>
        <?php
    }

    public function handle_import() {
        if ( ! current_user_can('manage_options') ) wp_die('Insufficient permissions');
        check_admin_referer('fnpr_import');

        if ( empty($_FILES['csv_file']['name']) ) wp_die('No file uploaded');

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $overrides = ['test_form' => false, 'mimes' => ['csv' => 'text/csv','txt'=>'text/plain']];
        $uploaded = wp_handle_upload($_FILES['csv_file'], $overrides);

        if ( isset($uploaded['error']) ) {
            wp_die('Upload error: ' . esc_html($uploaded['error']));
        }

        $mode = in_array($_POST['mode'] ?? 'append', ['replace','append'], true) ? $_POST['mode'] : 'append';
        $ignore = ! empty($_POST['ignore_duplicates']);

        $importer = new FNPR_Importer();
        $result = $importer->import($uploaded['file'], $mode, $ignore);

        $status = sprintf(
            'Import complete. Mode: %s. Inserted: %d. Skipped: %d%s',
            esc_html(ucfirst($mode)),
            (int)$result['inserted'],
            (int)$result['skipped'],
            (!empty($result['errors']) ? ' (with errors)' : '')
        );

        // Clean up uploaded temp file
        @unlink($uploaded['file']);

        // Redirect back with status
        wp_redirect( add_query_arg(['page'=>'fossnovena-register','fnpr_status'=>rawurlencode($status)], admin_url('admin.php')) );
        exit;
    }
}
