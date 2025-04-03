<?php
/**
 * WordPress Unknown Files Checker
 * 
 * This file contains the functionality to check for unknown files in WordPress core directories.
 * Include this file in your main plugin file to add unknown files checking capability.
 */

if (!class_exists('WP_Unknown_Files_Checker')) {

    class WP_Unknown_Files_Checker {
        
        // Store unknown files
        private $unknown_files = array();
        
        // List of allowed WordPress drop-ins
        private $allowed_dropins = array(
            'advanced-cache.php',
            'blog-deleted.php',
            'blog-inactive.php',
            'blog-suspended.php',
            'db-error.php',
            'db.php',
            'maintenance.php',
            'object-cache.php',
            'php-error.php',
            'fatal-error-handler.php',
            'sunrise.php'
        );
        
        /**
         * Check for unknown files in core directories and sensitive WordPress locations
         * 
         * @param array $checksums WordPress core checksums
         * @return array List of unknown files found
         */
        public function check_for_unknown_files($checksums) {
            // Clear previous results
            $this->unknown_files = array();
            
            // Core directories to check (removing plugins directory to avoid false positives)
            $core_dirs = array(
                'wp-admin',
                'wp-includes'
            );
            
            $abspath = untrailingslashit(ABSPATH);
            
            // Check core directories
            foreach ($core_dirs as $dir) {
                $dir_path = $abspath . '/' . $dir;
                $this->scan_directory_for_unknown_files($dir_path, $dir, $checksums);
            }
            
            // Check must-use plugins directory
            $mu_plugins_dir = $abspath . '/wp-content/mu-plugins';
            if (is_dir($mu_plugins_dir)) {
                $this->check_mu_plugins($mu_plugins_dir);
            }
            
            // Check wp-content drop-ins
            $this->check_wp_content_dropins($abspath . '/wp-content');
            
            return $this->unknown_files;
        }
        
        /**
         * Check must-use plugins directory for suspicious files
         * 
         * @param string $mu_plugins_dir Path to mu-plugins directory
         */
        private function check_mu_plugins($mu_plugins_dir) {
            if (!is_dir($mu_plugins_dir)) {
                return;
            }
            
            $files = scandir($mu_plugins_dir);
            
            foreach ($files as $file) {
                // Skip . and ..
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $file_path = $mu_plugins_dir . '/' . $file;
                $relative_file_path = 'wp-content/mu-plugins/' . $file;
                
                // Check if it's a PHP file
                if (substr(strtolower($file), -4) === '.php') {
                    // For mu-plugins, we'll flag them but not necessarily as malicious
                    // as they could be legitimate for the site's functionality
                    $this->unknown_files[] = $relative_file_path . ' (MUST-USE PLUGIN - REVIEW CAREFULLY)';
                } elseif (is_dir($file_path)) {
                    // Scan subdirectories in mu-plugins
                    $sub_files = scandir($file_path);
                    
                    foreach ($sub_files as $sub_file) {
                        if ($sub_file === '.' || $sub_file === '..') {
                            continue;
                        }
                        
                        if (substr(strtolower($sub_file), -4) === '.php') {
                            $relative_sub_file = 'wp-content/mu-plugins/' . $file . '/' . $sub_file;
                            $this->unknown_files[] = $relative_sub_file . ' (MUST-USE PLUGIN - REVIEW CAREFULLY)';
                        }
                    }
                }
            }
        }
        
        /**
         * Check wp-content directory for drop-ins
         * 
         * @param string $wp_content_dir Path to wp-content directory
         */
        private function check_wp_content_dropins($wp_content_dir) {
            if (!is_dir($wp_content_dir)) {
                return;
            }
            
            $files = scandir($wp_content_dir);
            
            foreach ($files as $file) {
                // Skip directories and non-PHP files
                if (is_dir($wp_content_dir . '/' . $file) || substr(strtolower($file), -4) !== '.php') {
                    continue;
                }
                
                // Skip index.php as it's common and legitimate
                if ($file === 'index.php') {
                    continue;
                }
                
                // If file is not in allowed drop-ins list, flag it
                if (!in_array($file, $this->allowed_dropins)) {
                    $this->unknown_files[] = 'wp-content/' . $file . ' (UNEXPECTED DROP-IN FILE - REVIEW CAREFULLY)';
                } else {
                    // Known drop-in, but still flag it for review
                    $this->unknown_files[] = 'wp-content/' . $file . ' (KNOWN DROP-IN - VERIFY LEGITIMACY)';
                }
            }
        }
        
        /**
         * Scan a directory recursively for unknown files
         * 
         * @param string $dir_path Absolute path to directory
         * @param string $relative_path Relative path to WordPress root
         * @param array $checksums WordPress core checksums
         */
        private function scan_directory_for_unknown_files($dir_path, $relative_path, $checksums) {
            if (!is_dir($dir_path)) {
                return;
            }
            
            $files = scandir($dir_path);
            
            foreach ($files as $file) {
                // Skip . and ..
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $file_path = $dir_path . '/' . $file;
                $relative_file_path = $relative_path . '/' . $file;
                
                if (is_dir($file_path)) {
                    // Recursively scan subdirectories
                    $this->scan_directory_for_unknown_files($file_path, $relative_file_path, $checksums);
                } else {
                    // Check if this file is in the checksums list
                    if (!isset($checksums[$relative_file_path]) && !$this->is_expected_generated_file($relative_file_path)) {
                        $this->unknown_files[] = $relative_file_path;
                    }
                }
            }
        }
        
        /**
         * Check if a file is an expected generated file (that wouldn't be in the checksums)
         * 
         * @param string $file Relative file path
         * @return bool True if file is an expected generated file
         */
        private function is_expected_generated_file($file) {
            // List of patterns for files that are dynamically generated and wouldn't be in checksums
            $expected_patterns = array(
                '#/wp-admin/css/colors/.+/colors\.css$#',
                '#/wp-admin/css/colors/.+/colors-rtl\.css$#',
                '#/wp-admin/css/colors/.+/colors\.min\.css$#',
                '#/wp-admin/css/colors/.+/colors-rtl\.min\.css$#'
            );
            
            // Skip Akismet and Hello Dolly as most users ignore or delete them
            if (strpos($file, '/wp-content/plugins/akismet/') === 0 || 
                strpos($file, '/wp-content/plugins/hello.php') === 0) {
                return true;
            }
            
            foreach ($expected_patterns as $pattern) {
                if (preg_match($pattern, $file)) {
                    return true;
                }
            }
            
            return false;
        }
    }
}