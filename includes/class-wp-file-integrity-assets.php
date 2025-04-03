<?php
/**
 * WordPress File Integrity Assets Loader
 * 
 * Handles loading of CSS and JavaScript files for the plugin
 */

if (!class_exists('WP_File_Integrity_Assets')) {

    class WP_File_Integrity_Assets {
        
        /**
         * Initialize the assets
         */
        public function __construct() {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        }
        
        /**
         * Enqueue admin CSS and JavaScript
         * 
         * @param string $hook Current admin page
         */
        public function enqueue_admin_assets($hook) {
            // Only load on our plugin pages
            if (strpos($hook, 'wp-file-integrity-checker') === false && 
                strpos($hook, 'tools_page_wp-file-integrity-checker') === false) {
                return;
            }
            
            // Enqueue CSS
            wp_enqueue_style(
                'wp-file-integrity-admin-css',
                plugin_dir_url(WP_FILE_INTEGRITY_FILE) . 'assets/css/wp-file-integrity-admin.css',
                array(),
                WP_FILE_INTEGRITY_VERSION
            );
            
            // Enqueue JS
            wp_enqueue_script(
                'wp-file-integrity-admin-js',
                plugin_dir_url(WP_FILE_INTEGRITY_FILE) . 'assets/js/wp-file-integrity-admin.js',
                array('jquery'),
                WP_FILE_INTEGRITY_VERSION,
                true
            );
        }
    }
}