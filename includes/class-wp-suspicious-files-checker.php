<?php
/**
 * WordPress Suspicious Files Checker
 * 
 * This file contains the functionality to check for suspicious files in WordPress plugins directory.
 * This is separate from the unknown files checker to avoid flagging legitimate plugins as unknown.
 * Now with integrated progress tracking.
 */

if (!class_exists('WP_Suspicious_Files_Checker')) {

    class WP_Suspicious_Files_Checker {
        
        // Store suspicious files
        private $suspicious_files = array();
        
        // Progress tracking
        private $progress_tracker = null;
        private $files_scanned = 0;
        
        /**
         * Constructor
         */
        public function __construct() {
            // Try to get progress tracker instance from core
            global $wp_file_integrity_core;
            if (isset($wp_file_integrity_core) && 
                property_exists($wp_file_integrity_core, 'progress_tracker') &&
                $wp_file_integrity_core->progress_tracker) {
                $this->progress_tracker = $wp_file_integrity_core->progress_tracker;
            }
        }
        
        /**
         * Check for suspicious files in plugins directory and other sensitive locations
         * 
         * @return array List of suspicious files found
         */
        public function check_for_suspicious_files() {
            // Clear previous results
            $this->suspicious_files = array();
            $this->files_scanned = 0;
            
            // Get paths
            $abspath = untrailingslashit(ABSPATH);
            $plugins_dir = $abspath . '/wp-content/plugins';
            $mu_plugins_dir = $abspath . '/wp-content/mu-plugins';
            $wp_content_dir = $abspath . '/wp-content';
            
            // Update progress if available
            if ($this->progress_tracker) {
                $this->progress_tracker->update_progress(
                    $this->files_scanned,
                    __('Starting suspicious files scan...', 'wp-file-integrity-checker')
                );
            }
            
            // Scan plugins directory
            if (is_dir($plugins_dir)) {
                $this->scan_directory_for_suspicious_files($plugins_dir, 'wp-content/plugins');
            }
            
            // Update progress if available
            if ($this->progress_tracker) {
                $this->progress_tracker->update_progress(
                    $this->files_scanned,
                    __('Scanning must-use plugins...', 'wp-file-integrity-checker')
                );
            }
            
            // Scan mu-plugins directory more aggressively
            if (is_dir($mu_plugins_dir)) {
                $this->scan_directory_for_suspicious_mu_plugins($mu_plugins_dir, 'wp-content/mu-plugins');
            }
            
            // Update progress if available
            if ($this->progress_tracker) {
                $this->progress_tracker->update_progress(
                    $this->files_scanned,
                    __('Checking wp-content drop-ins...', 'wp-file-integrity-checker')
                );
            }
            
            // Check direct wp-content PHP files (drop-ins) more aggressively
            $this->scan_wp_content_for_suspicious_files($wp_content_dir);
            
            // Final progress update
            if ($this->progress_tracker) {
                $this->progress_tracker->update_progress(
                    $this->files_scanned,
                    __('Suspicious files scan complete', 'wp-file-integrity-checker')
                );
            }
            
            return $this->suspicious_files;
        }
        
        /**
         * Scan must-use plugins directory for suspicious files
         * 
         * @param string $dir_path Absolute path to directory
         * @param string $relative_path Relative path to WordPress root
         */
        private function scan_directory_for_suspicious_mu_plugins($dir_path, $relative_path) {
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
                
                // Update progress counter
                $this->files_scanned++;
                
                // Update progress every 10 files if progress tracker is available
                if ($this->progress_tracker && $this->files_scanned % 10 === 0) {
                    $this->progress_tracker->update_progress(
                        $this->files_scanned,
                        sprintf(__('Checking MU plugin: %s', 'wp-file-integrity-checker'), $relative_file_path)
                    );
                }
                
                if (is_dir($file_path)) {
                    // Recursively scan subdirectories
                    $this->scan_directory_for_suspicious_mu_plugins($file_path, $relative_file_path);
                } else {
                    // Check PHP files in mu-plugins more thoroughly
                    if (substr(strtolower($file), -4) === '.php') {
                        if ($this->is_suspicious_file($file, $file_path, true)) {
                            $this->suspicious_files[] = $relative_file_path . ' (MU-PLUGIN - HIGHER RISK)';
                        }
                    }
                }
            }
        }
        
        /**
         * Scan wp-content directory for suspicious drop-in files
         * 
         * @param string $wp_content_dir Path to wp-content directory
         */
        private function scan_wp_content_for_suspicious_files($wp_content_dir) {
            if (!is_dir($wp_content_dir)) {
                return;
            }
            
            // List of allowed WordPress drop-ins
            $allowed_dropins = array(
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
                'sunrise.php',
                'index.php'  // WordPress includes an index.php by default
            );
            
            $files = scandir($wp_content_dir);
            
            foreach ($files as $file) {
                // Skip directories and non-PHP files
                if (is_dir($wp_content_dir . '/' . $file) || substr(strtolower($file), -4) !== '.php') {
                    continue;
                }
                
                $file_path = $wp_content_dir . '/' . $file;
                $relative_file_path = 'wp-content/' . $file;
                
                // Update progress counter
                $this->files_scanned++;
                
                // Update progress if progress tracker is available
                if ($this->progress_tracker) {
                    $this->progress_tracker->update_progress(
                        $this->files_scanned,
                        sprintf(__('Checking wp-content file: %s', 'wp-file-integrity-checker'), $file)
                    );
                }
                
                // If file is not in allowed drop-ins list, check it thoroughly
                if (!in_array($file, $allowed_dropins)) {
                    $this->suspicious_files[] = $relative_file_path . ' (UNEXPECTED DROP-IN - HIGH RISK)';
                } else {
                    // Known drop-in, but still check for malicious code
                    if ($this->is_suspicious_file($file, $file_path, true)) {
                        $this->suspicious_files[] = $relative_file_path . ' (SUSPICIOUS DROP-IN)';
                    }
                }
            }
        }
        
        /**
         * Scan a directory recursively for suspicious files
         * 
         * @param string $dir_path Absolute path to directory
         * @param string $relative_path Relative path to WordPress root
         */
        private function scan_directory_for_suspicious_files($dir_path, $relative_path) {
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
                
                // Skip Akismet and Hello Dolly
                if (strpos($relative_file_path, 'wp-content/plugins/akismet/') === 0 || 
                    $relative_file_path === 'wp-content/plugins/hello.php') {
                    continue;
                }
                
                // Skip node_modules directories
                if (strpos($relative_file_path, 'node_modules') !== false) {
                    continue;
                }
                
                // Skip vendor directories as they contain third-party code
                if (strpos($relative_file_path, '/vendor/') !== false) {
                    continue;
                }
                
                // Update progress counter
                $this->files_scanned++;
                
                // Update progress every 50 files if progress tracker is available
                if ($this->progress_tracker && $this->files_scanned % 50 === 0) {
                    $plugin_name = '';
                    if (preg_match('#wp-content/plugins/([^/]+)/#', $relative_file_path, $matches)) {
                        $plugin_name = $matches[1];
                    }
                    
                    $this->progress_tracker->update_progress(
                        $this->files_scanned,
                        sprintf(__('Scanning plugin: %s', 'wp-file-integrity-checker'), $plugin_name ? $plugin_name : basename($relative_path))
                    );
                }
                
                // Skip common legitimate file types
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $safe_extensions = array('png', 'jpg', 'jpeg', 'gif', 'svg', 'css', 'js', 'map', 'json', 'txt', 'md', 'html');
                if (in_array($ext, $safe_extensions)) {
                    continue;
                }
                
                if (is_dir($file_path)) {
                    // Recursively scan subdirectories
                    $this->scan_directory_for_suspicious_files($file_path, $relative_file_path);
                } else {
                    // Check for suspicious files
                    if ($this->is_suspicious_file($file, $file_path, false)) {
                        $this->suspicious_files[] = $relative_file_path;
                    }
                }
            }
        }
        
        /**
         * Check if a file is suspicious based on extension and patterns
         * 
         * @param string $filename Filename to check
         * @param string $filepath Full path to the file
         * @param bool $deep_scan Whether to perform a deeper scan
         * @return bool True if the file is suspicious
         */
        private function is_suspicious_file($filename, $filepath, $deep_scan = false) {
            // Suspicious file extensions
            $suspicious_extensions = array(
                '.php.suspected',
                '.php.bak',
                '.php~',
                '.php.old',
                '.php.save',
                '.phtml',
                '.shtml',
                '.php.swp',
                '.suspected',
                '.bak',
                '.old',
                '.swp'
            );
            
            // Check for suspicious extensions
            foreach ($suspicious_extensions as $ext) {
                if (substr(strtolower($filename), -strlen($ext)) === $ext) {
                    return true;
                }
            }
            
            // These words in filenames are highly suspicious
            $highly_suspicious_names = array(
                'backdoor',
                'c99',
                'r57',
                'webshell',
                'rootkit',
                'malware',
                'trojan',
                'hacktools',
                'phising',
                'bypass',
                'exploit',
                'shell'
            );
            
            // Convert filename to lowercase for case-insensitive check
            $lower_filename = strtolower($filename);
            
            // Check for highly suspicious names
            foreach ($highly_suspicious_names as $name) {
                if (strpos($lower_filename, $name) !== false) {
                    return true;
                }
            }
            
            // Check PHP files for suspicious content
            if (substr($lower_filename, -4) === '.php') {
                
                // Deep scan checks all PHP files more thoroughly
                if ($deep_scan) {
                    $content = @file_get_contents($filepath);
                    if ($content !== false) {
                        return $this->check_for_malicious_code($content);
                    }
                }
                // Regular scan only checks suspicious files or small files
                else {
                    // Very small PHP files can be suspicious
                    $file_size = filesize($filepath);
                    if ($file_size !== false && $file_size < 100) { // Files under 100 bytes are suspicious
                        return true;
                    }
                    
                    // Check specifically named PHP files for suspicious content
                    if (strpos($lower_filename, 'shell') !== false || 
                        strpos($lower_filename, 'hack') !== false || 
                        strpos($lower_filename, 'upload') !== false ||
                        strpos($lower_filename, 'eval') !== false) {
                        
                        // Only check content of small files (under 100KB) to avoid performance issues
                        if ($file_size !== false && $file_size < 100 * 1024) {
                            $content = @file_get_contents($filepath);
                            if ($content !== false) {
                                return $this->check_for_malicious_code($content);
                            }
                        }
                    }
                }
            }
            
            return false;
        }
        
        /**
         * Check a file's content for malicious code patterns
         * 
         * @param string $content File content
         * @return bool True if malicious patterns found
         */
        private function check_for_malicious_code($content) {
            // Very specific malicious patterns
            $suspicious_patterns = array(
                // Base64 encoded payloads with $_REQUEST, $_POST, $_GET
                'base64_decode\s*\(\s*\$_(?:REQUEST|POST|GET|COOKIE)',
                // Eval on user input
                'eval\s*\(\s*\$_(?:REQUEST|POST|GET|COOKIE)',
                // System commands on user input
                'system\s*\(\s*\$_(?:REQUEST|POST|GET|COOKIE)',
                'shell_exec\s*\(\s*\$_(?:REQUEST|POST|GET|COOKIE)',
                'passthru\s*\(\s*\$_(?:REQUEST|POST|GET|COOKIE)',
                'exec\s*\(\s*\$_(?:REQUEST|POST|GET|COOKIE)',
                // Preg_replace with /e modifier (code execution)
                'preg_replace\s*\(\s*([\'"]).*\/e\\1',
                // Dynamic function creation with user input
                'create_function\s*\(.*\$_(?:REQUEST|POST|GET|COOKIE)',
                // Variable function calls with user input
                '\$(?:\w+)\s*=\s*\$_(?:REQUEST|POST|GET|COOKIE).*\$\\1\s*\(',
                // Common obfuscation techniques
                'str_rot13\s*\(\s*base64_decode',
                'gzinflate\s*\(\s*base64_decode',
                // Hidden iframe injection
                '<iframe\s+style=[\'"]display:\s*none',
                // Common web shell patterns
                '(?:eval|assert|passthru|exec|include|system|shell_exec)\s*\(\s*\$\w+\s*\[\s*\d+\s*\]',
                // Command injection
                'echo\s+`',
                // Backdoor passwords
                '\$password\s*=\s*[\'"](?:admin|123456|password|hack|shell)',
                // Hidden admin creation
                'wp_insert_user\s*\(\s*array\s*\(',
                // Database manipulation to add admin
                'INSERT\s+INTO.*wp_users',
                // Common eval obfuscation
                '\$\w+=[\'"][a-zA-Z0-9+/]+[\'"]\s*;.*(?:eval|assert)',
                // Remote file inclusion 
                '(?:include|require)(?:_once)?\s*\(\s*[\'"]https?://'
            );
            
            foreach ($suspicious_patterns as $pattern) {
                if (@preg_match('#' . $pattern . '#i', $content)) {
                    return true;
                }
            }
            
            // Check for high entropy strings which could be obfuscated code
            if (strlen($content) > 200) {
                $sample = substr($content, 0, 1000); // Check first 1000 chars
                if ($this->has_high_entropy($sample) && 
                    (strpos($content, 'eval(') !== false || 
                     strpos($content, 'base64_decode') !== false)) {
                    return true;
                }
            }
            
            return false;
        }
        
        /**
         * Check if a string has high entropy (could be obfuscated code)
         * 
         * @param string $string String to check
         * @return bool True if high entropy detected
         */
        private function has_high_entropy($string) {
            $chars = count_chars($string, 1);
            $entropy = 0;
            $length = strlen($string);
            
            foreach ($chars as $char => $count) {
                $probability = $count / $length;
                $entropy -= $probability * log($probability, 2);
            }
            
            // High entropy value suggests possible obfuscation
            return $entropy > 5.7;
        }
    }
}