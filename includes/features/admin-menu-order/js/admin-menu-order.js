jQuery(document).ready(function ($) {
    // Get the menu list element and save button
    const $menuList = $('.cpt-menu-order-list');
    const $saveBtn = $('#cpt-menu-order-save-btn');
    const $status = $('#menu_order_status');
    const $error = $('#menu_order_error');
    const $loading = $('#menu_order_loading');

    // Initialize Sortable
    const sortable = new Sortable($menuList[0], {
        animation: 150,
        ghostClass: 'cpt-menu-order-placeholder',
        onEnd: function () {
            // Enable save button when order changes
            $saveBtn.prop('disabled', false);
        }
    });

    // Save menu order with improved error handling and user feedback
    $saveBtn.on('click', function (e) {
        e.preventDefault();
        const button = $(this);
        const originalText = button.val();

        // Get menu order from Sortable
        const menuOrder = sortable.toArray();

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
        $saveBtn.prop('disabled', true);

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
                    $saveBtn.prop('disabled', false);

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
        // Create a map of items by ID for quick lookup
        const itemMap = {};
        const items = sortable.el.children;

        Array.from(items).forEach(item => {
            const id = item.getAttribute('data-menu-id');
            itemMap[id] = item;
        });

        // Use the sortable API to reorder the items
        sortable.sort(sortedMenu);

        // Add highlight effect to all items to show they've moved
        Array.from(sortable.el.children).forEach(item => {
            item.classList.add('cpt-menu-highlight');
            setTimeout(() => {
                item.classList.remove('cpt-menu-highlight');
            }, 1500);
        });
    }

    // Initialize menu items with data-id attribute for Sortable
    $('.cpt-menu-order-item').each(function () {
        $(this).attr('data-id', $(this).data('menu-id'));
    });
});
