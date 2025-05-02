jQuery(document).ready(function ($) {
    // Selector for the generate button
    var buttonSelector = '.cpt-generate-alt-button';

    // Use event delegation
    $('body').on('click', buttonSelector, function (e) {
        e.preventDefault();

        var $button = $(this);
        var $container = $button.closest('.cpt-alt-text-container');
        var $statusSpinner = $container.find('.cpt-alt-status.spinner');
        var $altTextDisplay = $container.find('.cpt-alt-text-display');
        var attachmentId = $button.data('attachment-id');

        if ($button.is('.disabled')) {
            return; // Prevent multiple clicks
        }

        // Disable button and show spinner, update text
        $button.addClass('disabled').text(cptAiAltTextData.generating_text);
        $statusSpinner.css('display', 'inline-block');

        // AJAX call
        $.ajax({
            url: cptAiAltTextData.ajax_url,
            type: 'POST',
            data: {
                action: cptAiAltTextData.ajax_action,
                security: cptAiAltTextData.nonce,
                attachment_id: attachmentId
            },
            success: function (response) {
                if (response.success) {
                    $altTextDisplay.text(response.data.alt_text);
                    if (window.cptShowToast) {
                        window.cptShowToast('Alt text generated successfully!', 'success');
                    }
                } else {
                    var errorMessage = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
                    if (window.cptShowToast) {
                        window.cptShowToast('Error: ' + errorMessage, 'error');
                    }
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var errorMessage = 'AJAX Error: ' + textStatus + ' - ' + errorThrown;
                if (window.cptShowToast) {
                    window.cptShowToast(errorMessage, 'error');
                }
            },
            complete: function () {
                // Re-enable button and hide spinner, restore text
                $button.removeClass('disabled').text(cptAiAltTextData.generate_button_text);
                $statusSpinner.hide();
            }
        });
    });
}); 