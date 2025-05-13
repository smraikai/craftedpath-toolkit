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

    // Auto Sort with AI functionality
    $('#auto-sort-btn').on('click', function (e) {
        e.preventDefault();
        const button = $(this);
        const originalText = button.html();
        const $status = $('#menu_order_status');
        const $error = $('#menu_order_error');
        const $loading = $('#menu_order_loading');

        // Confirm action
        if (!confirm('Would you like to automatically sort your menu items using AI? This will use the OpenAI API.')) {
            return;
        }

        // Hide any previous messages and show loading
        $status.hide();
        $error.hide();
        $loading.show();

        // Disable button to prevent multiple requests
        button.prop('disabled', true);
        $('#cpt-menu-order-save-btn').prop('disabled', true);

        // Send AJAX request to the auto sort endpoint
        $.ajax({
            url: cptAdminMenuOrder.ajaxurl,
            type: 'POST',
            data: {
                action: 'auto_sort_menu',
                nonce: cptAdminMenuOrder.nonce
            },
            success: function (response) {
                $loading.hide();

                if (response.success && response.data && response.data.sorted_menu) {
                    $status.html(response.data.message).show();

                    // Reorder the menu items according to the AI suggestion
                    reorderMenuItems(response.data.sorted_menu);

                    // Enable save button since order has changed
                    $('#cpt-menu-order-save-btn').prop('disabled', false);

                    setTimeout(function () {
                        $status.fadeOut();
                    }, 5000);
                } else {
                    // Show error message with details
                    const errorMsg = response.data || 'Error auto-sorting menu';
                    $error.html('Error: ' + errorMsg).show();
                }

                // Re-enable button
                button.prop('disabled', false);
                button.html(originalText);
            },
            error: function (xhr, status, error) {
                $loading.hide();
                console.error('Ajax error:', status, error);
                $error.html('Network Error: ' + error).show();
                button.prop('disabled', false);
                button.html(originalText);
            }
        });
    });

    /**
     * Reorder menu items based on the sorted array from AI
     */
    function reorderMenuItems(sortedMenu) {
        const $list = $('.cpt-menu-order-list');
        const $items = $list.children();

        // Create a map of menu items by ID for quick lookup
        const itemMap = {};
        $items.each(function () {
            const id = $(this).data('menu-id');
            itemMap[id] = $(this);
        });

        // Detach all items from the list
        $items.detach();

        // Add items back in the sorted order
        sortedMenu.forEach(function (id) {
            if (itemMap[id]) {
                $list.append(itemMap[id]);
                // Add a flash effect to show the item has moved
                itemMap[id].addClass('cpt-menu-highlight');
                setTimeout(function () {
                    itemMap[id].removeClass('cpt-menu-highlight');
                }, 1500);
            }
        });

        // Add any remaining items that weren't in the sorted list
        $items.each(function () {
            const id = $(this).data('menu-id');
            if (sortedMenu.indexOf(id) === -1) {
                $list.append($(this));
            }
        });
    }
});
