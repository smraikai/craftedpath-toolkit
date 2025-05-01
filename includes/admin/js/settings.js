(function ($) {
    'use strict';

    $(document).ready(function () {
        console.log('Settings.js loaded - document ready');

        // Look for toast trigger in multiple ways
        // 1. Check for the standard error ID
        const triggerId = '#setting-error-cpt_features_saved';
        const $toastTrigger = $(triggerId);

        // 2. Also check for our container class
        const $toastContainer = $('.cpt-toast-trigger-area');

        console.log('Toast trigger lookup results:');
        console.log('- By ID:', $toastTrigger.length);
        console.log('- By class:', $toastContainer.length);

        // Dump the DOM IDs in the body for debugging
        console.log('All notices on page:');
        $('.notice').each(function () {
            console.log('Notice found:', this.id, $(this).attr('class'));
        });

        if ($toastTrigger.length > 0) {
            console.log('Toast trigger element found by ID');
            showToastFromElement($toastTrigger);
        } else if ($toastContainer.length > 0) {
            console.log('Toast trigger element found by container');
            const $notice = $toastContainer.find('.notice');
            if ($notice.length > 0) {
                showToastFromElement($notice);
            }
        } else {
            console.log('No toast trigger elements found');
        }

        // Function to show toast from an element
        function showToastFromElement($element) {
            // Get the message from the element (for future use)
            const message = $element.find('p').text() || 'Settings saved successfully.';

            if (typeof window.showCPTToast === 'function') {
                console.log('Calling showCPTToast with message:', message);
                window.showCPTToast(message, 'success');
            } else {
                console.error('showCPTToast function not found!');
            }

            // Remove the trigger element
            $element.remove();
        }

        /* // Old URL parameter check (keep commented out for now)
        const urlParams = new URLSearchParams(window.location.search);
        console.log("Checking URL params:", window.location.search); 
        if (urlParams.has('settings-updated') && urlParams.get('settings-updated') === 'true') {
            console.log("'settings-updated=true' detected!");
            if (typeof window.showCPTToast === 'function') {
                 console.log("Calling showCPTToast for save notification.");
                 window.showCPTToast('Settings saved successfully.', 'success');
            } else {
                console.error('CraftedPath Toolkit: showCPTToast function not found when trying to show save notification.');
            }
        } else {
            console.log("'settings-updated=true' NOT detected.");
        }
        */

        // Initialize the settings page UI
        // console.log('[SettingsJS] Initializing settings page UI...');
        const initSettingsPage = function () {
            // Toggle switch effects
            $('.craftedpath-toggle input[type="checkbox"]').on('change', function () {
                const $toggle = $(this).closest('.craftedpath-toggle');
                const $label = $(this).closest('tr').find('.craftedpath-feature-description');

                // Add animation
                $toggle.addClass('toggled');

                // Update status text
                if ($(this).is(':checked')) {
                    $label.append('<span class="status-change"> — Enabled</span>');
                } else {
                    $label.append('<span class="status-change"> — Disabled</span>');
                }

                // Remove animation and status text after delay
                setTimeout(function () {
                    $toggle.removeClass('toggled');
                    $('.status-change').fadeOut(function () {
                        $(this).remove();
                    });
                }, 1500);
            });

            // Handle form submission with animation
            $('.craftedpath-card form').on('submit', function (e) {
                const $submitButton = $(this).find('.button-primary');

                // Save the original text
                const originalText = $submitButton.val();

                // Show saving indicator
                $submitButton.val('Saving...')
                    .addClass('saving')
                    .prop('disabled', true);

                // Check if any features are being disabled
                const disabledFeatures = [];
                $('.craftedpath-toggle input[type="checkbox"]').each(function () {
                    const featureName = $(this).closest('tr').find('th label').text().trim();
                    if ($(this).prop('checked') === false) {
                        disabledFeatures.push(featureName);
                    }
                });

                // If critical features are disabled, confirm
                if (disabledFeatures.length > 0) {
                    if (!confirm('You are about to disable these features:\n\n' +
                        disabledFeatures.join('\n') +
                        '\n\nAre you sure you want to continue?')) {
                        // Reset button if user cancels
                        $submitButton.val(originalText)
                            .removeClass('saving')
                            .prop('disabled', false);
                        return false;
                    }
                }

                return true;
            });

            // Handle notices
            const $notices = $('.notice');
            if ($notices.length) {
                $notices.each(function () {
                    // Add a close button
                    $(this).append('<button type="button" class="notice-dismiss"></button>');
                });

                // Fade in notices
                $notices.hide().fadeIn(400);

                // Handle dismiss button
                $(document).on('click', '.notice-dismiss', function () {
                    $(this).closest('.notice').slideUp(200);
                });

                // Auto-dismiss success notices
                setTimeout(function () {
                    $('.notice-success, .updated').slideUp(300);
                }, 3000);
            }
        };

        // Initialize
        initSettingsPage();

        // --- Accordion Logic --- 
        const initAccordion = function () {
            const $accordion = $('.cpt-features-accordion');
            if (!$accordion.length) {
                // console.log('No accordion found.');
                return;
            }

            // console.log('Initializing accordion...');

            $accordion.on('click', '.cpt-feature-accordion-trigger', function (e) {
                e.preventDefault(); // Prevent default button behavior
                const $header = $(this).parent(); // Get the h3 element
                const $section = $header.closest('.cpt-feature-accordion-section');
                const $content = $section.find('.cpt-feature-accordion-content');
                const isOpen = $section.hasClass('is-open');

                // console.log(`Accordion header clicked: ${$header.attr('id')}, Currently open: ${isOpen}`);

                // Close all other sections (optional, for single-open accordion)
                // $accordion.find('.cpt-feature-accordion-section.is-open').not($section).each(function() {
                //     $(this).removeClass('is-open');
                //     $(this).find('.cpt-feature-accordion-content').slideUp(200);
                //     $(this).find('.cpt-feature-accordion-header').attr('aria-expanded', 'false');
                //     $(this).find('.cpt-feature-accordion-content').prop('hidden', true);
                // });

                // Toggle the clicked section
                if (isOpen) {
                    $content.slideUp(200, function () {
                        $section.removeClass('is-open');
                        $header.attr('aria-expanded', 'false');
                        $content.prop('hidden', true);
                    });
                } else {
                    $section.addClass('is-open');
                    $header.attr('aria-expanded', 'true');
                    $content.prop('hidden', false);
                    $content.slideDown(200);
                }
            });
        };

        // Initialize Accordion
        initAccordion();

        // --- Expand/Collapse All Logic ---
        const $accordionContainer = $('.cpt-features-accordion');

        $('.cpt-expand-all').on('click', function () {
            // console.log('Expand All clicked');
            $accordionContainer.find('.cpt-feature-accordion-section:not(.is-open)').each(function () {
                const $section = $(this);
                const $header = $section.find('.cpt-feature-accordion-header');
                const $content = $section.find('.cpt-feature-accordion-content');

                $section.addClass('is-open');
                $header.attr('aria-expanded', 'true');
                $content.prop('hidden', false);
                $content.slideDown(200);
            });
        });

        $('.cpt-collapse-all').on('click', function () {
            // console.log('Collapse All clicked');
            $accordionContainer.find('.cpt-feature-accordion-section.is-open').each(function () {
                const $section = $(this);
                const $header = $section.find('.cpt-feature-accordion-header');
                const $content = $section.find('.cpt-feature-accordion-content');

                $content.slideUp(200, function () {
                    $section.removeClass('is-open');
                    $header.attr('aria-expanded', 'false');
                    $content.prop('hidden', true);
                });
            });
        });

    });

})(jQuery); 