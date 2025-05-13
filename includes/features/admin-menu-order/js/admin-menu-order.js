/**
 * Admin Menu Order JavaScript
 * Handles drag & drop functionality and saving menu order
 */
(function($) {
    'use strict';

    // Check dependencies
    if (typeof Sortable === 'undefined') {
        console.error('Admin Menu Order: SortableJS library not loaded.');
        return;
    }

    $(document).ready(function() {
        const $menuList = $('#sortable-menu-list');
        const $error = $('#menu-order-error');
        const $saveButton = $('#save_menu_order');
        
        if (!$menuList.length) {
            console.warn('Admin Menu Order: Menu list container not found.');
            return;
        }

        // Initialize Sortable
        const sortable = new Sortable($menuList[0], {
            animation: 150,
            ghostClass: 'menu-order-ghost',
            chosenClass: 'menu-order-chosen',
            dragClass: 'menu-order-drag',
            onEnd: function(evt) {
                // Enable save button when order changes
                $saveButton.prop('disabled', false);
            }
        });

        // Handle save button click
        $saveButton.on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.val();
            
            // Get the current order of menu items
            const menuOrder = Array.from($menuList.find('.menu-order-item')).map(
                item => $(item).data('slug')
            );

            // Disable button and show saving state
            $button.val('Saving...').addClass('saving').prop('disabled', true);
            $error.hide();

            // Send AJAX request
            $.ajax({
                url: cptMenuOrderVars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpt_save_menu_order',
                    nonce: cptMenuOrderVars.nonce,
                    menu_order: JSON.stringify(menuOrder)
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message using the existing toast system
                        if (typeof cptkShowToast === 'function') {
                            cptkShowToast(response.data.message, 'success');
                        }
                        
                        // Reload the page after a brief delay to show the new order
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        $error.html(response.data).show();
                        $button.val(originalText).removeClass('saving').prop('disabled', false);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $error.html('Error saving menu order: ' + errorThrown).show();
                    $button.val(originalText).removeClass('saving').prop('disabled', false);
                }
            });
        });

        // Initially disable save button until changes are made
        $saveButton.prop('disabled', true);
    });
})(jQuery);