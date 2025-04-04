<?php
/**
 * Plugin Name: WP File Integrity Checker
 * Plugin URI: https://wbcomdesigns.com/downloads/wp-file-integrity-checker
 * Description: Checks WordPress core file hashes against the official repository to detect possible infections or modifications.
 * Version: 1.0.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-file-integrity-checker
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WP_FILE_INTEGRITY_VERSION', '1.0.0');
define('WP_FILE_INTEGRITY_FILE', __FILE__);
define('WP_FILE_INTEGRITY_DIR', plugin_dir_path(__FILE__));
define('WP_FILE_INTEGRITY_URL', plugin_dir_url(__FILE__));

// Load required components
require_once WP_FILE_INTEGRITY_DIR . 'includes/class-wp-unknown-files-checker.php';
require_once WP_FILE_INTEGRITY_DIR . 'includes/class-wp-theme-file-handler.php';
require_once WP_FILE_INTEGRITY_DIR . 'includes/class-wp-suspicious-files-checker.php';
require_once WP_FILE_INTEGRITY_DIR . 'includes/class-wp-wporg-plugin-verifier.php';
require_once WP_FILE_INTEGRITY_DIR . 'includes/class-wp-file-integrity-core.php';
require_once WP_FILE_INTEGRITY_DIR . 'includes/class-wp-file-integrity-admin.php';
require_once WP_FILE_INTEGRITY_DIR . 'includes/class-wp-file-integrity-assets.php';

/**
 * Main plugin initialization function.
 */
function wp_file_integrity_init() {
    // Initialize core functionality
    $wp_file_integrity_core = new WP_File_Integrity_Core();
    
    // Initialize admin interface if in admin area
    if (is_admin()) {
        $wp_file_integrity_admin = new WP_File_Integrity_Admin($wp_file_integrity_core);
        $wp_file_integrity_assets = new WP_File_Integrity_Assets();
    }
}
add_action('plugins_loaded', 'wp_file_integrity_init');

/**
 * Activation hook function
 */
function wp_file_integrity_activate() {
    // Set default options
    $default_options = array(
        'enable_scheduled_checks' => 1,
        'check_frequency' => 'daily',
        'email_notifications' => 1,
        'notification_email' => get_option('admin_email'),
        'verify_with_wporg' => 1
    );
    
    update_option('wp_file_integrity_checker_options', $default_options);
    
    // Schedule the event
    if (!wp_next_scheduled('wp_file_integrity_check')) {
        wp_schedule_event(time(), 'daily', 'wp_file_integrity_check');
    }
}
register_activation_hook(__FILE__, 'wp_file_integrity_activate');

/**
 * Deactivation hook function
 */
function wp_file_integrity_deactivate() {
    // Clear the scheduled hook
    wp_clear_scheduled_hook('wp_file_integrity_check');
}
register_deactivation_hook(__FILE__, 'wp_file_integrity_deactivate');