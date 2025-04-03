<?php
/**
 * Core functionality for WP File Integrity Checker
 * 
 * Handles file scanning, verification, and notifications
 */

if (!class_exists('WP_File_Integrity_Core')) {

    class WP_File_Integrity_Core {
        
        // Hold scan results
        public $scan_results = false;
        public $modified_files = array();
        public $missing_files = array();
        public $unknown_files = array();
        public $suspicious_files = array();
        public $verified_files = array();
        public $modified_files_wporg = array();
        public $not_in_wporg_files = array();
        public $verification_errors = array();
        
        /**
         * Constructor
         */
        public function __construct() {
            // Add scheduled check action
            add_action('wp_file_integrity_check', array($this, 'perform_scheduled_check'));
        }
        
        /**
         * Check WordPress core file integrity
         */
        public function check_file_integrity() {
            global $wp_version;
            
            // Clear previous results
            $this->reset_scan_results();
            
            // Get the checksums from the WordPress API
            $checksums = $this->get_core_checksums($wp_version);
            
            if (!$checksums) {
                add_settings_error(
                    'wp_file_integrity_checker',
                    'checksums_error',
                    __('Could not retrieve WordPress checksums. Please try again later.', 'wp-file-integrity-checker'),
                    'error'
                );
                return false;
            }
            
            // Get ABSPATH without trailing slash
            $abspath = untrailingslashit(ABSPATH);
            
            // Check core files against checksums
            foreach ($checksums as $file => $checksum) {
                // Skip Akismet and Hello Dolly files
                if ($this->should_skip_file($file)) {
                    continue;
                }
                
                $file_path = $abspath . '/' . $file;
                
                // Check if file exists
                if (!file_exists($file_path)) {
                    $this->missing_files[] = $file;
                    continue;
                }
                
                // Calculate the hash of the file
                $file_hash = md5_file($file_path);
                
                // Compare with expected hash
                if ($file_hash !== $checksum) {
                    $this->modified_files[] = $file;
                }
            }
            
            // Check for unknown files
            $unknown_files_checker = new WP_Unknown_Files_Checker();
            $this->unknown_files = $unknown_files_checker->check_for_unknown_files($checksums);
            
            // Check for suspicious files in plugins directory
            $suspicious_files_checker = new WP_Suspicious_Files_Checker();
            $this->suspicious_files = $suspicious_files_checker->check_for_suspicious_files();
            
            // Filter theme files that are commonly modified
            $theme_file_handler = new WP_Theme_File_Handler();
            $this->modified_files = $theme_file_handler->filter_modified_files($this->modified_files);
            
            // Send notification if configured
            $options = get_option('wp_file_integrity_checker_options', array());
            if (isset($options['email_notifications']) && $options['email_notifications'] && 
                (count($this->modified_files) > 0 || count($this->missing_files) > 0 || 
                 count($this->unknown_files) > 0 || count($this->suspicious_files) > 0)) {
                $this->send_notification_email();
            }
            
            // Store results in transient
            $this->store_scan_results();
            
            // Set scan results flag
            $this->scan_results = true;
            
            return true;
        }
        
        /**
         * Reset scan results
         */
        private function reset_scan_results() {
            $this->scan_results = false;
            $this->modified_files = array();
            $this->missing_files = array();
            $this->unknown_files = array();
            $this->suspicious_files = array();
            $this->verified_files = array();
            $this->modified_files_wporg = array();
            $this->not_in_wporg_files = array();
            $this->verification_errors = array();
        }
        
        /**
         * Store scan results in transient
         */
        private function store_scan_results() {
            set_transient('wp_file_integrity_scan_results', array(
                'modified_files' => $this->modified_files,
                'missing_files' => $this->missing_files,
                'unknown_files' => $this->unknown_files,
                'suspicious_files' => $this->suspicious_files,
                'verified_files' => $this->verified_files,
                'modified_files_wporg' => $this->modified_files_wporg,
                'not_in_wporg_files' => $this->not_in_wporg_files,
                'verification_errors' => $this->verification_errors,
                'timestamp' => current_time('timestamp')
            ), DAY_IN_SECONDS);
        }
        
        /**
         * Load stored scan results
         */
        public function load_stored_scan_results() {
            $stored_results = get_transient('wp_file_integrity_scan_results');
            
            if ($stored_results) {
                $this->modified_files = $stored_results['modified_files'];
                $this->missing_files = $stored_results['missing_files'];
                
                if (isset($stored_results['unknown_files'])) {
                    $this->unknown_files = $stored_results['unknown_files'];
                }
                
                if (isset($stored_results['suspicious_files'])) {
                    $this->suspicious_files = $stored_results['suspicious_files'];
                }
                
                if (isset($stored_results['verified_files'])) {
                    $this->verified_files = $stored_results['verified_files'];
                }
                
                if (isset($stored_results['modified_files_wporg'])) {
                    $this->modified_files_wporg = $stored_results['modified_files_wporg'];
                }
                
                if (isset($stored_results['not_in_wporg_files'])) {
                    $this->not_in_wporg_files = $stored_results['not_in_wporg_files'];
                }
                
                if (isset($stored_results['verification_errors'])) {
                    $this->verification_errors = $stored_results['verification_errors'];
                }
                
                $this->scan_results = true;
                
                return true;
            }
            
            return false;
        }
        
        /**
         * Verify suspicious files against WordPress.org SVN repository
         */
        public function verify_suspicious_files_against_wporg() {
            if (empty($this->suspicious_files)) {
                return false;
            }
            
            // Initialize the verifier
            $wporg_verifier = new WP_WPOrg_Plugin_Verifier();
            
            // Run verification
            $results = $wporg_verifier->verify_plugin_files($this->suspicious_files);
            
            // Store results
            $this->verified_files = $results['verified'];
            $this->modified_files_wporg = $results['modified'];
            $this->not_in_wporg_files = $results['not_in_wporg'];
            $this->verification_errors = $results['errors'];
            
            // Update stored results
            $this->store_scan_results();
            
            return true;
        }

        /**
         * Check WordPress core file integrity with WordPress.org verification
         */
        public function check_file_integrity_with_wporg_verification() {
            // First run the normal integrity check
            $result = $this->check_file_integrity();
            
            // Then verify suspicious files against WordPress.org
            if ($result && !empty($this->suspicious_files)) {
                $this->verify_suspicious_files_against_wporg();
            }
            
            return $result;
        }

        /**
         * Check if a file should be skipped during integrity checking
         *
         * @param string $file Relative file path
         * @return boolean True if the file should be skipped
         */
        private function should_skip_file($file) {
            // Skip Akismet plugin files
            if (strpos($file, 'wp-content/plugins/akismet/') === 0) {
                return true;
            }
            
            // Skip Hello Dolly plugin
            if ($file === 'wp-content/plugins/hello.php') {
                return true;
            }
            
            return false;
        }

        /**
         * Get the WordPress core checksums from the API
         */
        private function get_core_checksums($version) {
            $locale = get_locale();
            $url = 'https://api.wordpress.org/core/checksums/1.0/?' . 
                http_build_query(array('version' => $version, 'locale' => $locale));
            
            $response = wp_remote_get($url);
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (empty($data) || !isset($data['checksums']) || !is_array($data['checksums'])) {
                return false;
            }
            
            return $data['checksums'];
        }

        /**
         * Perform scheduled check
         */
        public function perform_scheduled_check() {
            $options = get_option('wp_file_integrity_checker_options', array());
            
            // Check if WordPress.org verification is enabled
            if (isset($options['verify_with_wporg']) && $options['verify_with_wporg']) {
                $this->check_file_integrity_with_wporg_verification();
            } else {
                $this->check_file_integrity();
            }
        }

        /**
         * Send notification email
         */
        private function send_notification_email() {
            $options = get_option('wp_file_integrity_checker_options', array());
            $email = isset($options['notification_email']) ? $options['notification_email'] : get_option('admin_email');
            
            $subject = sprintf(
                __('[%s] WordPress File Integrity Alert', 'wp-file-integrity-checker'),
                get_bloginfo('name')
            );
            
            $message = __('The WordPress File Integrity Checker has detected the following issues:', 'wp-file-integrity-checker') . "\n\n";
            
            if (count($this->modified_files) > 0) {
                $message .= __('Modified Files:', 'wp-file-integrity-checker') . "\n";
                foreach ($this->modified_files as $file) {
                    $message .= "- $file\n";
                }
                $message .= "\n";
            }
            
            if (count($this->missing_files) > 0) {
                $message .= __('Missing Files:', 'wp-file-integrity-checker') . "\n";
                foreach ($this->missing_files as $file) {
                    $message .= "- $file\n";
                }
                $message .= "\n";
            }
            
            if (count($this->unknown_files) > 0) {
                $message .= __('Unknown Files:', 'wp-file-integrity-checker') . "\n";
                foreach ($this->unknown_files as $file) {
                    $message .= "- $file\n";
                }
                $message .= "\n";
            }
            
            if (count($this->suspicious_files) > 0) {
                $message .= __('Suspicious Files:', 'wp-file-integrity-checker') . "\n";
                foreach ($this->suspicious_files as $file) {
                    $message .= "- $file\n";
                }
                $message .= "\n";
            }
            
            if (count($this->modified_files_wporg) > 0) {
                $message .= __('Modified Plugin Files (WordPress.org):', 'wp-file-integrity-checker') . "\n";
                foreach ($this->modified_files_wporg as $file) {
                    $message .= "- {$file['path']} (Plugin: {$file['plugin']})\n";
                }
                $message .= "\n";
            }
            
            $message .= __('Please investigate these changes as they may indicate a security breach.', 'wp-file-integrity-checker') . "\n\n";
            $message .= __('Site URL:', 'wp-file-integrity-checker') . ' ' . home_url() . "\n";
            $message .= __('WordPress Version:', 'wp-file-integrity-checker') . ' ' . get_bloginfo('version') . "\n";
            
            wp_mail($email, $subject, $message);
        }
    }
}