jQuery(document).ready(function ($) {
    console.log('AISMG Admin JS Loaded');

    // --- Debug: Check if buttons exist on load ---
    if ($('#aismg-generate-sitemap').length) {
        console.log("Sitemap generate button found on load.");
    } else {
        console.warn("Sitemap generate button NOT found on load!");
    }
    if ($('#aismg-generate-menu').length) {
        console.log("Menu generate button found on load.");
    } else {
        console.warn("Menu generate button NOT found on load!");
    }
    // --- End Debug ---

    // --- General Helper Functions --- 
    function showSpinner($button) {
        $button.next('.spinner').addClass('is-active');
        $button.prop('disabled', true);
    }

    function hideSpinner($button) {
        $button.next('.spinner').removeClass('is-active');
        $button.prop('disabled', false);
    }

    function showError(message, $container) {
        var $errorDiv = $container.find('.aismg-error');
        // Ensure WP notice classes are present for styling
        $errorDiv.addClass('notice notice-error is-dismissible');
        // Set content and explicitly show
        $errorDiv.html('<p>' + message + '</p>').show();
        // Alternative forceful show, uncomment if .show() isn't enough
        // $errorDiv.html('<p>' + message + '</p>').css('display', 'block'); 
        $container.find('.aismg-status').hide(); // Hide status if error shown
    }

    function hideMessages($container) {
        $container.find('.aismg-error').hide().empty();
        // We can keep the notice classes or remove them, hiding should be sufficient
        // $container.find('.aismg-error').removeClass('notice notice-error is-dismissible').hide().empty(); 
        $container.find('.aismg-status').hide().empty();
    }

    function showStatus(htmlContent, $container) {
        $container.find('.aismg-status').html(htmlContent).show();
        $container.find('.aismg-error').hide(); // Hide error if status shown
    }

    // --- Sitemap Generation --- 
    $('#aismg-generate-sitemap').on('click', function () {
        var $button = $(this);
        var $container = $('.aismg-sitemap-page');

        if ($container.length === 0) {
            console.error("Could not find the container element '.aismg-sitemap-page'!");
            alert("Plugin error: Could not find page container. Please contact support.");
            return;
        }

        var prompt = $container.find('#aismg_sitemap_prompt').val().trim();

        hideMessages($container);
        $container.find('#aismg-sitemap-results').empty().hide(); // Clear previous results

        if (!prompt) {
            showError('Please enter a description for your sitemap.', $container);
            return;
        }

        showSpinner($button);
        $button.text(aismg_ajax_object.sitemap_generating_text);

        $.ajax({
            url: aismg_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'aismg_generate_sitemap',
                nonce: aismg_ajax_object.nonce,
                prompt: prompt
            },
            success: function (response) {
                console.log("AJAX Success:", response); // Log the entire response
                if (response.success) {
                    var $resultsDiv = $container.find('#aismg-sitemap-results');
                    console.log("Target results div:", $resultsDiv); // Log the jQuery object
                    if (response.data.pages && response.data.pages.length > 0) {
                        console.log("Received pages data:", response.data.pages); // Log the pages data
                        // Updated List HTML generation
                        var listHtml = '<h3>Proposed Sitemap Pages:</h3><ul>';
                        $.each(response.data.pages, function (index, pageData) {
                            // pageData contains: title, slug, level, parent_index (optional)
                            var indentation = '&nbsp;'.repeat(pageData.level * 4); // 4 spaces for visual indentation
                            // Value format: title||slug||level||parent_index (parent_index might be undefined for top level)
                            var parentIndexVal = (typeof pageData.parent_index !== 'undefined') ? pageData.parent_index : -1;
                            var value = escapeHtml(pageData.title) + '||' + escapeHtml(pageData.slug) + '||' + pageData.level + '||' + parentIndexVal;
                            listHtml += '<li><label>' +
                                indentation +
                                '<input type="checkbox" name="sitemap_pages[]" value="' + value + '" checked data-index="' + index + '" data-level="' + pageData.level + '"> ' +
                                escapeHtml(pageData.title) +
                                ' <small>(Slug: ' + escapeHtml(pageData.slug) + ')</small>' +
                                '</label></li>';
                        });
                        listHtml += '</ul><button id="aismg-create-pages" class="button button-primary">Create Selected Pages</button><span class="spinner"></span>';
                        console.log("Generated HTML:", listHtml); // Log the generated HTML
                        try {
                            $resultsDiv.html(listHtml).show();
                            console.log("HTML injected and shown.");
                        } catch (e) {
                            console.error("Error updating results div:", e);
                        }
                    } else {
                        console.log("No pages data found in successful response.");
                        showError('The AI did not return any page suggestions. Try refining your prompt.', $container);
                    }
                } else {
                    console.log("AJAX response success=false:", response.data.message);
                    showError(response.data.message || aismg_ajax_object.error_text, $container);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Sitemap Generation AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                showError('AJAX error: ' + textStatus + ' - ' + errorThrown, $container);
            },
            complete: function () {
                hideSpinner($button);
                $button.text('Generate Sitemap'); // Reset button text
            }
        });
    });

    // --- Page Creation (Event delegation for dynamically added button) --- 
    $('.aismg-sitemap-page').on('click', '#aismg-create-pages', function () {
        var $button = $(this);
        var $container = $(this).closest('.aismg-sitemap-page');
        var selectedPagesData = [];

        hideMessages($container); // Clear previous status/errors

        $container.find('#aismg-sitemap-results input[name="sitemap_pages[]"]:checked').each(function () {
            selectedPagesData.push($(this).val()); // Value is title||slug||level||parent_index
        });

        if (selectedPagesData.length === 0) {
            showError('Please select at least one page to create.', $container);
            return;
        }

        showSpinner($button);
        $button.text(aismg_ajax_object.sitemap_creating_text);

        $.ajax({
            url: aismg_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'aismg_create_pages',
                nonce: aismg_ajax_object.nonce,
                pages_data: selectedPagesData // Send structured data
            },
            success: function (response) {
                if (response.success) {
                    var statusHtml = '<p><strong>Page Creation Results:</strong></p><ul>';
                    var hasSuccess = false;
                    var hasFailure = false;

                    if (response.data.created && response.data.created.length > 0) {
                        hasSuccess = true;
                        $.each(response.data.created, function (index, page) {
                            statusHtml += '<li>Created: ' + escapeHtml(page.title) + ' (<a href="' + page.edit_link + '" target="_blank">Edit</a> | <a href="' + page.view_link + '" target="_blank">View</a>)</li>';
                        });
                    }
                    if (response.data.skipped && response.data.skipped.length > 0) {
                        hasFailure = true;
                        $.each(response.data.skipped, function (index, page) {
                            statusHtml += '<li>Skipped (already exists): ' + escapeHtml(page.title) + ' (<a href="' + page.edit_link + '" target="_blank">Edit</a> | <a href="' + page.view_link + '" target="_blank">View</a>)</li>';
                        });
                    }
                    if (response.data.failed && response.data.failed.length > 0) {
                        hasFailure = true;
                        $.each(response.data.failed, function (index, page) {
                            statusHtml += '<li><span style="color: red;">Failed:</span> ' + escapeHtml(page.title) + (page.error ? ' - ' + escapeHtml(page.error) : '') + '</li>';
                        });
                    }
                    statusHtml += '</ul>';
                    if (hasSuccess || hasFailure) {
                        showStatus(statusHtml, $container);
                    }
                    if (response.data.message) { // Optional summary message
                        showStatus('<p>' + response.data.message + '</p>' + ((hasSuccess || hasFailure) ? statusHtml : ''), $container);
                    }

                    // Optionally clear results or disable button after success?
                    $container.find('#aismg-sitemap-results').empty().hide(); // Clear results list after creation
                } else {
                    showError(response.data.message || aismg_ajax_object.error_text, $container);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Page Creation AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                showError('AJAX error: ' + textStatus + ' - ' + errorThrown, $container);
            },
            complete: function () {
                hideSpinner($button);
                // Button is removed when results are cleared, no need to reset text
                // $button.text('Create Selected Pages'); 
            }
        });
    });

    // --- Menu Generation --- 
    $('#aismg-generate-menu').on('click', function () {
        console.log("Menu Generate button clicked!");
        var $button = $(this);
        var $container = $('.aismg-menu-page');

        if ($container.length === 0) {
            console.error("Could not find the container element '.aismg-menu-page'!");
            alert("Plugin error: Could not find page container. Please contact support.");
            return;
        }

        var prompt = $container.find('#aismg_menu_prompt').val().trim();

        hideMessages($container);
        $container.find('#aismg-menu-results').empty().hide(); // Clear previous results

        if (!prompt) {
            showError('Please enter a description for your menu.', $container);
            return;
        }

        showSpinner($button);
        $button.text(aismg_ajax_object.menu_generating_text);

        $.ajax({
            url: aismg_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'aismg_generate_menu',
                nonce: aismg_ajax_object.nonce,
                prompt: prompt
            },
            success: function (response) {
                if (response.success) {
                    var $resultsDiv = $container.find('#aismg-menu-results');
                    if (response.data.menu_items && response.data.menu_items.length > 0) {
                        var listHtml = '<h3>Proposed Menu Structure:</h3>';
                        listHtml += '<p>Enter a name for the new menu (required): <input type="text" id="aismg-new-menu-name" size="30"></p>';
                        listHtml += '<ul>';
                        $.each(response.data.menu_items, function (index, item) {
                            // Updated display and value format
                            var indentation = '&nbsp;'.repeat(item.level * 4); // 4 spaces for visual indentation
                            // Value format: type||title||value||level
                            var value = escapeHtml(item.type) + '||' + escapeHtml(item.title) + '||' + escapeHtml(item.value) + '||' + item.level;
                            listHtml += '<li><label>' + indentation +
                                '<input type="checkbox" name="menu_items[]" value="' + value + '" checked data-index="' + index + '" data-level="' + item.level + '"> ';
                            listHtml += escapeHtml(item.title);
                            if (item.type === 'page') {
                                listHtml += ' <small>(Page: ' + escapeHtml(item.value) + ')</small>';
                            } else if (item.type === 'custom') {
                                listHtml += ' <small>(Custom Link/Path: ' + escapeHtml(item.value) + ')</small>'; // Updated text
                            }
                            listHtml += '</label></li>';
                        });
                        listHtml += '</ul><button id="aismg-create-menu" class="button button-primary">Create Menu & Add Items</button><span class="spinner"></span>';
                        $resultsDiv.html(listHtml).show();
                    } else {
                        showError('The AI did not return any menu item suggestions. Try refining your prompt.', $container);
                    }
                } else {
                    showError(response.data.message || aismg_ajax_object.error_text, $container);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Menu Generation AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                showError('AJAX error: ' + textStatus + ' - ' + errorThrown, $container);
            },
            complete: function () {
                hideSpinner($button);
                $button.text('Generate Menu Items'); // Correct reset button text
            }
        });
    });

    // --- Menu Creation (Event delegation) --- 
    $('.aismg-menu-page').on('click', '#aismg-create-menu', function () {
        var $button = $(this);
        var $container = $(this).closest('.aismg-menu-page');
        var selectedItems = [];
        var menuName = $container.find('#aismg-new-menu-name').val().trim();

        hideMessages($container); // Clear previous status/errors

        if (!menuName) {
            showError('Please enter a name for the new menu.', $container);
            $container.find('#aismg-new-menu-name').focus();
            return;
        }

        $container.find('#aismg-menu-results input[name="menu_items[]"]:checked').each(function () {
            selectedItems.push($(this).val()); // Value is type||title||value||level
        });

        if (selectedItems.length === 0) {
            showError('Please select at least one menu item to create.', $container);
            return;
        }

        showSpinner($button);
        $button.text(aismg_ajax_object.menu_creating_text);

        $.ajax({
            url: aismg_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'aismg_create_menu',
                nonce: aismg_ajax_object.nonce,
                items_data: selectedItems, // Send structured data (renamed for clarity)
                menu_name: menuName
            },
            success: function (response) {
                if (response.success) {
                    var statusHtml = '';
                    if (response.data.menu_id && response.data.menu_edit_link) {
                        statusHtml += '<p><strong>Successfully created menu:</strong> \'' + escapeHtml(response.data.menu_name) + '\' (<a href="' + response.data.menu_edit_link + '" target="_blank">Edit Menu</a>)</p>';
                    }
                    statusHtml += '<p><strong>Menu Item Creation Results:</strong></p><ul>';
                    var itemAdded = false;
                    var itemFailed = false;

                    if (response.data.added && response.data.added.length > 0) {
                        itemAdded = true;
                        $.each(response.data.added, function (index, item) {
                            statusHtml += '<li>Added: ' + escapeHtml(item.title) + (item.type === 'page' ? ' (linked to page: ' + escapeHtml(item.page_title) + ')' : (item.type === 'custom' ? ' (custom link: ' + escapeHtml(item.url) + ')' : '')) + '</li>';
                        });
                    }
                    if (response.data.failed && response.data.failed.length > 0) {
                        itemFailed = true;
                        $.each(response.data.failed, function (index, item) {
                            statusHtml += '<li><span style="color: red;">Failed:</span> ' + escapeHtml(item.title) + ' - ' + escapeHtml(item.reason) + '</li>';
                        });
                    }
                    statusHtml += '</ul>';
                    if (itemAdded || itemFailed || response.data.menu_id) {
                        showStatus(statusHtml, $container);
                    } else if (response.data.message) {
                        showStatus('<p>' + escapeHtml(response.data.message) + '</p>', $container);
                    } else {
                        showError('Received success response but no details were provided.', $container);
                    }

                    $container.find('#aismg-menu-results').empty().hide(); // Clear results list after creation
                } else {
                    showError(response.data.message || aismg_ajax_object.error_text, $container);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Menu Creation AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                showError('AJAX error: ' + textStatus + ' - ' + errorThrown, $container);
            },
            complete: function () {
                hideSpinner($button);
                // Button is removed when results are cleared
            }
        });
    });

    // --- Utility: Escape HTML --- 
    var entityMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '/': '&#x2F;',
        '`': '&#x60;',
        '=': '&#x3D;'
    };

    function escapeHtml(string) {
        return String(string).replace(/[&<>"'`=\/]/g, function (s) {
            return entityMap[s];
        });
    }
}); 