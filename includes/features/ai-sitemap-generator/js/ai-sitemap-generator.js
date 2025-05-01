/**
 * AI Sitemap Generator JavaScript
 * Handles AJAX requests and UI interactions
 */
(function ($) {
    'use strict';

    // Check if jQuery is available
    if (typeof $ === 'undefined') {
        console.error('jQuery is required for the AI Sitemap Generator');
        return;
    }

    // Init on document ready
    $(document).ready(function () {
        initSitemapGenerator();
        initMenuGenerator();
    });

    /**
     * Initialize the Sitemap Generator
     */
    function initSitemapGenerator() {
        const $generateBtn = $('#generate_sitemap');
        const $sitemapResultsContainer = $('#sitemap_results'); // Static container for delegation

        if (!$generateBtn.length) return;

        // Generate button click
        $generateBtn.on('click', function (e) {
            e.preventDefault();
            generateSitemap();
        });

        // Use event delegation for dynamically added elements
        $sitemapResultsContainer.on('click', '#create_pages', function () {
            createWordPressPages();
        });

        $sitemapResultsContainer.on('change', '#select_all_pages', function () {
            const isChecked = $(this).prop('checked');
            // Find checkboxes within the sitemap tree relative to the container
            $sitemapResultsContainer.find('.page-checkbox').prop('checked', isChecked);
        });
    }

    /**
     * Initialize the Menu Generator
     */
    function initMenuGenerator() {
        const $generateBtn = $('#generate_menu');
        if (!$generateBtn.length) return;

        $generateBtn.on('click', function (e) {
            e.preventDefault();
            generateMenu();
        });

        // Create WP Menu button handler
        $('#create_wp_menu').on('click', function () {
            createWordPressMenu();
        });

        // Export button handler
        $('#export_menu').on('click', function () {
            exportMenuAsJson();
        });
    }

    /**
     * Generate sitemap using AI
     */
    function generateSitemap() {
        const $description = $('#sitemap_description');
        const $depth = $('#sitemap_depth');
        const $results = $('#sitemap_results');
        const $error = $('#sitemap_error');
        const $loading = $('#sitemap_loading');

        if (!$description.val().trim()) {
            $error.text('Please provide a website description').show();
            return;
        }

        // Reset UI
        $error.hide();
        $results.hide();
        $loading.show();
        // Clear previous success messages
        $('.notice-success').remove();

        // Prepare data
        const data = {
            action: 'cpt_generate_sitemap',
            description: $description.val(),
            depth: $depth.val(),
            security: cptSitemapVars.nonce
        };

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function (response) {
                $loading.hide();

                if (response.success) {
                    // Store the response for later use
                    window.sitemapData = response.data;

                    // Render the sitemap tree with checkboxes
                    renderSitemapTreeWithCheckboxes(response.data);
                    $results.show();
                } else {
                    $error.text(response.data).show();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $loading.hide();
                $error.text('Error: ' + errorThrown).show();
            }
        });
    }

    /**
     * Generate menu using AI
     */
    function generateMenu() {
        const $menuType = $('#menu_type');
        const $useExistingSitemap = $('#use_existing_sitemap');
        const $results = $('#menu_results');
        const $error = $('#menu_error');
        const $loading = $('#menu_loading');

        // Reset UI
        $error.hide();
        $results.hide();
        $loading.show();

        // Prepare data
        const data = {
            action: 'cpt_generate_menu',
            menu_type: $menuType.val(),
            use_existing_sitemap: $useExistingSitemap.is(':checked'),
            security: cptSitemapVars.nonce
        };

        // If using existing sitemap, include it
        if (data.use_existing_sitemap && window.sitemapData) {
            data.sitemap_data = JSON.stringify(window.sitemapData);
        }

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function (response) {
                $loading.hide();

                if (response.success) {
                    // Store the response for later use
                    window.menuData = response.data;

                    // Render the menu structure
                    renderMenuStructure(response.data);
                    $results.show();
                } else {
                    $error.text(response.data).show();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $loading.hide();
                $error.text('Error: ' + errorThrown).show();
            }
        });
    }

    /**
     * Render sitemap tree with checkboxes in the UI
     */
    function renderSitemapTreeWithCheckboxes(data) {
        const $container = $('.sitemap-tree');
        $container.empty();

        // Add checkbox controls at the top
        const $controls = $('<div class="sitemap-controls"></div>');
        $controls.append('<label><input type="checkbox" id="select_all_pages" checked> Select/Deselect All</label>');
        $container.append($controls);

        const $tree = $('<ul class="sitemap-tree-list"></ul>');

        // Build the tree recursively
        function buildTree(items, $parent, parentPath = '') {
            items.forEach(function (item, index) {
                // Use a more robust ID generation (e.g., based on path and index)
                const uniqueIdPart = parentPath.replace(/[^a-zA-Z0-9]/g, '_') + '_' + index;
                const pageId = 'page_' + uniqueIdPart;
                const itemPath = item.path || '';

                const $item = $('<li></li>');
                // Pass the unique ID to the checkbox and store item data correctly
                const $checkbox = $('<input type="checkbox" class="page-checkbox" id="' + pageId + '" checked>');
                // Use .data() to store the object, avoiding JSON stringify issues in attributes
                $checkbox.data('item', item);
                const $label = $('<label for="' + pageId + '"></label>');

                $label.append('<span class="sitemap-page-title">' + item.title + '</span>');

                if (item.description) {
                    $label.append('<p class="sitemap-page-desc">' + item.description + '</p>');
                }

                $item.append($checkbox);
                $item.append($label);

                if (item.children && item.children.length) {
                    const $subList = $('<ul></ul>');
                    // Pass the generated unique pageId as the parent identifier for children
                    buildTree(item.children, $subList, pageId);
                    $item.append($subList);
                }

                $parent.append($item);
            });
        }

        // Initialize tree building
        if (Array.isArray(data)) {
            buildTree(data, $tree);
            $container.append($tree);
        }

        // Ensure Create Pages button exists in the actions area
        if ($('#create_pages').length === 0) {
            $('#sitemap_results .cpt-actions').prepend('<button class="button button-primary" id="create_pages">Create Selected Pages</button>');
        }
    }

    /**
     * Render menu structure in the UI
     */
    function renderMenuStructure(data) {
        const $container = $('.menu-structure');
        $container.empty();

        const $menuList = $('<ul class="menu-list"></ul>');

        // Build the menu recursively
        function buildMenu(items, $parent) {
            items.forEach(function (item) {
                const $item = $('<li></li>');
                $item.append('<span class="menu-item-title">' + item.title + '</span>');

                if (item.children && item.children.length) {
                    const $subList = $('<ul></ul>');
                    buildMenu(item.children, $subList);
                    $item.append($subList);
                }

                $parent.append($item);
            });
        }

        // Initialize menu building
        if (Array.isArray(data)) {
            buildMenu(data, $menuList);
            $container.append($menuList);
        }
    }

    /**
     * Export the menu data as a JSON file
     */
    function exportMenuAsJson() {
        if (!window.menuData) return;

        const dataStr = JSON.stringify(window.menuData, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,' + encodeURIComponent(dataStr);

        const exportLink = document.createElement('a');
        exportLink.setAttribute('href', dataUri);
        exportLink.setAttribute('download', 'menu.json');
        exportLink.click();
    }

    /**
     * Create WordPress menu from generated data
     */
    function createWordPressMenu() {
        if (!window.menuData) return;

        const $error = $('#menu_error');
        const $loading = $('#menu_loading');

        // Reset UI
        $error.hide();
        $loading.show();

        // Prepare data
        const data = {
            action: 'cpt_create_wp_menu',
            menu_data: JSON.stringify(window.menuData),
            menu_name: $('#menu_type option:selected').text(),
            security: cptSitemapVars.nonce
        };

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function (response) {
                $loading.hide();

                if (response.success) {
                    alert('WordPress menu created successfully!');
                } else {
                    $error.text(response.data).show();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $loading.hide();
                $error.text('Error: ' + errorThrown).show();
            }
        });
    }

    /**
     * Create WordPress pages from the generated sitemap
     */
    function createWordPressPages() {
        const $error = $('#sitemap_error');
        const $resultsContainer = $('#sitemap_results');

        // Reset UI 
        $error.hide();
        $('.notice-success').remove(); // Clear previous success messages

        // Get all checked page data directly from checkboxes using .data()
        const selectedPages = [];
        $resultsContainer.find('.page-checkbox:checked').each(function () {
            const itemData = $(this).data('item');
            if (itemData) {
                selectedPages.push(itemData);
            } else {
                console.warn("Could not retrieve item data for checkbox: ", this.id);
            }
        });

        // Check if any pages are selected
        if (selectedPages.length === 0) {
            $error.text('Please select at least one page to create').show();
            return;
        }

        // Prepare AJAX data
        const data = {
            action: 'cpt_create_wp_pages',
            // Send the flat list of selected pages, hierarchy reconstruction will happen on the server
            pages_data: JSON.stringify(selectedPages),
            security: cptSitemapVars.nonce
        };

        // Make AJAX request to create pages
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function (response) {
                if (response.success) {
                    // Show success toast notification
                    if (typeof showCPTToast === 'function') {
                        showCPTToast(response.data.message, 'success');
                    } else {
                        // Fallback if toast function isn't available
                        alert(response.data.message);
                    }
                    // Optionally, clear the selection or results after success
                    // $resultsContainer.hide(); 
                    // window.sitemapData = null;
                } else {
                    $error.text(response.data).show();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $error.text('Error creating pages: ' + errorThrown).show();
            }
        });
    }

})(jQuery); 