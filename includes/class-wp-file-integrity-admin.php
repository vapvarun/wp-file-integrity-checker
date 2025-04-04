<?php
/**
 * Admin interface for WP File Integrity Checker
 * 
 * Handles settings, admin pages, and UI
 */

if (!class_exists('WP_File_Integrity_Admin')) {

    class WP_File_Integrity_Admin {
        
        private $core;
        
        /**
         * Constructor
         * 
         * @param WP_File_Integrity_Core $core Core functionality instance
         */
        public function __construct($core) {
            $this->core = $core;
            
            // Load stored results
            $this->core->load_stored_scan_results();
            
            // Add menu item
            add_action('admin_menu', array($this, 'add_admin_menu'));
            
            // Register settings
            add_action('admin_init', array($this, 'register_settings'));
        }
        
        /**
         * Register the administration menu
         */
        public function add_admin_menu() {
            add_management_page(
                __('WP File Integrity Checker', 'wp-file-integrity-checker'),
                __('File Integrity', 'wp-file-integrity-checker'),
                'manage_options',
                'wp-file-integrity-checker',
                array($this, 'display_admin_page')
            );
        }
        
        /**
         * Register plugin settings
         */
        public function register_settings() {
            register_setting('wp_file_integrity_checker_options', 'wp_file_integrity_checker_options');
            
            add_settings_section(
                'wp_file_integrity_checker_general',
                __('General Settings', 'wp-file-integrity-checker'),
                array($this, 'settings_section_callback'),
                'wp_file_integrity_checker'
            );
            
            add_settings_field(
                'enable_scheduled_checks',
                __('Enable Scheduled Checks', 'wp-file-integrity-checker'),
                array($this, 'enable_scheduled_checks_callback'),
                'wp_file_integrity_checker',
                'wp_file_integrity_checker_general'
            );
            
            add_settings_field(
                'check_frequency',
                __('Check Frequency', 'wp-file-integrity-checker'),
                array($this, 'check_frequency_callback'),
                'wp_file_integrity_checker',
                'wp_file_integrity_checker_general'
            );
            
            add_settings_field(
                'email_notifications',
                __('Email Notifications', 'wp-file-integrity-checker'),
                array($this, 'email_notifications_callback'),
                'wp_file_integrity_checker',
                'wp_file_integrity_checker_general'
            );
            
            add_settings_field(
                'notification_email',
                __('Notification Email', 'wp-file-integrity-checker'),
                array($this, 'notification_email_callback'),
                'wp_file_integrity_checker',
                'wp_file_integrity_checker_general'
            );
            
            add_settings_field(
                'verify_with_wporg',
                __('Verify Plugins with WordPress.org', 'wp-file-integrity-checker'),
                array($this, 'verify_with_wporg_callback'),
                'wp_file_integrity_checker',
                'wp_file_integrity_checker_general'
            );
        }
        
        /**
         * Settings section callback
         */
        public function settings_section_callback() {
            echo '<p>' . __('Configure the file integrity checker settings.', 'wp-file-integrity-checker') . '</p>';
        }
        
        /**
         * Enable scheduled checks field callback
         */
        public function enable_scheduled_checks_callback() {
            $options = get_option('wp_file_integrity_checker_options', array());
            $checked = isset($options['enable_scheduled_checks']) ? $options['enable_scheduled_checks'] : 0;
            
            echo '<input type="checkbox" id="enable_scheduled_checks" name="wp_file_integrity_checker_options[enable_scheduled_checks]" value="1" ' . checked(1, $checked, false) . '>';
            echo '<label for="enable_scheduled_checks">' . __('Run automated integrity checks.', 'wp-file-integrity-checker') . '</label>';
        }
        
        /**
         * Check frequency field callback
         */
        public function check_frequency_callback() {
            $options = get_option('wp_file_integrity_checker_options', array());
            $frequency = isset($options['check_frequency']) ? $options['check_frequency'] : 'daily';
            
            echo '<select id="check_frequency" name="wp_file_integrity_checker_options[check_frequency]">';
            echo '<option value="hourly" ' . selected('hourly', $frequency, false) . '>' . __('Hourly', 'wp-file-integrity-checker') . '</option>';
            echo '<option value="twicedaily" ' . selected('twicedaily', $frequency, false) . '>' . __('Twice Daily', 'wp-file-integrity-checker') . '</option>';
            echo '<option value="daily" ' . selected('daily', $frequency, false) . '>' . __('Daily', 'wp-file-integrity-checker') . '</option>';
            echo '<option value="weekly" ' . selected('weekly', $frequency, false) . '>' . __('Weekly', 'wp-file-integrity-checker') . '</option>';
            echo '</select>';
        }
        
        /**
         * Email notifications field callback
         */
        public function email_notifications_callback() {
            $options = get_option('wp_file_integrity_checker_options', array());
            $checked = isset($options['email_notifications']) ? $options['email_notifications'] : 0;
            
            echo '<input type="checkbox" id="email_notifications" name="wp_file_integrity_checker_options[email_notifications]" value="1" ' . checked(1, $checked, false) . '>';
            echo '<label for="email_notifications">' . __('Send email notifications when issues are found.', 'wp-file-integrity-checker') . '</label>';
        }
        
        /**
         * Notification email field callback
         */
        public function notification_email_callback() {
            $options = get_option('wp_file_integrity_checker_options', array());
            $email = isset($options['notification_email']) ? $options['notification_email'] : get_option('admin_email');
            
            echo '<input type="email" id="notification_email" name="wp_file_integrity_checker_options[notification_email]" value="' . esc_attr($email) . '" class="regular-text">';
        }
        
        /**
         * Verify with WordPress.org field callback
         */
        public function verify_with_wporg_callback() {
            $options = get_option('wp_file_integrity_checker_options', array());
            $checked = isset($options['verify_with_wporg']) ? $options['verify_with_wporg'] : 1;
            
            echo '<input type="checkbox" id="verify_with_wporg" name="wp_file_integrity_checker_options[verify_with_wporg]" value="1" ' . checked(1, $checked, false) . '>';
            echo '<label for="verify_with_wporg">' . __('Verify suspicious plugin files against WordPress.org repository. (Recommended)', 'wp-file-integrity-checker') . '</label>';
        }
        
        /**
         * Display the admin page
         */
        public function display_admin_page() {
            if (!current_user_can('manage_options')) {
                return;
            }
            
            if (isset($_POST['wp_file_integrity_run_check'])) {
                check_admin_referer('wp_file_integrity_run_check_nonce');
                
                // Check if WordPress.org verification is requested
                if (isset($_POST['verify_with_wporg']) && $_POST['verify_with_wporg'] == '1') {
                    $this->core->check_file_integrity_with_wporg_verification();
                } else {
                    $this->core->check_file_integrity();
                }
            }
            
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                
                <h2 class="nav-tab-wrapper">
                    <a href="?page=wp-file-integrity-checker" class="nav-tab <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'check') ? 'nav-tab-active' : ''; ?>">
                        <?php _e('File Integrity Check', 'wp-file-integrity-checker'); ?>
                    </a>
                    <a href="?page=wp-file-integrity-checker&tab=settings" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'settings') ? 'nav-tab-active' : ''; ?>">
                        <?php _e('Settings', 'wp-file-integrity-checker'); ?>
                    </a>
                </h2>
                
                <?php
                $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'check';
                
                if ($tab === 'settings') {
                    $this->render_settings_tab();
                } else {
                    $this->render_check_tab();
                }
                ?>
                
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('form[name="wp_file_integrity_run_check"]').on('submit', function() {
                        console.log('Form submitted');
                        
                        // Define scan steps
                        var scanSteps = [
                            'Fetching WordPress core file checksums',
                            'Scanning core files',
                            'Checking for unknown files in WordPress core',
                            'Checking for suspicious files in plugins',
                            'Processing theme files',
                            'Finalizing scan results'
                        ];
                        
                        // Create progress container with steps
                        var stepsHtml = '';
                        scanSteps.forEach(function(step, index) {
                            stepsHtml += '<div class="scan-step" id="scan-step-' + index + '">' +
                                '<span class="step-number">' + (index + 1) + '.</span> ' +
                                '<span class="step-name">' + step + '</span> ' +
                                '<span class="step-status pending">Pending</span>' +
                                '</div>';
                        });
                        
                        var $progressContainer = $('<div>')
                            .addClass('wp-file-integrity-progress')
                            .html(
                                '<h3>' + 
                                    'File Integrity Scan Progress ' +
                                    '<span class="spinner scan-spinner is-active"></span>' + 
                                '</h3>' +
                                '<div class="progress-bar-container">' +
                                    '<div class="progress-bar"></div>' +
                                '</div>' +
                                '<div class="progress-info">' +
                                    '<span class="progress-percentage">0%</span>' +
                                    '<span class="progress-details">Initializing scan...</span>' +
                                '</div>' +
                                '<div class="scan-steps">' + stepsHtml + '</div>'
                            );
                        
                        // Add progress container after the form
                        $(this).after($progressContainer);
                        
                        // Add styles for steps
                        $('<style>').text(`
                            .scan-steps {
                                margin-top: 15px;
                                border: 1px solid #e5e5e5;
                                padding: 10px 15px;
                                background: #fff;
                                border-radius: 3px;
                            }
                            .scan-step {
                                padding: 8px 0;
                                border-bottom: 1px solid #f0f0f0;
                                display: flex;
                                align-items: center;
                            }
                            .scan-step:last-child {
                                border-bottom: none;
                            }
                            .step-number {
                                font-weight: bold;
                                margin-right: 10px;
                                min-width: 25px;
                            }
                            .step-name {
                                flex-grow: 1;
                            }
                            .step-status {
                                padding: 3px 8px;
                                border-radius: 3px;
                                font-size: 12px;
                                font-weight: 500;
                            }
                            .step-status.pending {
                                background: #f0f0f1;
                                color: #50575e;
                            }
                            .step-status.in-progress {
                                background: #f0f6fc;
                                color: #2271b1;
                            }
                            .step-status.complete {
                                background: #edfaef;
                                color: #00a32a;
                            }
                            .step-status.error {
                                background: #fcf0f1;
                                color: #d63638;
                            }
                            .wp-file-integrity-progress.complete .scan-steps {
                                margin-bottom: 15px;
                            }
                        `).appendTo('head');
                        
                        // Generate a unique progress ID
                        var progressId = 'wp_file_integrity_progress_' + Math.random().toString(36).substr(2, 9);
                        
                        // Store progress ID in data attribute
                        $('.wp-file-integrity-progress').attr('data-progress-id', progressId);
                        
                        var currentStepIndex = -1;
                        
                        // Start checking for progress updates
                        var updateInterval = setInterval(function() {
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
                                        
                                        // Update progress bar
                                        $('.wp-file-integrity-progress .progress-bar').css('width', data.percentage + '%');
                                        $('.wp-file-integrity-progress .progress-percentage').text(data.percentage + '%');
                                        
                                        // Update file count
                                        if (data.total_files > 0) {
                                            var countText = data.processed_files + ' / ' + data.total_files + ' files processed';
                                            $('.progress-details').text(countText);
                                        }
                                        
                                        // Update steps based on current operation
                                        var newStepIndex = -1;
                                        var currentStep = data.current_step.toLowerCase();
                                        
                                        if (currentStep.includes('fetching') || currentStep.includes('checksums')) {
                                            newStepIndex = 0;
                                        } else if (currentStep.includes('scanning core') || currentStep.includes('core files')) {
                                            newStepIndex = 1;
                                        } else if (currentStep.includes('unknown files')) {
                                            newStepIndex = 2;
                                        } else if (currentStep.includes('suspicious') || currentStep.includes('plugin')) {
                                            newStepIndex = 3;
                                        } else if (currentStep.includes('theme')) {
                                            newStepIndex = 4;
                                        } else if (currentStep.includes('finalizing') || currentStep.includes('complete')) {
                                            newStepIndex = 5;
                                        }
                                        
                                        // Update step statuses
                                        if (newStepIndex > currentStepIndex && newStepIndex >= 0) {
                                            // Mark previous step as complete
                                            if (currentStepIndex >= 0) {
                                                $('#scan-step-' + currentStepIndex + ' .step-status')
                                                    .removeClass('in-progress')
                                                    .addClass('complete')
                                                    .text('Complete');
                                            }
                                            
                                            // Mark current step as in progress
                                            $('#scan-step-' + newStepIndex + ' .step-status')
                                                .removeClass('pending')
                                                .addClass('in-progress')
                                                .text('In Progress');
                                            
                                            currentStepIndex = newStepIndex;
                                        }
                                        
                                        // Check if scan is complete
                                        if (data.percentage >= 100) {
                                            clearInterval(updateInterval);
                                            
                                            // Mark all remaining steps as complete
                                            for (var i = 0; i <= 5; i++) {
                                                if ($('#scan-step-' + i + ' .step-status').hasClass('pending') || 
                                                    $('#scan-step-' + i + ' .step-status').hasClass('in-progress')) {
                                                    $('#scan-step-' + i + ' .step-status')
                                                        .removeClass('pending in-progress')
                                                        .addClass('complete')
                                                        .text('Complete');
                                                }
                                            }
                                            
                                            // Update UI to show completion
                                            $('.wp-file-integrity-progress').addClass('complete');
                                            $('.wp-file-integrity-progress .spinner').removeClass('is-active');
                                            
                                            // Add completion message
                                            $('.wp-file-integrity-progress').append(
                                                '<div class="notice notice-success inline">' +
                                                '<p>File integrity check completed successfully. Results will appear below shortly.</p>' +
                                                '</div>'
                                            );
                                            
                                            // Add a button to refresh the page instead of auto-refreshing
                                            $('.wp-file-integrity-progress').append(
                                                '<div class="notice notice-success inline">' +
                                                '<p>File integrity check completed successfully. <button type="button" class="button view-results">View Results</button></p>' +
                                                '</div>'
                                            );

                                            // Add click handler for the button
                                            $('.view-results').on('click', function() {
                                                window.location.reload();
                                            });
                                        }
                                    }
                                }
                            });
                        }, 1500);
                    });
                });
                </script>
            </div>
            <?php
        }
        
        /**
         * Render the settings tab
         */
        private function render_settings_tab() {
            ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_file_integrity_checker_options');
                do_settings_sections('wp_file_integrity_checker');
                submit_button();
                ?>
            </form>
            <?php
        }
        
        /**
         * Render the check tab
         */
        private function render_check_tab() {
            // Get stored results timestamp if available
            $stored_results = get_transient('wp_file_integrity_scan_results');
            $last_scan_time = $stored_results ? $stored_results['timestamp'] : false;
            
            // Get the verification setting
            $options = get_option('wp_file_integrity_checker_options', array());
            $verify_with_wporg = isset($options['verify_with_wporg']) ? $options['verify_with_wporg'] : 1;
            ?>
            <div class="card">
                <h2><?php _e('WordPress Core File Integrity Check', 'wp-file-integrity-checker'); ?></h2>
                <p><?php _e('This will compare your WordPress core files against the official WordPress repository to check for any unauthorized modifications.', 'wp-file-integrity-checker'); ?></p>
                
                <?php if ($last_scan_time): ?>
                    <p>
                        <?php printf(__('Last scan: %s', 'wp-file-integrity-checker'), 
                            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_scan_time)); ?>
                    </p>
                <?php endif; ?>
                
                <form method="post" action="" name="wp_file_integrity_run_check">
                    <?php wp_nonce_field('wp_file_integrity_run_check_nonce'); ?>
                    <p>
                        <label>
                            <input type="checkbox" name="verify_with_wporg" value="1" <?php checked(1, $verify_with_wporg); ?>>
                            <?php _e('Verify suspicious plugin files against WordPress.org repository', 'wp-file-integrity-checker'); ?>
                        </label>
                    </p>
                    <p>
                        <input type="submit" name="wp_file_integrity_run_check" class="button button-primary" value="<?php _e('Run File Integrity Check', 'wp-file-integrity-checker'); ?>">
                    </p>
                </form>
            </div>
            
            <?php if ($this->core->scan_results): ?>
                <div class="card">
                    <h2><?php _e('Scan Results', 'wp-file-integrity-checker'); ?></h2>
                    
                    <?php if (count($this->core->modified_files) + count($this->core->missing_files) + count($this->core->unknown_files) + count($this->core->suspicious_files) === 0): ?>
                        <div class="notice notice-success">
                            <p><?php _e('All WordPress core files are intact and unmodified.', 'wp-file-integrity-checker'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-error">
                            <p><?php _e('Issues were found with your WordPress core files.', 'wp-file-integrity-checker'); ?></p>
                        </div>
                        
                        <?php if (!empty($this->core->modified_files)): ?>
                            <h3><?php _e('Modified Files', 'wp-file-integrity-checker'); ?> (<?php echo count($this->core->modified_files); ?>)</h3>
                            <p><?php _e('These files have been modified from the original WordPress core files.', 'wp-file-integrity-checker'); ?></p>
                            <ul class="wp-file-integrity-file-list modified-files">
                                <?php foreach ($this->core->modified_files as $file): ?>
                                    <li><?php echo esc_html($file); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <?php if (!empty($this->core->missing_files)): ?>
                            <h3><?php _e('Missing Files', 'wp-file-integrity-checker'); ?> (<?php echo count($this->core->missing_files); ?>)</h3>
                            <p><?php _e('These files are missing from your WordPress installation.', 'wp-file-integrity-checker'); ?></p>
                            <ul class="wp-file-integrity-file-list missing-files">
                                <?php foreach ($this->core->missing_files as $file): ?>
                                    <li><?php echo esc_html($file); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <?php if (!empty($this->core->unknown_files)): ?>
                            <h3><?php _e('Unknown Files', 'wp-file-integrity-checker'); ?> (<?php echo count($this->core->unknown_files); ?>)</h3>
                            <p><?php _e('These files are not part of the WordPress core.', 'wp-file-integrity-checker'); ?></p>
                            <ul class="wp-file-integrity-file-list unknown-files">
                                <?php foreach ($this->core->unknown_files as $file): ?>
                                    <li><?php echo esc_html($file); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <?php if (!empty($this->core->suspicious_files)): ?>
                            <h3><?php _e('Suspicious Files', 'wp-file-integrity-checker'); ?> (<?php echo count($this->core->suspicious_files); ?>)</h3>
                            <p><?php _e('These files in your plugins directory have suspicious patterns that could indicate malware.', 'wp-file-integrity-checker'); ?></p>
                            <ul class="wp-file-integrity-file-list suspicious-files">
                                <?php foreach ($this->core->suspicious_files as $file): ?>
                                    <li><?php echo esc_html($file); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <?php $this->render_wporg_verification_results(); ?>
                
            <?php endif; ?>
            <?php
        }
        
        /**
         * Display WordPress.org verification results
         */
        private function render_wporg_verification_results() {
            if (empty($this->core->verified_files) && empty($this->core->modified_files_wporg) && empty($this->core->not_in_wporg_files)) {
                return;
            }
            ?>
            <div class="card">
                <h2><?php _e('WordPress.org Plugin Verification Results', 'wp-file-integrity-checker'); ?></h2>
                
                <?php if (!empty($this->core->verified_files)): ?>
                    <h3><?php _e('Verified Files', 'wp-file-integrity-checker'); ?> (<?php echo count($this->core->verified_files); ?>)</h3>
                    <p><?php _e('These suspicious files were verified against WordPress.org and match the original files.', 'wp-file-integrity-checker'); ?></p>
                    <ul class="wp-file-integrity-file-list verified-files">
                        <?php foreach ($this->core->verified_files as $file): ?>
                            <li>
                                <?php echo esc_html($file['path']); ?>
                                <a href="<?php echo esc_url($file['svn_url']); ?>" target="_blank"><?php _e('View Original', 'wp-file-integrity-checker'); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (!empty($this->core->modified_files_wporg)): ?>
                    <h3><?php _e('Modified Plugin Files', 'wp-file-integrity-checker'); ?> (<?php echo count($this->core->modified_files_wporg); ?>)</h3>
                    <p><?php _e('These files differ from the original files on WordPress.org.', 'wp-file-integrity-checker'); ?></p>
                    <ul class="wp-file-integrity-file-list modified-files-wporg">
                        <?php foreach ($this->core->modified_files_wporg as $file): ?>
                            <li>
                                <?php echo esc_html($file['path']); ?>
                                <a href="<?php echo esc_url($file['svn_url']); ?>" target="_blank"><?php _e('View Original', 'wp-file-integrity-checker'); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (!empty($this->core->not_in_wporg_files)): ?>
                    <h3><?php _e('Files Not in WordPress.org', 'wp-file-integrity-checker'); ?> (<?php echo count($this->core->not_in_wporg_files); ?>)</h3>
                    <p><?php _e('These suspicious files belong to plugins not available on WordPress.org.', 'wp-file-integrity-checker'); ?></p>
                    <ul class="wp-file-integrity-file-list not-in-wporg-files">
                        <?php foreach ($this->core->not_in_wporg_files as $file): ?>
                            <li><?php echo esc_html($file['path']); ?> (<?php echo esc_html($file['plugin']); ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php
        }
    }
}