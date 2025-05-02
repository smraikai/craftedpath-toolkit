jQuery(document).ready(function ($) {
    // Use event delegation for dynamically loaded content (like grid view)
    $('body').on('click', '.cpt-generate-alt-button', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $listItem = $button.closest('tr'); // For list view
        if (!$listItem.length) {
            // Attempt to find parent for grid view (might need refinement)
            $listItem = $button.closest('.attachment');
        }
        var $statusSpinner = $button.next('.cpt-alt-status.spinner');
        var $altTextDisplay = $button.siblings('.cpt-alt-text-display');
        var attachmentId = $button.data('attachment-id');

        if ($button.is('.disabled')) {
            return; // Prevent multiple clicks while processing
        }

        // Disable button and show spinner
        $button.addClass('disabled').text(cptAiAltTextData.generating_text);
        $statusSpinner.css('display', 'inline-block'); // Show spinner

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
                    // Update the displayed alt text
                    $altTextDisplay.text(response.data.alt_text);
                    // Show success toast
                    if (window.cptShowToast) {
                        window.cptShowToast('Alt text generated successfully!', 'success');
                    } else {
                        console.log('Alt text generated: ', response.data.alt_text);
                    }
                } else {
                    // Show error toast
                    var errorMessage = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
                    if (window.cptShowToast) {
                        window.cptShowToast('Error: ' + errorMessage, 'error');
                    } else {
                        console.error('Error generating alt text: ', errorMessage);
                        alert('Error: ' + errorMessage);
                    }
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                // Show error toast for AJAX failure
                var errorMessage = 'AJAX Error: ' + textStatus + ' - ' + errorThrown;
                if (window.cptShowToast) {
                    window.cptShowToast(errorMessage, 'error');
                } else {
                    console.error(errorMessage);
                    alert(errorMessage);
                }
            },
            complete: function () {
                // Re-enable button and hide spinner
                $button.removeClass('disabled').text(cptAiAltTextData.generate_button_text);
                $statusSpinner.hide();
            }
        });
    });
}); 