(function ($) {
    'use strict';

    $(document).ready(function () {
        // console.log('[SettingsJS] Document Ready');

        // Check for the hidden toast trigger notice using its likely ID
        // The ID is typically 'setting-error-{code}' based on add_settings_error code.
        const triggerId = '#setting-error-cpt_features_saved';
        const $toastTrigger = $(triggerId);

        // console.log(`[SettingsJS] Searching for trigger element: ${triggerId}`);
        // console.log(`[SettingsJS] Found ${$toastTrigger.length} trigger element(s).`);

        if ($toastTrigger.length > 0) {
            // console.log('[SettingsJS] Trigger element FOUND. Type:', typeof window.showCPTToast);
            // Check if the showCPTToast function exists before calling it
            if (typeof window.showCPTToast === 'function') {
                // console.log('[SettingsJS] Calling showCPTToast now...');
                window.showCPTToast('Settings saved successfully.', 'success');
                // console.log('[SettingsJS] showCPTToast call completed.');
            } else {
                // console.error('[SettingsJS] Error: showCPTToast function NOT found!');
            }
            // Remove the trigger element so it doesn't fire again on refresh
            // console.log('[SettingsJS] Removing trigger element:', triggerId);
            $toastTrigger.remove();
        } else {
            // console.log('[SettingsJS] Trigger element NOT found.');
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
    });

})(jQuery); 