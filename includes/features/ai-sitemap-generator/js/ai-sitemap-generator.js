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
        if (!$generateBtn.length) return;

        $generateBtn.on('click', function (e) {
            e.preventDefault();
            generateSitemap();
        });

        // Save button handler
        $('#save_sitemap').on('click', function () {
            saveSitemapAsJson();
        });

        // Export button handler
        $('#export_sitemap').on('click', function () {
            exportSitemapToCsv();
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

                    // Render the sitemap tree
                    renderSitemapTree(response.data);
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
     * Render sitemap tree in the UI
     */
    function renderSitemapTree(data) {
        const $container = $('.sitemap-tree');
        $container.empty();

        const $tree = $('<ul class="sitemap-tree-list"></ul>');

        // Build the tree recursively
        function buildTree(items, $parent) {
            items.forEach(function (item) {
                const $item = $('<li></li>');
                $item.append('<span class="sitemap-page-title">' + item.title + '</span>');

                if (item.description) {
                    $item.append('<p class="sitemap-page-desc">' + item.description + '</p>');
                }

                if (item.children && item.children.length) {
                    const $subList = $('<ul></ul>');
                    buildTree(item.children, $subList);
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
     * Save the sitemap data as a JSON file
     */
    function saveSitemapAsJson() {
        if (!window.sitemapData) return;

        const dataStr = JSON.stringify(window.sitemapData, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,' + encodeURIComponent(dataStr);

        const exportLink = document.createElement('a');
        exportLink.setAttribute('href', dataUri);
        exportLink.setAttribute('download', 'sitemap.json');
        exportLink.click();
    }

    /**
     * Export the sitemap data as a CSV file
     */
    function exportSitemapToCsv() {
        if (!window.sitemapData) return;

        let csvContent = 'Page Title,URL Path,Description,Level\n';

        // Flatten the tree structure
        function processItems(items, level) {
            items.forEach(function (item) {
                const path = item.path || '';
                const desc = item.description || '';

                // Escape quotes in CSV
                const title = item.title.replace(/"/g, '""');
                const description = desc.replace(/"/g, '""');

                csvContent += `"${title}","${path}","${description}",${level}\n`;

                if (item.children && item.children.length) {
                    processItems(item.children, level + 1);
                }
            });
        }

        processItems(window.sitemapData, 1);

        const dataUri = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvContent);
        const exportLink = document.createElement('a');
        exportLink.setAttribute('href', dataUri);
        exportLink.setAttribute('download', 'sitemap.csv');
        exportLink.click();
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

})(jQuery); 