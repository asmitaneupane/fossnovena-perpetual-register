<?php
/**
 * Plugin Name: Fossnovena Perpetual Register
 * Description: CSV-powered Perpetual Register with Replace/Append, shortcode display, search & pagination.
 * Version:     1.0.0
 * Author:      Asmita Neupane
 * Text Domain: fossnovena-pr
 */

if ( ! defined('ABSPATH') ) exit;

define('FNPR_VERSION', '1.0.0');
define('FNPR_PATH', plugin_dir_path(__FILE__));
define('FNPR_URL',  plugin_dir_url(__FILE__));
define('FNPR_TABLE', $GLOBALS['wpdb']->prefix . 'fn_perpetual_register');
define('FNPR_UPLOAD_DIR', wp_upload_dir()['basedir'] . '/fossnovena');
define('FNPR_UPLOAD_URL', wp_upload_dir()['baseurl'] . '/fossnovena');
define('FNPR_CSV_PATH', FNPR_UPLOAD_DIR . '/perpetual-register.csv');

require_once FNPR_PATH . 'includes/class-fnpr-activator.php';
require_once FNPR_PATH . 'includes/class-fnpr-importer.php';
require_once FNPR_PATH . 'includes/class-fnpr-admin.php';
require_once FNPR_PATH . 'includes/class-fnpr-frontend.php';

register_activation_hook(__FILE__, ['FNPR_Activator', 'activate']);

add_action('plugins_loaded', function () {
    // Admin assets
    add_action('admin_enqueue_scripts', function($hook){
        if ( strpos($hook, 'fossnovena') !== false ) {
            wp_enqueue_style('fnpr-admin', FNPR_URL . 'assets/admin.css', [], FNPR_VERSION);
            wp_enqueue_script('fnpr-admin', FNPR_URL . 'assets/admin.js', ['jquery'], FNPR_VERSION, true);
        }
    });

    // Admin Page
    (new FNPR_Admin())->hooks();

    // Shortcode / Frontend
    (new FNPR_Frontend())->hooks();
});
