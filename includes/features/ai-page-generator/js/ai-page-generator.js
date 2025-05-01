/**
 * AI Page Structure Generator JavaScript
 * Handles AJAX requests and UI interactions for generating page structures.
 * Logic mirrors the original Sitemap generator portion.
 */
(function ($) {
    'use strict';

    // Check if jQuery is available
    if (typeof $ === 'undefined') {
        console.error('jQuery is required for the AI Page Generator');
        return;
    }

    // Check for localization variables
    if (typeof cptPageGenVars === 'undefined') {
        console.error('Localization variables (cptPageGenVars) are missing.');
        return;
    }

    // Store generated page structure data (equivalent to original window.sitemapData)
    let pageStructureData = null;

    // Init on document ready
    $(document).ready(function () {
        initSitemapGenerator(); // Use original function name
    });

    /**
     * Initialize the Sitemap Generator (using original function name and logic)
     */
    function initSitemapGenerator() {
        const $generateBtn = $('#cpt-generate-pages-btn'); // Use ID from PHP view
        const $sitemapResultsContainer = $('#sitemap_results'); // Use original container ID (reverted in PHP)

        if (!$generateBtn.length) {
            console.log("Generate button not found, skipping init.");
            return;
        }

        // Generate button click
        $generateBtn.on('click', function (e) {
            e.preventDefault();
            generateSitemap(); // Call original function name
        });

        // Use event delegation for dynamically added 'Create Pages' button
        $sitemapResultsContainer.on('click', '#create_pages', function () { // Use original button ID
            createWordPressPages(); // Call original function name
        });

        // Use event delegation for dynamically added 'Select All' checkbox
        $sitemapResultsContainer.on('change', '#select_all_pages', function () { // Use original checkbox ID
            const isChecked = $(this).prop('checked');
            $sitemapResultsContainer.find('.page-checkbox').prop('checked', isChecked); // Use original class
        });
    }

    /**
     * Generate sitemap/page structure using AI (using original function name)
     */
    function generateSitemap() {
        const $description = $('#sitemap_description'); // Original ID
        const $depth = $('#sitemap_depth'); // Original ID
        const $results = $('#sitemap_results'); // Original ID
        const $error = $('#sitemap_error'); // Original ID
        const $loading = $('#sitemap_loading'); // Original ID
        const $status = $('#page_gen_status'); // Keep new status ID

        // Clear previous results/messages
        $error.hide().empty();
        $status.hide().empty();
        $results.hide().find('.sitemap-tree').empty(); // Clear tree
        $results.find('.cpt-actions').empty(); // Clear actions area
        $loading.show();
        pageStructureData = null; // Reset stored data

        if (!$description.val().trim()) {
            $loading.hide();
            $error.text('Please provide a website description.').show();
            return;
        }

        // Prepare data for AJAX request
        const data = {
            action: 'cpt_generate_page_structure', // NEW Action name
            description: $description.val(),
            depth: $depth.val(),
            nonce: cptPageGenVars.nonce // NEW Nonce variable and key
        };

        // Make AJAX request
        $.ajax({
            url: cptPageGenVars.ajaxurl, // NEW localized URL
            type: 'POST',
            data: data,
            dataType: 'json', // Expect JSON response
            success: function (response) {
                $loading.hide();

                // ADJUSTED Data Handling 
                if (response.success && response.data && response.data.page_structure && Array.isArray(response.data.page_structure.pages)) {
                    pageStructureData = response.data.page_structure.pages; // Store JUST the array of pages

                    renderSitemapTreeWithCheckboxes(pageStructureData); // Pass the pages array
                    $results.show();
                } else {
                    const errorMessage = response.data ? (typeof response.data === 'string' ? response.data : (response.data.message || 'Invalid page structure data received.')) : 'Invalid response from server.';
                    $error.text('Error generating structure: ' + errorMessage).show();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $loading.hide();
                $error.text('AJAX Error: ' + textStatus + ' - ' + errorThrown).show();
            }
        });
    }

    /**
     * Render sitemap tree with checkboxes in the UI (Original Function)
     * @param {Array} pagesArray - The array of page objects.
     */
    function renderSitemapTreeWithCheckboxes(pagesArray) {
        const $container = $('.sitemap-tree'); // Original class
        $container.empty(); // Clear previous tree
        const $actionsContainer = $('#sitemap_results .cpt-actions');
        $actionsContainer.empty(); // Clear previous actions

        // Add checkbox controls at the top (original logic)
        const $controls = $('<div class="sitemap-controls"></div>');
        $controls.append('<label><input type="checkbox" id="select_all_pages" checked> Select/Deselect All</label>');
        $container.append($controls);

        const $tree = $('<ul class="sitemap-tree-list"></ul>'); // Original class

        // Build the tree recursively (original logic)
        function buildTree(items, $parent, parentIdPrefix = 'page') {
            items.forEach(function (item, index) {
                if (!item || !item.title) return; // Skip invalid items

                const uniqueIdPart = parentIdPrefix + '_' + index;
                const pageId = 'page_' + uniqueIdPart.replace(/[^a-zA-Z0-9_]/g, '_'); // Sanitize ID

                const $item = $('<li></li>');
                const $checkbox = $('<input type="checkbox" class="page-checkbox" id="' + pageId + '" checked>'); // Original class

                $checkbox.data('pageData', item); // Store the actual page data object 

                const $label = $('<label for="' + pageId + '"></label>');
                $label.append('<span class="sitemap-page-title">' + escapeHtml(item.title) + '</span>');

                // Optional: Add back description if AI provides it and original UI showed it
                // if (item.description) {
                //     $label.append('<p class="sitemap-page-desc">' + escapeHtml(item.description) + '</p>');
                // }

                $item.append($checkbox);
                $item.append($label);

                if (item.children && item.children.length) {
                    const $subList = $('<ul></ul>');
                    buildTree(item.children, $subList, pageId); // Pass unique ID prefix
                    $item.append($subList);
                }

                $parent.append($item);
            });
        }

        // Initialize tree building
        if (Array.isArray(pagesArray)) {
            buildTree(pagesArray, $tree);
            $container.append($tree);
        } else {
            $container.text('No valid page structure data to display.');
        }

        // Add Create Pages button (original logic)
        if (pagesArray && pagesArray.length > 0) {
            $actionsContainer.prepend('<button class="button button-primary" id="create_pages">Create Selected Pages</button>'); // Original ID
        }
    }

    /**
     * Create WordPress pages from the selected checkboxes (Original Function)
     */
    function createWordPressPages() {
        const $error = $('#sitemap_error'); // Original ID
        const $status = $('#page_gen_status'); // Keep new status ID
        const $loading = $('#sitemap_loading'); // Original ID
        const $resultsContainer = $('#sitemap_results'); // Original ID

        // Reset UI 
        $error.hide().empty();
        $status.hide().empty();

        // Get selected page data from checkboxes
        const selectedPages = [];
        $resultsContainer.find('.page-checkbox:checked').each(function () {
            const itemData = $(this).data('pageData'); // Retrieve stored object
            if (itemData) {
                // We only need title and children for recursive creation on backend
                // If backend expects full original object, send itemData directly.
                // Assuming backend handles the flat list and reconstructs hierarchy based on title/parent lookups.
                // PHP side (`create_pages_recursive`) expects an array of objects with `title` and potentially `children`.
                // Sending the full `itemData` should be fine as PHP accesses `$page_data['title']` etc.
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
            action: 'cpt_create_wp_pages', // NEW Action name
            // Send the array of selected page objects. 
            // PHP `ajax_create_wp_pages` expects a JSON string in `pages_to_create`.
            pages_to_create: JSON.stringify(selectedPages),
            nonce: cptPageGenVars.nonce // NEW Nonce variable and key
        };

        // Make AJAX request to create pages
        $.ajax({
            url: cptPageGenVars.ajaxurl, // NEW localized URL
            type: 'POST',
            data: data,
            dataType: 'json', // Expect JSON response
            success: function (response) {
                if (response.success) {
                    // Use toast for success message
                    const baseMessage = response.data.message || 'Pages processed successfully.';
                    if (typeof showCPTToast !== 'undefined') {
                        showCPTToast(baseMessage, 'success');
                        // Log detailed results to console instead of adding to toast/status div
                        if (response.data.created && response.data.created.length > 0) {
                            console.log('Page Creation Details:', response.data.created);
                        } else {
                            console.log('No new pages were created (perhaps they already existed).')
                        }
                    } else {
                        alert(baseMessage); // Fallback if toast function isn't available
                    }
                    $status.hide(); // Hide the status div as toast is used
                } else {
                    // Keep showing errors in the error div
                    let errorMessage = response.data ? (typeof response.data === 'string' ? response.data : (response.data.message || 'Failed to create pages.')) : 'Unknown error during page creation.';
                    if (response.data && response.data.failed && response.data.failed.length > 0) {
                        errorMessage += '<br>Failed items: ' + response.data.failed.map(escapeHtml).join(', ');
                    }
                    if (response.data && response.data.created && response.data.created.length > 0) {
                        errorMessage += '<br>Successfully created before error: ' + response.data.created.map(p => escapeHtml(p.title)).join(', ');
                    }
                    $error.html(errorMessage).show();
                    $status.hide(); // Ensure status div is hidden on error too
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $error.text('AJAX Error creating pages: ' + textStatus + ' - ' + errorThrown).show();
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
        // Very basic check, relies on WP sanitization mostly
        if (unsafe === null || typeof unsafe === 'undefined') return '#';
        // Assuming edit_url is properly generated by WP
        return unsafe;
    }

    // REMOVED: showToast, updateStructureFromUI, getCurrentPageStructureForCreation, copyGeneratedJson, etc.

})(jQuery); 