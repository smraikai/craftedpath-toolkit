/**
 * AI Auto Categorize Feature JS
 *
 * Handles the button click and AJAX request for auto-categorization.
 */
(function ($) {
    'use strict';

    $(document).ready(function () {

        // Check if localization object exists
        if (typeof cptAutoCategorize === 'undefined') {
            console.error('Auto Categorize localization object not found.');
            return;
        }

        const $button = $('#cptk-auto-categorize-button');
        const $spinner = $('#cptk-auto-categorize-controls .spinner');
        const $status = $('#cptk-auto-categorize-status');

        if (!$button.length) {
            // Button doesn't exist, maybe Gutenberg context? Exit gracefully.
            console.log('Auto Categorize button not found (maybe Gutenberg screen?)');
            return;
        }

        // Set initial button text
        $button.text(cptAutoCategorize.i18n.buttonText);

        $button.on('click', function () {
            // Disable button, show spinner, clear status
            $button.prop('disabled', true).text(cptAutoCategorize.i18n.loadingText);
            $spinner.addClass('is-active');
            $status.text('').removeClass('notice notice-success notice-error');

            // Prepare AJAX data
            const data = {
                action: 'cptk_auto_categorize_post', // Matches WP AJAX hook
                nonce: cptAutoCategorize.nonce,
                post_id: cptAutoCategorize.postId
            };

            // Make the AJAX call
            $.post(cptAutoCategorize.ajax_url, data)
                .done(function (response) {
                    if (response.success) {
                        $status.text(response.data.message).addClass('notice notice-success');
                        // TODO: Update the category checklist UI dynamically
                        console.log('Success:', response.data);
                        // Example: Find the category checkbox and check it
                        if (response.data.category_id) {
                            const categoryCheckbox = $(`#in-category-${response.data.category_id}`);
                            if (categoryCheckbox.length) {
                                // Uncheck others first if replacing
                                $('.categorychecklist input[type="checkbox"]').prop('checked', false);
                                categoryCheckbox.prop('checked', true);
                            } else {
                                // Category might be new, WP might refresh the box on save,
                                // or we might need to dynamically add it (more complex).
                                // For now, just showing the message is the primary feedback.
                                console.warn(`Category checkbox for ID ${response.data.category_id} not found.`);
                                // We might need to trigger a refresh of the category meta box.
                                // For Gutenberg, a dispatch action would be needed.
                            }
                        }
                    } else {
                        const errorMessage = response.data && response.data.message ? response.data.message : cptAutoCategorize.i18n.genericError;
                        $status.text(`${cptAutoCategorize.i18n.errorPrefix} ${errorMessage}`).addClass('notice notice-error');
                        console.error('Error:', response.data);
                    }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    $status.text(`${cptAutoCategorize.i18n.errorPrefix} ${cptAutoCategorize.i18n.genericError} (${textStatus})`).addClass('notice notice-error');
                    console.error('AJAX Fail:', textStatus, errorThrown);
                })
                .always(function () {
                    // Re-enable button, hide spinner, restore original text
                    $button.prop('disabled', false).text(cptAutoCategorize.i18n.buttonText);
                    $spinner.removeClass('is-active');
                });
        });

    }); // End document ready

})(jQuery); // End closure 