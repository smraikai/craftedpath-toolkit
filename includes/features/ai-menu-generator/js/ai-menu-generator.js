/**
 * AI Menu Generator JavaScript
 * Handles AJAX requests and UI interactions for generating menus.
 */
(function ($) {
    'use strict';

    // Check if jQuery is available
    if (typeof $ === 'undefined') {
        console.error('jQuery is required for the AI Menu Generator');
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

                if (response.success && response.data.menu_structure) {
                    generatedMenuStructure = response.data.menu_structure; // Store the structure

                    // Render the menu structure (using JSON viewer or simple list)
                    renderMenuStructure(generatedMenuStructure);
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
     * Render menu structure in the UI (using pre/code for JSON)
     */
    function renderMenuStructure(menuData) {
        const $container = $('.menu-structure'); // Use class from PHP view
        $container.empty();

        if (menuData && typeof menuData === 'object') {
            // Display formatted JSON in the code block
            const jsonString = JSON.stringify(menuData, null, 2); // Pretty print
            $container.text(jsonString); // Use text to display safely
            // If using a syntax highlighter like Prism.js, trigger it here
            // if (typeof Prism !== 'undefined') {
            //     Prism.highlightElement($container[0]);
            // }
        } else {
            $container.text('Invalid menu data format.');
        }
    }

    /**
     * Copy the menu data as a JSON string
     */
    function copyMenuJson() {
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
            // Add fallback if needed, similar to page generator
        });
    }

    /**
     * Create WordPress menu from generated data
     */
    function createWordPressMenu() {
        if (!generatedMenuStructure) {
            showToast('No menu structure generated yet.', 'error');
            return;
        }

        // Ask for menu name? Or use default from PHP?
        // Let's stick to PHP default for now ('AI Generated Menu' + timestamp)
        const menuName = $('#menu_type option:selected').text() + ' Menu (AI)'; // Example name

        const $error = $('#menu_error'); // Use ID from PHP view
        const $status = $('#menu_status'); // Use ID from PHP view
        const $loading = $('#menu_loading'); // Use ID from PHP view

        // Reset UI
        $error.hide().empty();
        $status.hide().empty();
        $loading.show();

        // Prepare data
        const data = {
            action: 'cpt_create_wp_menu',
            menu_structure: JSON.stringify(generatedMenuStructure), // Send the full structure
            menu_name: menuName, // Send a suggested name
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
                if (response.success) {
                    // Use toast notification
                    const message = response.data.message || 'Menu created successfully.';
                    if (typeof cptkShowToast !== 'undefined') {
                        cptkShowToast(message, 'success');
                        // Log extra info to console?
                        console.log("Menu Creation Details:", response.data);
                    } else {
                        alert(message); // Fallback
                    }
                    // Add link to edit menu in status area?
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