<?php
/**
 * WordPress Theme File Handler
 * 
 * This file contains functionality to properly handle theme files during integrity checks.
 * Themes are often customized, so this component helps reduce false positives.
 */

if (!class_exists('WP_Theme_File_Handler')) {

    class WP_Theme_File_Handler {
        
        /**
         * Filter modified files to remove expected theme modifications
         * 
         * @param array $modified_files List of modified files
         * @return array Filtered list of modified files
         */
        public function filter_modified_files($modified_files) {
            return array_filter($modified_files, function($file) {
                // If it's a theme file, check if modification should be ignored
                if ($this->is_theme_file($file)) {
                    return !$this->ignore_theme_file_modification($file);
                }
                
                // For non-theme files, keep them in the list
                return true;
            });
        }
        
        /**
         * Check if a file is part of a WordPress theme
         * 
         * @param string $file Relative file path
         * @return boolean True if file is part of a theme
         */
        public function is_theme_file($file) {
            return (strpos($file, 'wp-content/themes/') === 0);
        }
        
        /**
         * Check if theme file modification should be ignored
         * 
         * @param string $file Relative file path
         * @return boolean True if theme file modification should be ignored
         */
        public function ignore_theme_file_modification($file) {
            // Common theme files that are often customized
            $customizable_files = array(
                'style.css',
                'rtl.css',
                'functions.php',
                'header.php',
                'footer.php',
                'sidebar.php',
                'sidebar-left.php',
                'sidebar-right.php',
                'comments.php',
                'single.php',
                'page.php',
                'archive.php',
                'search.php',
                'searchform.php',
                '404.php',
                'front-page.php',
                'home.php',
                'index.php',
                'content.php',
                'content-page.php',
                'content-single.php',
                'content-search.php',
                'content-none.php',
                'author.php',
                'category.php',
                'tag.php',
                'taxonomy.php',
                'date.php',
                'readme.txt',
                'screenshot.png',
                'custom.css',
                'custom.js',
                'editor-style.css',
                'theme.json',
                'custom-header.php',
                'custom-background.php'
            );
            
            // Extract filename from path
            $filename = basename($file);
            
            // Check if file is in the customizable list
            if (in_array($filename, $customizable_files)) {
                return true;
            }
            
            // Check for commonly customized directories
            $customized_dirs = array(
                '/css/',
                '/js/',
                '/custom/',
                '/assets/',
                '/img/',
                '/images/',
                '/fonts/',
                '/inc/',
                '/includes/',
                '/template-parts/',
                '/templates/',
                '/layouts/',
                '/customizer/',
                '/blocks/',
                '/patterns/'
            );
            
            // Check if file is in a customizable directory
            foreach ($customized_dirs as $dir) {
                if (strpos($file, $dir) !== false) {
                    return true;
                }
            }
            
            // Check for child theme assets (assume child themes are customized)
            if (preg_match('#wp-content/themes/[^/]+-child/#', $file)) {
                return true;
            }
            
            // Check for customized parent theme references in child themes
            if (strpos($file, '-parent/') !== false) {
                return true;
            }
            
            // Check for common WordPress theme frameworks
            $theme_frameworks = array(
                'twentytwenty',
                'twentytwentyone',
                'twentytwentytwo',
                'twentytwentythree',
                'twentytwentyfour',
                'twentytwentyfive',
                'astra',
                'elementor',
                'divi',
                'avada',
                'generatepress',
                'oceanwp',
                'underscores',
                'storefront',
                'flatsome',
                'kadence',
                'bricks',
                'beaver-builder',
                'buddyboss',
                'bootstrap',
                'genesis',
                'enfold'
            );
            
            foreach ($theme_frameworks as $framework) {
                if (strpos($file, $framework) !== false) {
                    return true;
                }
            }
            
            return false;
        }
        
        /**
         * Check if a theme file is critical for site functionality
         * 
         * @param string $file Relative file path
         * @return boolean True if file is critical
         */
        public function is_critical_theme_file($file) {
            $critical_files = array(
                'functions.php',
                'index.php',
                'style.css'
            );
            
            $filename = basename($file);
            
            return in_array($filename, $critical_files);
        }
        
        /**
         * Get theme information for a file
         * 
         * @param string $file Relative file path
         * @return array|false Theme info or false if not a theme file
         */
        public function get_theme_info($file) {
            if (!$this->is_theme_file($file)) {
                return false;
            }
            
            preg_match('#wp-content/themes/([^/]+)/#', $file, $matches);
            
            if (empty($matches[1])) {
                return false;
            }
            
            $theme_slug = $matches[1];
            $theme = wp_get_theme($theme_slug);
            
            if (!$theme->exists()) {
                return false;
            }
            
            return array(
                'slug' => $theme_slug,
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'author' => $theme->get('Author'),
                'is_child' => $theme->parent() !== false,
                'parent' => $theme->parent() ? $theme->parent()->get('Name') : null
            );
        }
    }
}