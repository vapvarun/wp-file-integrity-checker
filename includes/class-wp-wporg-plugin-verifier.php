<?php
/**
 * WordPress.org Plugin File Verifier
 * 
 * This component checks suspicious plugin files against the WordPress.org SVN repository.
 * It verifies if plugin files match their original versions from WordPress.org.
 * Now with integrated progress tracking.
 */

if (!class_exists('WP_WPOrg_Plugin_Verifier')) {

    class WP_WPOrg_Plugin_Verifier {
        
        // Store verification results
        private $verification_results = array();
        private $plugins_cache = array();
        private $processed_plugins = array();
        
        // Progress tracker
        private $progress_tracker = null;
        private $processed_files = 0;
        private $total_files = 0;
        
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
         * Verify a list of suspicious files against WordPress.org
         * 
         * @param array $suspicious_files List of suspicious file paths
         * @return array Verification results
         */
        public function verify_plugin_files($suspicious_files) {
            // Reset results
            $this->verification_results = array(
                'verified' => array(),
                'modified' => array(),
                'not_in_wporg' => array(),
                'errors' => array()
            );
            
            $this->processed_plugins = array();
            
            // Setup progress tracking
            $this->total_files = count($suspicious_files);
            $this->processed_files = 0;
            
            foreach ($suspicious_files as $file_path) {
                // Extract clean path (remove any annotations)
                $clean_path = preg_replace('/ \(.*\)$/', '', $file_path);
                
                // Skip non-plugin files
                if (strpos($clean_path, 'wp-content/plugins/') !== 0) {
                    $this->processed_files++;
                    continue;
                }
                
                // Update progress if tracker is available
                if ($this->progress_tracker && $this->processed_files % 5 === 0) {
                    $this->progress_tracker->update_progress(
                        $this->processed_files,
                        sprintf(__('Verifying plugin file: %s', 'wp-file-integrity-checker'), basename($clean_path))
                    );
                }
                
                // Extract plugin slug from path
                if (preg_match('#wp-content/plugins/([^/]+)/#', $clean_path, $matches)) {
                    $plugin_slug = $matches[1];
                    
                    // Skip if we've already processed this plugin
                    if (in_array($plugin_slug, $this->processed_plugins)) {
                        $this->processed_files++;
                        continue;
                    }
                    
                    // Check if plugin exists on WordPress.org
                    if ($this->plugin_exists_on_wporg($plugin_slug)) {
                        // Only verify files if the plugin is on WordPress.org
                        $this->verify_file_against_svn($plugin_slug, $clean_path);
                    } else {
                        // Just record the plugin slug for plugins not on WordPress.org
                        if (!$this->is_plugin_in_results($plugin_slug)) {
                            $plugin_name = $this->get_plugin_name($plugin_slug);
                            $this->verification_results['not_in_wporg'][] = array(
                                'path' => '', // No specific file path
                                'plugin' => $plugin_slug,
                                'name' => $plugin_name
                            );
                            
                            // Add to processed plugins list
                            $this->processed_plugins[] = $plugin_slug;
                        }
                    }
                }
                
                $this->processed_files++;
            }
            
            // Update progress to complete if progress tracker is available
            if ($this->progress_tracker) {
                $this->progress_tracker->update_progress(
                    $this->total_files,
                    __('Verification complete', 'wp-file-integrity-checker')
                );
            }
            
            return $this->verification_results;
        }
        
        /**
         * Check if a plugin is already in the results
         *
         * @param string $plugin_slug Plugin slug to check
         * @return bool True if plugin is already in results
         */
        private function is_plugin_in_results($plugin_slug) {
            foreach ($this->verification_results['not_in_wporg'] as $item) {
                if ($item['plugin'] === $plugin_slug) {
                    return true;
                }
            }
            return false;
        }
        
        /**
         * Get the plugin name from its slug
         *
         * @param string $plugin_slug Plugin slug
         * @return string Plugin name or slug if name can't be determined
         */
        private function get_plugin_name($plugin_slug) {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            $plugins = get_plugins();
            $plugin_path = $plugin_slug . '/' . $plugin_slug . '.php';
            $alt_plugin_path = $plugin_slug . '/index.php';
            
            // Check for main plugin file with same name as directory
            if (isset($plugins[$plugin_path])) {
                return $plugins[$plugin_path]['Name'];
            }
            
            // Check for index.php as main file
            if (isset($plugins[$alt_plugin_path])) {
                return $plugins[$alt_plugin_path]['Name'];
            }
            
            // Look for any file in this plugin directory
            foreach ($plugins as $path => $data) {
                if (strpos($path, $plugin_slug . '/') === 0) {
                    return $data['Name'];
                }
            }
            
            // Return the slug if we can't find the name
            return ucwords(str_replace('-', ' ', $plugin_slug)) . ' (Premium/Custom)';
        }
        
        /**
         * Check if a plugin exists on WordPress.org
         * 
         * @param string $plugin_slug Plugin directory name
         * @return bool True if plugin exists on WordPress.org
         */
        private function plugin_exists_on_wporg($plugin_slug) {
            // Check cache first
            if (isset($this->plugins_cache[$plugin_slug])) {
                return $this->plugins_cache[$plugin_slug];
            }
            
            // Update progress if progress tracker is available
            if ($this->progress_tracker) {
                $this->progress_tracker->update_progress(
                    $this->processed_files,
                    sprintf(__('Checking if %s exists on WordPress.org...', 'wp-file-integrity-checker'), $plugin_slug)
                );
            }
            
            // Use WordPress.org API to check if plugin exists
            $response = wp_remote_head("https://plugins.svn.wordpress.org/$plugin_slug/trunk/");
            
            if (is_wp_error($response)) {
                $this->plugins_cache[$plugin_slug] = false;
                return false;
            }
            
            $status = wp_remote_retrieve_response_code($response);
            $exists = ($status === 200);
            
            // Cache the result
            $this->plugins_cache[$plugin_slug] = $exists;
            
            return $exists;
        }
        
        /**
         * Verify a file against the WordPress.org SVN repository
         * 
         * @param string $plugin_slug Plugin directory name
         * @param string $file_path Full path to the file
         */
        private function verify_file_against_svn($plugin_slug, $file_path) {
            // Get relative path within the plugin
            $relative_path = str_replace("wp-content/plugins/$plugin_slug/", '', $file_path);
            
            // Update progress if progress tracker is available
            if ($this->progress_tracker) {
                $this->progress_tracker->update_progress(
                    $this->processed_files,
                    sprintf(__('Verifying %s/%s against WordPress.org...', 'wp-file-integrity-checker'), 
                            $plugin_slug, $relative_path)
                );
            }
            
            // Get local file hash
            if (!file_exists(ABSPATH . $file_path)) {
                $this->verification_results['errors'][] = array(
                    'path' => $file_path,
                    'message' => 'File not found in local installation'
                );
                return;
            }
            
            $local_hash = md5_file(ABSPATH . $file_path);
            
            // Get SVN file hash
            $svn_url = "https://plugins.svn.wordpress.org/$plugin_slug/trunk/$relative_path";
            $response = wp_remote_get($svn_url);
            
            if (is_wp_error($response)) {
                $this->verification_results['errors'][] = array(
                    'path' => $file_path,
                    'message' => 'Error fetching SVN file: ' . $response->get_error_message()
                );
                return;
            }
            
            $status = wp_remote_retrieve_response_code($response);
            
            if ($status !== 200) {
                // Try checking stable tag
                $stable_tag = $this->get_plugin_stable_tag($plugin_slug);
                if ($stable_tag) {
                    $svn_url = "https://plugins.svn.wordpress.org/$plugin_slug/tags/$stable_tag/$relative_path";
                    $response = wp_remote_get($svn_url);
                    
                    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                        $this->verification_results['errors'][] = array(
                            'path' => $file_path,
                            'message' => 'File not found in SVN repository (trunk or stable tag)'
                        );
                        return;
                    }
                } else {
                    $this->verification_results['errors'][] = array(
                        'path' => $file_path,
                        'message' => 'File not found in SVN repository'
                    );
                    return;
                }
            }
            
            $svn_content = wp_remote_retrieve_body($response);
            $svn_hash = md5($svn_content);
            
            // Compare hashes
            if ($local_hash === $svn_hash) {
                $this->verification_results['verified'][] = array(
                    'path' => $file_path,
                    'plugin' => $plugin_slug,
                    'local_hash' => $local_hash,
                    'svn_hash' => $svn_hash,
                    'svn_url' => $svn_url
                );
            } else {
                $this->verification_results['modified'][] = array(
                    'path' => $file_path,
                    'plugin' => $plugin_slug,
                    'local_hash' => $local_hash,
                    'svn_hash' => $svn_hash,
                    'svn_url' => $svn_url
                );
            }
        }
        
        /**
         * Get the stable tag for a plugin
         * 
         * @param string $plugin_slug Plugin directory name
         * @return string|false Stable tag or false if not found
         */
        private function get_plugin_stable_tag($plugin_slug) {
            // Update progress if progress tracker is available
            if ($this->progress_tracker) {
                $this->progress_tracker->update_progress(
                    $this->processed_files,
                    sprintf(__('Getting stable version of %s...', 'wp-file-integrity-checker'), $plugin_slug)
                );
            }
            
            // Try to get plugin info from WordPress.org API
            $response = wp_remote_get("https://api.wordpress.org/plugins/info/1.0/$plugin_slug.json");
            
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return false;
            }
            
            $plugin_data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (empty($plugin_data) || !isset($plugin_data['version'])) {
                return false;
            }
            
            return $plugin_data['version'];
        }
    }
}