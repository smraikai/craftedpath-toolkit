jQuery(document).ready(function ($) {
    console.log('Admin Menu Order JS initialized');

    // Get the menu list element and save button
    const $menuList = $('.cpt-menu-order-list');
    const $saveBtn = $('#cpt-menu-order-save-btn');
    const $loading = $('#menu_order_loading');

    console.log('Menu list found:', $menuList.length > 0);
    console.log('Save button found:', $saveBtn.length > 0);

    // Check for saved success message in sessionStorage
    const savedStatus = sessionStorage.getItem('cpt_menu_order_saved');
    if (savedStatus) {
        // Show toast with the saved message
        showCPTToast(savedStatus, 'success');
        // Remove the flag to prevent showing on future page loads
        sessionStorage.removeItem('cpt_menu_order_saved');
    }

    // Initialize Sortable
    const sortable = new Sortable($menuList[0], {
        animation: 150,
        ghostClass: 'cpt-menu-order-placeholder',
        dataIdAttr: 'data-menu-id', // Use data-menu-id attribute for IDs
        onEnd: function (evt) {
            console.log('Sortable onEnd event fired');
            console.log('New index:', evt.newIndex);
            console.log('Old index:', evt.oldIndex);
            // Enable save button when order changes
            $saveBtn.prop('disabled', false);
        }
    });

    console.log('Sortable initialized');

    // Log initial menu items
    const initialMenuItems = Array.from($menuList[0].children).map(item => {
        return {
            id: item.getAttribute('data-menu-id'),
            text: item.querySelector('.menu-title').textContent.trim()
        };
    });
    console.log('Initial menu items:', initialMenuItems);

    // Add Spacer button functionality
    $('#add-spacer-btn').on('click', function () {
        console.log('Add spacer button clicked');

        // Create a unique ID for the spacer
        const timestamp = new Date().getTime();
        const spacerId = `cpt-spacer-${timestamp}`;

        // Create the spacer element
        const $spacer = $(`<li class="cpt-menu-order-item cpt-menu-spacer" data-menu-id="${spacerId}" data-id="${spacerId}">
            <span class="dashicons dashicons-menu"></span>
            <span class="menu-title">Spacer</span>
            <button type="button" class="cpt-remove-spacer" title="Remove Spacer">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </li>`);

        // Add the spacer to the end of the list
        $menuList.append($spacer);

        // Highlight the new spacer
        $spacer.addClass('cpt-menu-highlight');
        setTimeout(() => {
            $spacer.removeClass('cpt-menu-highlight');
        }, 1500);

        // Enable save button since order has changed
        $saveBtn.prop('disabled', false);
    });

    // Remove Spacer button functionality (delegated event for dynamically added elements)
    $menuList.on('click', '.cpt-remove-spacer', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $spacer = $(this).closest('li');
        const spacerId = $spacer.attr('data-menu-id');

        console.log(`Removing spacer: ${spacerId}`);

        // Fade out and remove the spacer
        $spacer.fadeOut(300, function () {
            $spacer.remove();
            // Enable save button since order has changed
            $saveBtn.prop('disabled', false);
        });
    });

    // Save menu order with improved error handling and user feedback
    $saveBtn.on('click', function (e) {
        console.log('Save button clicked');
        e.preventDefault();
        const button = $(this);
        const originalText = button.val();

        // Get menu order from sortable elements
        const menuOrder = [];
        Array.from($menuList[0].children).forEach(item => {
            const id = item.getAttribute('data-menu-id');
            const text = item.querySelector('.menu-title').textContent.trim();
            menuOrder.push(id);
            console.log(`Menu item: ${text} (${id})`);
        });

        // Log the menu order for debugging
        console.log('Menu order to save:', menuOrder);

        // Show loading state
        button.prop('disabled', true).val('Saving...');

        // Send AJAX request
        console.log('Sending AJAX request to save menu order');
        $.ajax({
            url: cptAdminMenuOrder.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_admin_menu_order',
                menu_order: menuOrder,
                nonce: cptAdminMenuOrder.nonce
            },
            success: function (response) {
                console.log('AJAX success response:', response);
                if (response.success) {
                    // Store success message in sessionStorage
                    sessionStorage.setItem('cpt_menu_order_saved', 'Menu order saved successfully!');
                    // Refresh the page immediately
                    window.location.reload();
                } else {
                    // Show error toast with details
                    const errorMsg = response.data || 'Error saving menu order';
                    console.error('Error from server:', errorMsg);
                    showCPTToast('Error: ' + errorMsg, 'error');
                    button.val(originalText).prop('disabled', false);
                }
            },
            error: function (xhr, status, error) {
                // Handle network or server errors
                console.error('AJAX error:', status, error);
                console.error('Response text:', xhr.responseText);
                showCPTToast('Network Error: ' + error, 'error');
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
        console.log('Auto sort button clicked');
        e.preventDefault();
        const button = $(this);
        const originalText = button.html();

        // Confirm action
        if (!confirm('Would you like to automatically sort your menu items using AI? This will use the OpenAI API.')) {
            console.log('Auto sort cancelled by user');
            return;
        }

        // Show loading
        $loading.show();

        // Disable button to prevent multiple requests
        button.prop('disabled', true);
        $saveBtn.prop('disabled', true);

        // Send AJAX request to the auto sort endpoint
        console.log('Sending AJAX request for AI sorting');
        $.ajax({
            url: cptAdminMenuOrder.ajaxurl,
            type: 'POST',
            data: {
                action: 'auto_sort_menu',
                nonce: cptAdminMenuOrder.nonce
            },
            success: function (response) {
                console.log('AI sort AJAX success response:', response);
                $loading.hide();

                if (response.success && response.data && response.data.sorted_menu) {
                    // Show success toast
                    showCPTToast(response.data.message, 'success');

                    // Reorder the menu items according to the AI suggestion
                    reorderMenuItems(response.data.sorted_menu);

                    // Enable save button since order has changed
                    $saveBtn.prop('disabled', false);
                } else {
                    // Show error toast with details
                    const errorMsg = response.data || 'Error auto-sorting menu';
                    console.error('Error from server:', errorMsg);
                    showCPTToast('Error: ' + errorMsg, 'error');
                }

                // Re-enable button
                button.prop('disabled', false);
                button.html(originalText);
            },
            error: function (xhr, status, error) {
                console.error('AI sort AJAX error:', status, error);
                console.error('Response text:', xhr.responseText);
                $loading.hide();
                showCPTToast('Network Error: ' + error, 'error');
                button.prop('disabled', false);
                button.html(originalText);
            }
        });
    });

    /**
     * Reorder menu items based on the sorted array from AI
     */
    function reorderMenuItems(sortedMenu) {
        console.log('Reordering with:', sortedMenu);

        if (!Array.isArray(sortedMenu) || sortedMenu.length === 0) {
            console.error('Invalid sorted menu data received');
            showCPTToast('Error: Invalid menu data received from AI', 'error');
            return;
        }

        // Create a map of items by ID for quick lookup
        const itemMap = {};
        const items = sortable.el.children;

        Array.from(items).forEach(item => {
            const id = item.getAttribute('data-menu-id');
            itemMap[id] = item;
            console.log(`Mapping item: ${id}`);
        });

        // Keep track of found items
        const foundItems = [];
        const notFoundItems = [];

        // Check which items can be found
        sortedMenu.forEach(id => {
            if (itemMap[id]) {
                foundItems.push(id);
            } else {
                notFoundItems.push(id);
                console.warn(`Item not found for ID: ${id}`);
            }
        });

        console.log('Found items:', foundItems);
        console.log('Not found items:', notFoundItems);

        // Only proceed if we have items to reorder
        if (foundItems.length === 0) {
            console.error('None of the sorted menu items were found in the DOM');
            showCPTToast('Error: Unable to match sorted items', 'error');
            return;
        }

        // Remove all existing items
        console.log('Removing existing items');
        while (sortable.el.firstChild) {
            sortable.el.removeChild(sortable.el.firstChild);
        }

        // Add items back in the sorted order
        console.log('Adding items in sorted order');
        foundItems.forEach(id => {
            console.log(`Appending item: ${id}`);
            sortable.el.appendChild(itemMap[id]);
            // Add highlight effect
            itemMap[id].classList.add('cpt-menu-highlight');
            setTimeout(() => {
                itemMap[id].classList.remove('cpt-menu-highlight');
            }, 1500);
        });

        // Add any remaining items that weren't in the sorted list
        Object.keys(itemMap).forEach(id => {
            if (!foundItems.includes(id)) {
                console.log(`Appending remaining item: ${id}`);
                sortable.el.appendChild(itemMap[id]);
            }
        });

        // Log the final order
        const finalOrder = Array.from(sortable.el.children).map(item => {
            return item.getAttribute('data-menu-id');
        });
        console.log('Final order after reordering:', finalOrder);

        // Make sure to trigger the save button to be enabled
        $saveBtn.prop('disabled', false);
    }
});
