/**
 * JavaScript for WP File Integrity Checker admin interface
 *
 * Handles UI interactions, file list filtering, and result display
 */

(function($) {
    'use strict';

    // Initialize once the DOM is fully loaded
    $(document).ready(function() {
        // Initialize the file integrity admin functionality
        WP_File_Integrity_Admin.init();
    });

    // Main admin object
    var WP_File_Integrity_Admin = {
        
        /**
         * Initialize the admin functionality
         */
        init: function() {
            this.setupTabs();
            this.setupFileLists();
            this.setupScanningProgress();
            this.setupResultsFilter();
        },
        
        /**
         * Set up tab navigation
         */
        setupTabs: function() {
            // Handle tab navigation
            $('.nav-tab').on('click', function(e) {
                if ($(this).attr('href').indexOf('tab=') !== -1) {
                    return; // Let the normal link work
                }
                
                e.preventDefault();
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show/hide the correct tab content
                var tabId = $(this).attr('href').replace('#', '');
                $('.tab-content').hide();
                $('#' + tabId).show();
            });
        },
        
        /**
         * Set up expandable file lists
         */
        setupFileLists: function() {
            // Make file lists collapsible
            $('.wp-file-integrity-file-list').each(function() {
                var $list = $(this);
                var $header = $list.prev('h3');
                
                // If there are more than 10 items, only show first 10 and add "show more" button
                if ($list.find('li').length > 10) {
                    var $items = $list.find('li');
                    $items.slice(10).hide();
                    
                    var $showMore = $('<button>')
                        .text('Show More')
                        .addClass('button')
                        .on('click', function() {
                            $items.slice(10).toggle();
                            $(this).text(
                                $(this).text() === 'Show More' ? 'Show Less' : 'Show More'
                            );
                        });
                    
                    $list.after($showMore);
                }
                
                // Add counts to headers with file type
                if ($header.length) {
                    var count = $list.find('li').length;
                    $header.append(' <span class="count">(' + count + ')</span>');
                }
            });
        },
        
        /**
         * Set up scanning progress indicator
         */
        setupScanningProgress: function() {
            // Show progress indicator when scanning starts
            $('form[name="wp_file_integrity_run_check"]').on('submit', function() {
                // Create progress overlay
                var $form = $(this);
                var $submitButton = $form.find('input[type="submit"]');
                
                // Disable submit button and display spinner
                $submitButton.prop('disabled', true).after(
                    $('<span>').addClass('spinner is-active').css({
                        'float': 'none',
                        'margin-top': '0',
                        'margin-left': '10px'
                    })
                );
                
                // Display progress message
                $form.after(
                    $('<div>')
                        .addClass('notice notice-info')
                        .html('<p>Scanning files, please wait. This may take a few minutes for large sites...</p>')
                );
            });
        },
        
        /**
         * Set up results filtering options
         */
        setupResultsFilter: function() {
            if ($('.wp-file-integrity-file-list').length === 0) {
                return; // No results to filter
            }
            
            // Create filter interface
            var $filterContainer = $('<div>')
                .addClass('wp-file-integrity-filter')
                .html(
                    '<label>' +
                    'Filter results: ' +
                    '<input type="text" placeholder="Enter search term..." class="regular-text">' +
                    '</label>'
                );
            
            // Add filter before first results list
            $('.wp-file-integrity-file-list').first().before($filterContainer);
            
            // Add filter functionality
            var $filterInput = $filterContainer.find('input');
            $filterInput.on('keyup', function() {
                var searchTerm = $(this).val().toLowerCase();
                
                if (searchTerm.length < 2) {
                    // If less than 2 characters, show all items
                    $('.wp-file-integrity-file-list li').show();
                    return;
                }
                
                // Filter list items
                $('.wp-file-integrity-file-list li').each(function() {
                    var itemText = $(this).text().toLowerCase();
                    $(this).toggle(itemText.indexOf(searchTerm) !== -1);
                });
                
                // Update counts
                $('.wp-file-integrity-file-list').each(function() {
                    var $list = $(this);
                    var $header = $list.prev('h3');
                    var $count = $header.find('.count');
                    
                    if ($count.length) {
                        var visibleCount = $list.find('li:visible').length;
                        var totalCount = $list.find('li').length;
                        $count.text('(' + visibleCount + ' of ' + totalCount + ')');
                    }
                });
            });
        }
    };

})(jQuery);