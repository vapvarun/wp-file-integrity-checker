/**
 * Enhanced progress tracking for WP File Integrity Checker
 * Adds a visual progress bar with file counting and status updates
 */

(function($) {
    'use strict';

    // Progress tracking object
    var WP_File_Integrity_Progress = {
        progressId: null,
        updateInterval: null,
        isComplete: false,
        
        /**
         * Initialize the progress bar
         */
        init: function() {
            // Check if we're on the scan page
            if ($('form[name="wp_file_integrity_run_check"]').length === 0) {
                return;
            }
            
            this.setupScanButton();
        },
        
        /**
         * Setup the scan button click handler
         */
        setupScanButton: function() {
            var self = this;
            
            $('form[name="wp_file_integrity_run_check"]').on('submit', function(e) {
                var $form = $(this);
                var $submitButton = $form.find('input[type="submit"]');
                
                // Disable submit button
                $submitButton.prop('disabled', true);
                
                // Create progress container
                var $progressContainer = $('<div>')
                    .addClass('wp-file-integrity-progress')
                    .html(
                        '<h3>' + 
                            'Scanning Files ' +
                            '<span class="spinner scan-spinner is-active"></span>' + 
                        '</h3>' +
                        '<div class="progress-bar-container">' +
                            '<div class="progress-bar"></div>' +
                        '</div>' +
                        '<div class="progress-info">' +
                            '<span class="progress-percentage">0%</span>' +
                            '<span class="progress-details">Initializing scan...</span>' +
                            '<span class="progress-step">Preparing file list</span>' +
                        '</div>'
                    );
                
                // Add progress container after the form
                $form.after($progressContainer);
                
                // Start progress tracking after a short delay to allow the form submission
                setTimeout(function() {
                    self.startProgressTracking();
                }, 1000);
            });
        },
        
        /**
         * Start tracking progress
         */
        startProgressTracking: function() {
            var self = this;
            
            // Generate a unique progress ID
            this.progressId = 'wp_file_integrity_progress_' + Math.random().toString(36).substr(2, 9);
            
            // Store progress ID in data attribute
            $('.wp-file-integrity-progress').attr('data-progress-id', this.progressId);
            
            // Start checking for progress updates
            this.updateInterval = setInterval(function() {
                self.updateProgress();
            }, 1500);
        },
        
        /**
         * Update progress display
         */
        updateProgress: function() {
            var self = this;
            
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'wp_file_integrity_get_progress',
                    progress_id: this.progressId,
                    nonce: wpFileIntegrityData.progressNonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateProgressDisplay(response.data);
                    }
                },
                error: function() {
                    // On error, slow down polling
                    clearInterval(self.updateInterval);
                    self.updateInterval = setInterval(function() {
                        self.updateProgress();
                    }, 5000);
                }
            });
        },
        
        /**
         * Update the progress display with the latest data
         * 
         * @param {Object} data Progress data from server
         */
        updateProgressDisplay: function(data) {
            var $progress = $('.wp-file-integrity-progress');
            var $bar = $progress.find('.progress-bar');
            var $percentage = $progress.find('.progress-percentage');
            var $details = $progress.find('.progress-details');
            var $step = $progress.find('.progress-step');
            
            // Update percentage
            $bar.css('width', data.percentage + '%');
            $percentage.text(data.percentage + '%');
            
            // Update details
            if (data.total_files > 0) {
                var countText = data.processed_files + ' / ' + data.total_files + ' files';
                $details.html(countText);
                
                // Add file counter
                if (!$details.find('.file-counter').length) {
                    $details.html(countText + ' <span class="file-counter">' + 
                                   data.processed_files + '</span>');
                } else {
                    $details.find('.file-counter').text(data.processed_files);
                }
            }
            
            // Update step description
            $step.text(data.current_step);
            
            // Check if scan is complete
            if (data.percentage >= 100 && !this.isComplete) {
                this.completeScan();
            }
        },
        
        /**
         * Handle scan completion
         */
        completeScan: function() {
            // Mark as complete
            this.isComplete = true;
            
            // Stop progress updates
            clearInterval(this.updateInterval);
            
            var $progress = $('.wp-file-integrity-progress');
            
            // Update UI to show completion
            $progress.addClass('complete');
            $progress.find('.progress-percentage').text('100%');
            $progress.find('.spinner').removeClass('is-active');
            $progress.find('.progress-step').text('Scan complete! Loading results...');
            
            // Add completion message
            $progress.append('<div class="notice notice-success inline"><p>File integrity check completed successfully. Results will appear below shortly.</p></div>');
            
            // Refresh the page after a delay to show results
            setTimeout(function() {
                window.location.reload();
            }, 3000);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        WP_File_Integrity_Progress.init();
    });
    
})(jQuery);