(function ($) {
    'use strict';

    $(document).ready(function () {
        // Initialize the settings page UI
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