/**
 * AI Menu Generator JavaScript
 * Handles AJAX requests and UI interactions for generating menus.
 */
(function ($) {
    'use strict';

    // Check if jQuery and the SortableJS jQuery wrapper are available
    if (typeof $ === 'undefined' || typeof $.fn.sortable === 'undefined') {
        console.error('jQuery and the SortableJS jQuery wrapper (jquery-sortable.js) are required for the AI Menu Generator');
        return;
    }

    // Check for localization variables
    if (typeof cptMenuVars === 'undefined') {
        console.error('Localization variables (cptMenuVars) are missing.');
        return;
    }

    // Store generated menu structure
    let generatedMenuStructure = null;
    // Store reference to generated sitemap from page generator (if available)
    // This relies on the page generator script running first if checkbox is used
    // let pageStructureData = null; // Potentially get from transient or previous run?

    // Init on document ready
    $(document).ready(function () {
        initMenuGenerator();
    });

    /**
     * Initialize the Menu Generator
     */
    function initMenuGenerator() {
        const $generateBtn = $('#cpt-generate-menu-btn'); // Use ID from PHP view
        const $createBtn = $('#create_wp_menu'); // Use ID from PHP view
        const $copyBtn = $('#copy_menu_json'); // Use ID from PHP view
        const $resultsContainer = $('#menu_results'); // Added for delegation
        const $menuStructureContainer = $('.menu-structure'); // Added for delegation

        if (!$generateBtn.length) return;

        $generateBtn.on('click', function (e) {
            e.preventDefault();
            generateMenu();
        });

        // Create WP Menu button handler
        $createBtn.on('click', function () {
            createWordPressMenu();
        });

        // Copy JSON button handler (replaces Export)
        $copyBtn.on('click', function () {
            copyMenuJson();
        });

        // Event delegation for potential future actions within the tree
        $menuStructureContainer.on('click', '.delete-menu-item', function () {
            $(this).closest('.menu-tree-item').remove();
            updateStructureFromUI(); // Update structure after removal
        });
        // $menuStructureContainer.on('click', '.edit-menu-item', function () { ... });
    }

    /**
     * Generate menu using AI
     */
    function generateMenu() {
        const $menuType = $('#menu_type');
        const $results = $('#menu_results');
        const $error = $('#menu_error'); // Use ID from PHP view
        const $loading = $('#menu_loading'); // Use ID from PHP view
        const $status = $('#menu_status'); // Use ID from PHP view

        // Reset UI
        $error.hide().empty();
        $status.hide().empty();
        $results.hide();
        $loading.show();
        generatedMenuStructure = null; // Reset stored data
        $('.menu-structure').empty(); // Clear old tree

        // Prepare data
        const data = {
            action: 'cpt_generate_menu',
            menu_type: $menuType.val(),
            nonce: cptMenuVars.nonce // Use correct nonce var
        };

        // Make AJAX request
        $.ajax({
            url: cptMenuVars.ajaxurl, // Use correct ajaxurl var
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (response) {
                $loading.hide();

                if (response.success && response.data.menu_structure && response.data.menu_structure.items) {
                    generatedMenuStructure = response.data.menu_structure; // Store the whole object {items: [...]} 

                    renderMenuStructure(generatedMenuStructure.items); // Pass the items array
                    $results.show();
                    // Enable buttons
                    $('#create_wp_menu').prop('disabled', false);
                    $('#copy_menu_json').prop('disabled', false);
                } else {
                    const errorMessage = response.data ? (typeof response.data === 'string' ? response.data : 'Invalid menu structure data received.') : 'Invalid response from server.';
                    $error.text('Error generating menu: ' + errorMessage).show();
                    $('#create_wp_menu').prop('disabled', true);
                    $('#copy_menu_json').prop('disabled', true);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $loading.hide();
                $error.text('AJAX Error: ' + textStatus + ' - ' + errorThrown).show();
                $('#create_wp_menu').prop('disabled', true);
                $('#copy_menu_json').prop('disabled', true);
            }
        });
    }

    /**
     * Render menu structure as an interactive HTML list
     * @param {Array} items - The array of menu item objects.
     */
    function renderMenuStructure(items) {
        const $container = $('.menu-structure');
        $container.empty();

        if (!items || !Array.isArray(items)) {
            $container.text('Invalid menu data format.');
            return;
        }

        const $tree = $('<ul class="menu-tree-list ui-sortable"></ul>'); // Root list

        // Recursive function to build the tree
        function buildTree(items, $parentList) {
            items.forEach(function (item) {
                if (!item || !item.title) return; // Skip invalid items

                const $listItem = $('<li class="menu-tree-item"></li>');
                $listItem.data('menuItemData', item); // Store original data

                const $itemContent = $('<div class="menu-item-content"></div>');
                $itemContent.append('<span class="menu-item-title"></span>').find('.menu-item-title').text(item.title);
                $listItem.append($itemContent);

                // Always add a child UL container, even if children array is empty
                const $subList = $('<ul class="menu-tree-children ui-sortable"></ul>');
                if (item.children && Array.isArray(item.children) && item.children.length > 0) {
                    // If children exist, build them into the sublist
                    buildTree(item.children, $subList);
                }
                // Append the sublist (either populated or empty)
                $listItem.append($subList);

                $parentList.append($listItem);
            });
        }

        buildTree(items, $tree);
        $container.append($tree);

        // Initialize SortableJS using the native approach (matching example)
        const listElements = $container.find('.menu-tree-list, .menu-tree-children').get(); // Get native DOM elements

        listElements.forEach(function (listEl) {
            new Sortable(listEl, { // Use native initialization
                group: 'nested', // Allow dragging between lists with the same group name
                animation: 150, // Animation speed
                fallbackOnBody: true,
                swapThreshold: 0.65,
                forceFallback: true,
                ghostClass: 'sortable-ghost',  // Class for drop placeholder
                chosenClass: 'sortable-chosen', // Class for the element being dragged
                dragClass: 'sortable-drag', // Class for the mirror element during drag
                // Event when an item is dropped
                onEnd: function (/**Event*/evt) {
                    // Update the generatedMenuStructure JS variable after drop
                    updateStructureFromUI();
                },
            });
        });
    }

    /**
    * Reads the current state of the sortable tree UI 
    * and updates the `generatedMenuStructure` JavaScript variable.
    */
    function updateStructureFromUI() {
        const $treeRoot = $('.menu-structure > .menu-tree-list');

        function parseTree($list) {
            let items = [];
            $list.children('.menu-tree-item').each(function () {
                const $listItem = $(this);
                // Retrieve original data, but only use title/url, reconstruct children
                const originalData = $listItem.data('menuItemData') || {};
                const newItem = {
                    title: originalData.title || 'Untitled', // Get title from original data
                    url: originalData.url || '#' // Get URL from original data
                    // Add other properties from originalData if needed
                };

                const $childrenList = $listItem.children('.menu-tree-children');
                if ($childrenList.length > 0) {
                    newItem.children = parseTree($childrenList);
                }
                items.push(newItem);
            });
            return items;
        }

        if ($treeRoot.length) {
            const updatedItems = parseTree($treeRoot);
            generatedMenuStructure = { items: updatedItems }; // Update the global variable
            console.log("Updated menu structure based on UI:", JSON.stringify(generatedMenuStructure, null, 2));
        } else {
            console.error("Could not find menu tree root to update structure.");
            generatedMenuStructure = null; // Indicate structure is invalid
        }
        // Ensure buttons are still enabled/disabled correctly
        $('#create_wp_menu').prop('disabled', !generatedMenuStructure || !generatedMenuStructure.items || generatedMenuStructure.items.length === 0);
        $('#copy_menu_json').prop('disabled', !generatedMenuStructure || !generatedMenuStructure.items || generatedMenuStructure.items.length === 0);
    }

    /**
     * Copy the menu data as a JSON string
     */
    function copyMenuJson() {
        updateStructureFromUI(); // Ensure we copy the latest structure

        if (!generatedMenuStructure) {
            showToast('No menu structure available to copy.', 'error');
            return;
        }

        const jsonString = JSON.stringify(generatedMenuStructure, null, 2); // Pretty print

        navigator.clipboard.writeText(jsonString).then(function () {
            showToast('Menu structure JSON copied to clipboard!', 'success');
        }, function (err) {
            showToast('Failed to copy JSON: ' + err, 'error');
            console.error('Async: Could not copy text: ', err);
        });
    }

    /**
     * Create WordPress menu from potentially modified structure
     */
    function createWordPressMenu() {
        updateStructureFromUI(); // Ensure we use the latest structure

        if (!generatedMenuStructure || !generatedMenuStructure.items || generatedMenuStructure.items.length === 0) {
            showToast('Cannot create menu: Structure is empty or invalid.', 'error');
            return;
        }

        const menuName = $('#menu_type option:selected').text() + ' Menu (AI)';
        const $error = $('#menu_error');
        const $status = $('#menu_status');
        const $loading = $('#menu_loading');

        // Reset UI
        $error.hide().empty();
        $status.hide().empty();
        $loading.show();

        // Prepare data
        const data = {
            action: 'cpt_create_wp_menu',
            // Send the potentially modified structure
            menu_structure: JSON.stringify(generatedMenuStructure),
            menu_name: menuName,
            nonce: cptMenuVars.nonce
        };

        // Make AJAX request
        $.ajax({
            url: cptMenuVars.ajaxurl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (response) {
                $loading.hide();
                if (response.success) {
                    const message = response.data.message || 'Menu created successfully.';
                    if (typeof cptkShowToast !== 'undefined') {
                        cptkShowToast(message, 'success');
                        console.log("Menu Creation Details:", response.data);
                    } else {
                        alert(message); // Fallback
                    }
                    if (response.data.edit_url) {
                        $status.html(`Menu created. <a href="${escapeUrl(response.data.edit_url)}" target="_blank">Edit Menu</a>`).show();
                    } else {
                        $status.text('Menu created successfully.').show();
                    }
                } else {
                    const errorMessage = response.data ? (typeof response.data === 'string' ? response.data : 'Failed to create menu.') : 'Unknown error during menu creation.';
                    $error.text(errorMessage).show();
                    $status.hide();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $loading.hide();
                $error.text('AJAX Error creating menu: ' + textStatus + ' - ' + errorThrown).show();
                $status.hide();
            }
        });
    }

    // --- Helper Functions ---
    function escapeHtml(unsafe) {
        if (unsafe === null || typeof unsafe === 'undefined') return '';
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function escapeUrl(unsafe) {
        // Very basic check
        if (unsafe === null || typeof unsafe === 'undefined') return '#';
        return unsafe;
    }

    // Assumes cptkShowToast is globally available from assets/js/toast.js
    function showToast(message, type = 'info') {
        if (typeof cptkShowToast !== 'undefined') {
            cptkShowToast(message, type);
        } else {
            alert(message); // Fallback
            console.log('Toast: [' + type + '] ' + message);
        }
    }

})(jQuery); 