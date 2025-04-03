<?php
/**
 * WordPress File Integrity Admin Progress Handler
 * 
 * This component handles the progress tracking and display during file scanning
 */

if (!class_exists('WP_File_Integrity_Admin_Progress')) {

    class WP_File_Integrity_Admin_Progress {
        
        // Progress data
        private $total_files = 0;
        private $processed_files = 0;
        private $current_step = '';
        private $progress_id = '';
        
        /**
         * Constructor
         */
        public function __construct() {
            // Generate unique progress ID for this scan
            $this->progress_id = 'wp_file_integrity_progress_' . uniqid();
            
            // Register AJAX handlers for progress updates
            add_action('wp_ajax_wp_file_integrity_update_progress', array($this, 'handle_progress_update'));
            add_action('wp_ajax_wp_file_integrity_get_progress', array($this, 'handle_get_progress'));
        }
        
        /**
         * Initialize progress tracking for a scan
         * 
         * @param int $total_files Total number of files to scan
         */
        public function init_progress($total_files) {
            $this->total_files = $total_files;
            $this->processed_files = 0;
            $this->current_step = 'Starting scan...';
            
            // Store progress data
            $this->update_progress_data();
        }
        
        /**
         * Update scan progress
         * 
         * @param int $processed_files Number of files processed so far
         * @param string $current_step Current scan step description
         */
        public function update_progress($processed_files, $current_step) {
            $this->processed_files = $processed_files;
            $this->current_step = $current_step;
            
            // Store progress data
            $this->update_progress_data();
        }
        
        /**
         * Store progress data in transient
         */
        private function update_progress_data() {
            $progress_data = array(
                'total_files' => $this->total_files,
                'processed_files' => $this->processed_files,
                'current_step' => $this->current_step,
                'percentage' => $this->total_files > 0 ? round(($this->processed_files / $this->total_files) * 100) : 0,
                'timestamp' => time()
            );
            
            set_transient($this->progress_id, $progress_data, HOUR_IN_SECONDS);
        }
        
        /**
         * Get progress data
         * 
         * @return array Current progress data
         */
        public function get_progress_data() {
            $progress_data = get_transient($this->progress_id);
            
            if (!$progress_data) {
                return array(
                    'total_files' => 0,
                    'processed_files' => 0,
                    'current_step' => 'Unknown',
                    'percentage' => 0,
                    'timestamp' => time()
                );
            }
            
            return $progress_data;
        }
        
        /**
         * Complete the progress tracking
         */
        public function complete_progress() {
            $this->processed_files = $this->total_files;
            $this->current_step = 'Scan complete';
            
            // Update one last time
            $this->update_progress_data();
        }
        
        /**
         * Get the progress ID
         * 
         * @return string Progress ID
         */
        public function get_progress_id() {
            return $this->progress_id;
        }
        
        /**
         * Generate HTML for progress bar
         * 
         * @return string HTML for progress bar
         */
        public function get_progress_bar_html() {
            $progress_data = $this->get_progress_data();
            $percentage = $progress_data['percentage'];
            
            $html = '<div class="wp-file-integrity-progress" data-progress-id="' . esc_attr($this->progress_id) . '">';
            $html .= '<div class="progress-bar-container">';
            $html .= '<div class="progress-bar" style="width: ' . esc_attr($percentage) . '%"></div>';
            $html .= '</div>';
            $html .= '<div class="progress-info">';
            $html .= '<span class="progress-percentage">' . esc_html($percentage) . '%</span>';
            $html .= '<span class="progress-details">' . esc_html($progress_data['processed_files']) . ' / ' . esc_html($progress_data['total_files']) . ' files</span>';
            $html .= '<span class="progress-step">' . esc_html($progress_data['current_step']) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
            
            return $html;
        }
        
        /**
         * Handle AJAX progress update request
         */
        public function handle_progress_update() {
            check_ajax_referer('wp_file_integrity_progress_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission denied');
            }
            
            $progress_id = isset($_POST['progress_id']) ? sanitize_text_field($_POST['progress_id']) : '';
            $processed_files = isset($_POST['processed_files']) ? intval($_POST['processed_files']) : 0;
            $current_step = isset($_POST['current_step']) ? sanitize_text_field($_POST['current_step']) : '';
            
            if (empty($progress_id)) {
                wp_send_json_error('Invalid progress ID');
            }
            
            // Set the progress ID
            $this->progress_id = $progress_id;
            
            // Update progress
            $this->update_progress($processed_files, $current_step);
            
            wp_send_json_success();
        }
        
        /**
         * Handle AJAX get progress request
         */
        public function handle_get_progress() {
            check_ajax_referer('wp_file_integrity_progress_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission denied');
            }
            
            $progress_id = isset($_GET['progress_id']) ? sanitize_text_field($_GET['progress_id']) : '';
            
            if (empty($progress_id)) {
                wp_send_json_error('Invalid progress ID');
            }
            
            // Set the progress ID
            $this->progress_id = $progress_id;
            
            // Get progress data
            $progress_data = $this->get_progress_data();
            
            wp_send_json_success($progress_data);
        }
        
        /**
         * Add progress JavaScript to admin footer
         */
        public function add_progress_scripts() {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                var progressId = $('.wp-file-integrity-progress').data('progress-id');
                if (!progressId) return;
                
                var progressUpdateInterval;
                
                function updateProgressBar() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'GET',
                        data: {
                            action: 'wp_file_integrity_get_progress',
                            progress_id: progressId,
                            nonce: '<?php echo wp_create_nonce('wp_file_integrity_progress_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var data = response.data;
                                $('.wp-file-integrity-progress .progress-bar').css('width', data.percentage + '%');
                                $('.wp-file-integrity-progress .progress-percentage').text(data.percentage + '%');
                                $('.wp-file-integrity-progress .progress-details').text(data.processed_files + ' / ' + data.total_files + ' files');
                                $('.wp-file-integrity-progress .progress-step').text(data.current_step);
                                
                                // Stop updating if scan is complete
                                if (data.percentage >= 100) {
                                    clearInterval(progressUpdateInterval);
                                }
                            }
                        }
                    });
                }
                
                // Update progress every 2 seconds
                progressUpdateInterval = setInterval(updateProgressBar, 2000);
            });
            </script>
            <?php
        }
    }
}