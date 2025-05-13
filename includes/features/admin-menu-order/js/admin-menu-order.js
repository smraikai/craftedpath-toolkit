jQuery(document).ready(function ($) {
    // Initialize sortable with improved configuration
    $('.cpt-menu-order-list').sortable({
        placeholder: 'cpt-menu-order-placeholder',
        handle: '.dashicons-menu',
        opacity: 0.8,
        cursor: 'move',
        axis: 'y',
        update: function (event, ui) {
            // Enable save button when order changes
            $('#cpt-menu-order-save-btn').prop('disabled', false);
        }
    }).disableSelection(); // Prevent text selection while dragging

    // Save menu order with improved error handling and user feedback
    $('#cpt-menu-order-save-btn').on('click', function (e) {
        e.preventDefault();
        const button = $(this);
        const originalText = button.val();
        const $status = $('#menu_order_status');
        const $error = $('#menu_order_error');

        // Get menu order
        const menuOrder = $('.cpt-menu-order-list').children().map(function () {
            return $(this).data('menu-id');
        }).get();

        // Hide any previous messages
        $status.hide();
        $error.hide();

        // Show loading state
        button.prop('disabled', true).val('Saving...');

        // Send AJAX request
        $.ajax({
            url: cptAdminMenuOrder.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_admin_menu_order',
                menu_order: menuOrder,
                nonce: cptAdminMenuOrder.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Show success message
                    $status.html('Menu order saved successfully!').show();
                    button.val('Saved!');

                    setTimeout(function () {
                        button.val(originalText);
                        $status.fadeOut();
                    }, 3000);
                } else {
                    // Show error message with details if available
                    const errorMsg = response.data || 'Error saving menu order';
                    $error.html('Error: ' + errorMsg).show();
                    button.val(originalText).prop('disabled', false);
                }
            },
            error: function (xhr, status, error) {
                // Handle network or server errors
                console.error('Ajax error:', status, error);
                $error.html('Network Error: ' + error).show();
                button.val(originalText).prop('disabled', false);
            }
        });
    });

    // Add visual feedback for draggable items
    $('.cpt-menu-order-item').hover(
        function () {
            $(this).addClass('cpt-menu-order-item-hover');
        },
        function () {
            $(this).removeClass('cpt-menu-order-item-hover');
        }
    );
});
