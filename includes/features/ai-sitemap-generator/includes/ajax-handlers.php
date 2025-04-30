<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// --- AJAX Handlers --- 

// Helper function to call OpenAI API
function aismg_call_openai_api($prompt_text, $system_message)
{
    $api_key = get_option('aismg_openai_api_key');
    $model = get_option('aismg_llm_model', 'gpt-3.5-turbo');

    if (empty($api_key)) {
        return new WP_Error('api_key_missing', __('OpenAI API Key is missing in settings.', 'ai-sitemap-menu-generator'));
    }

    $messages = [
        ['role' => 'system', 'content' => $system_message],
        ['role' => 'user', 'content' => $prompt_text]
    ];

    $request_args = [
        'method' => 'POST',
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => wp_json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.5, // Lower temp for more predictable structure output
            'max_tokens' => 300, // Adjust as needed
        ]),
        'timeout' => 60, // Increased timeout
    ];

    error_log("AISMG: Sending request to OpenAI. Model: $model. Prompt: " . substr($prompt_text, 0, 100) . "...");
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $request_args);

    if (is_wp_error($response)) {
        error_log('AISMG: OpenAI API WP Error: ' . $response->get_error_message());
        return new WP_Error('api_request_failed', __('Error communicating with OpenAI API.', 'ai-sitemap-menu-generator') . ' (' . $response->get_error_code() . ')');
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $decoded_body = json_decode($response_body, true);

    if ($response_code !== 200) {
        $error_message = isset($decoded_body['error']['message']) ? $decoded_body['error']['message'] : __('Unknown OpenAI API error.', 'ai-sitemap-menu-generator');
        error_log('AISMG: OpenAI API HTTP Error (' . $response_code . '): ' . $response_body);
        return new WP_Error('api_http_error', sprintf(__('OpenAI API Error (%d): %s', 'ai-sitemap-menu-generator'), $response_code, esc_html($error_message)));
    }

    if (!isset($decoded_body['choices'][0]['message']['content'])) {
        error_log('AISMG: OpenAI API response format error: ' . $response_body);
        return new WP_Error('api_response_format', __('Unexpected response format from OpenAI API.', 'ai-sitemap-menu-generator'));
    }

    $content = trim($decoded_body['choices'][0]['message']['content']);
    error_log("AISMG: Received content from OpenAI: " . substr($content, 0, 200) . "...");
    return $content;
}


/**
 * AJAX handler for generating the sitemap structure.
 */
function aismg_ajax_generate_sitemap()
{
    check_ajax_referer('aismg_ajax_nonce', 'nonce');

    $user_prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';

    if (empty($user_prompt)) {
        wp_send_json_error(['message' => __('Sitemap description cannot be empty.', 'ai-sitemap-menu-generator')]);
        return;
    }

    // Updated system prompt for hierarchy, slugs, and WP Page focus
    $system_message = <<<PROMPT
# Role: SEO-Aware WordPress **Page** Sitemap Architect

# Task:
Generate a hierarchical list of **WordPress Pages** and SEO-optimized URL slugs for a website based on the user's description. Focus on creating a logical site structure suitable for navigation.

# Output Format:
Respond ONLY with a bulleted list. Use indentation (two spaces per level) to indicate parent/child relationships. 
Each line MUST follow this exact format:
- `[Page Title] (slug: [seo-friendly-slug])`

Example (for a standard business site):
- About Us (slug: about-us)
  - Our Mission (slug: our-mission)
  - Our Team (slug: our-team)
- Services (slug: services)
  - Service A (slug: service-a)
  - Service B (slug: service-b)
- Contact Us (slug: contact)

# Rules:
*   **FOCUS ON PAGES:** Generate titles and slugs for **WordPress Pages** only (e.g., Home, About, Services, Contact, Portfolio, specific service pages, landing pages). 
*   **AVOID BLOG POSTS/ARCHIVES:** Do NOT suggest individual blog posts, blog category pages, tag archives, or date archives unless the user specifically requests a structure for a blog *section page* (e.g., a single page named "Blog" or "News").
*   Generate relevant page titles based on the user request.
*   Create short, descriptive, lowercase, hyphenated slugs for each page.
*   Use two spaces of indentation to show child pages.
*   Create a logical hierarchy suitable for website navigation.
*   Adhere strictly to the output format: `- [Title] (slug: [slug])`.
*   Do NOT include introductions, explanations, or summaries. Just the list.
*   If the request is unclear or unsuitable, respond with the single word: NONE.

# User Request:
(The user's description of the website will be provided here)
PROMPT;

    $llm_response = aismg_call_openai_api($user_prompt, $system_message);

    if (is_wp_error($llm_response)) {
        wp_send_json_error(['message' => $llm_response->get_error_message()]);
        return;
    }

    if (empty($llm_response) || strtoupper(trim($llm_response)) === 'NONE') {
        wp_send_json_error(['message' => __('The AI could not generate a sitemap based on your description. Please try refining your prompt.', 'ai-sitemap-menu-generator')]);
        return;
    }

    // Updated parsing logic for hierarchy and slugs
    $structured_pages = [];
    $lines = preg_split('/\r\n|\r|\n/', $llm_response);
    $pattern = '/^(\s*)[\*\-\–\—\+]\s*(.+?)\s+\(slug:\s*([a-z0-9\-]+)\s*\)$/u';
    $last_parent_indices = [-1]; // Stack to keep track of the index of the parent at each level

    foreach ($lines as $line) {
        if (preg_match($pattern, $line, $matches)) {
            $indentation = strlen($matches[1]);
            $level = $indentation / 2; // Assuming 2 spaces per level
            $title = trim($matches[2]);
            $slug = trim($matches[3]);

            $page_data = [
                'title' => $title,
                'slug' => $slug,
                'level' => $level,
                'children' => [] // Initialize children array
            ];

            // Adjust stack based on current level
            while ($level < count($last_parent_indices) - 1) {
                array_pop($last_parent_indices);
            }

            // Find the parent index from the stack
            $parent_index = end($last_parent_indices);

            if ($parent_index === -1) {
                // Top-level page
                $structured_pages[] = $page_data;
                $current_index = count($structured_pages) - 1;
            } else {
                // Child page - Need to find the parent in the nested structure
                // This requires navigating the structure based on the parent indices stored.
                // For simplicity in this pass, we'll store a temporary parent_title if possible,
                // but a full tree build might be better.
                // Let's rethink: Store flat list with level and parent_ref (index of parent in flat list)?

                // --- Simpler approach: Store flat list with level and parent index --- 
                $page_data['parent_index'] = $parent_index; // Store index of parent in the flat list
                $structured_pages[] = $page_data;
                $current_index = count($structured_pages) - 1;
            }

            // Update the stack for the current level
            if ($level >= count($last_parent_indices) - 1) {
                $last_parent_indices[$level + 1] = $current_index; // Store current index for next level
            }

        } else {
            // Optional: Log lines that don't match the pattern
            if (!empty(trim($line))) { // Avoid logging empty lines
                error_log("AISMG: Failed to parse sitemap line: " . $line);
            }
        }
    }

    if (empty($structured_pages)) {
        wp_send_json_error(['message' => __('The AI response did not contain a recognizable list of pages in the expected format.', 'ai-sitemap-menu-generator') . ' Raw: ' . esc_html($llm_response)]);
        return;
    }

    // Note: We now send back a flat list with level and potential parent_index
    // The JS will need to interpret this for display and sending data for creation.
    wp_send_json_success(['pages' => $structured_pages]);
}
add_action('wp_ajax_aismg_generate_sitemap', 'aismg_ajax_generate_sitemap');


/**
 * AJAX handler for creating WordPress pages from the sitemap list.
 */
function aismg_ajax_create_pages()
{
    check_ajax_referer('aismg_ajax_nonce', 'nonce');

    if (!current_user_can('publish_pages')) {
        wp_send_json_error(['message' => __('You do not have permission to create pages.', 'ai-sitemap-menu-generator')]);
        return;
    }

    // Updated: Receive structured data
    $selected_pages_data = isset($_POST['pages_data']) && is_array($_POST['pages_data']) ? $_POST['pages_data'] : [];

    if (empty($selected_pages_data)) {
        wp_send_json_error(['message' => __('No pages selected for creation.', 'ai-sitemap-menu-generator')]);
        return;
    }

    $created_pages_map = []; // Map original index => new page ID
    $processed_pages = []; // Store processed data with parent IDs resolved later
    $skipped_pages = [];
    $failed_pages = [];

    // --- First Pass: Create all pages without parents, mapping original index to new ID --- 
    foreach ($selected_pages_data as $index => $page_string) {
        $parts = explode('||', $page_string, 4);
        if (count($parts) !== 4) {
            error_log("AISMG: Malformed page data string: " . $page_string);
            $failed_pages[] = ['title' => 'Invalid Data', 'error' => 'Malformed data received.'];
            $processed_pages[$index] = ['status' => 'failed'];
            continue;
        }

        $title = sanitize_text_field(wp_unslash($parts[0]));
        $slug = sanitize_title(wp_unslash($parts[1])); // Use sanitize_title for slugs
        $level = intval($parts[2]);
        $parent_index = intval($parts[3]); // Original index of the parent

        if (empty($title) || empty($slug)) {
            error_log("AISMG: Missing title or slug for page data: " . $page_string);
            $failed_pages[] = ['title' => $title ?: 'Missing Title', 'error' => 'Missing title or slug.'];
            $processed_pages[$index] = ['status' => 'failed'];
            continue;
        }

        // Check if page already exists (using title OR slug for more robustness?)
        // Let's stick to title for now as slugs might be regenerated by WP if duplicate
        $existing_page = get_page_by_title($title, OBJECT, 'page');

        if ($existing_page) {
            $skipped_pages[] = [
                'title' => $title,
                'id' => $existing_page->ID,
                'edit_link' => get_edit_post_link($existing_page->ID, 'raw'),
                'view_link' => get_permalink($existing_page->ID)
            ];
            $created_pages_map[$index] = $existing_page->ID; // Map existing page ID
            $processed_pages[$index] = ['status' => 'skipped', 'id' => $existing_page->ID, 'parent_index' => $parent_index];
            continue; // Skip creation
        }

        // Create page (initially without parent)
        $new_page_args = [
            'post_title' => $title,
            'post_name' => $slug, // Use the suggested slug
            'post_content' => '<!-- Automatically generated page -->',
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'post_type' => 'page',
            'post_parent' => 0 // Set parent in the second pass
        ];

        $new_page_id = wp_insert_post($new_page_args, true);

        if (is_wp_error($new_page_id)) {
            $failed_pages[] = [
                'title' => $title,
                'error' => $new_page_id->get_error_message()
            ];
            error_log("AISMG: Failed to create page '$title': " . $new_page_id->get_error_message());
            $processed_pages[$index] = ['status' => 'failed'];
        } else {
            // Store successful creation info
            $created_pages_map[$index] = $new_page_id;
            $processed_pages[$index] = ['status' => 'created', 'id' => $new_page_id, 'parent_index' => $parent_index, 'title' => $title];
            error_log("AISMG: Successfully created page '$title' (ID: $new_page_id) with slug '$slug'. Parent to be set.");
        }
    }

    // --- Second Pass: Update parents for newly created pages --- 
    $final_created_list = [];

    foreach ($processed_pages as $index => $page_info) {
        // Only process pages that were successfully created in the first pass
        if ($page_info['status'] === 'created' && isset($page_info['parent_index']) && $page_info['parent_index'] !== -1) {
            $parent_original_index = $page_info['parent_index'];

            // Check if the parent page (based on original index) was successfully created or skipped (exists)
            if (isset($created_pages_map[$parent_original_index])) {
                $parent_page_id = $created_pages_map[$parent_original_index];
                $current_page_id = $page_info['id'];

                // Update the post parent
                $update_args = [
                    'ID' => $current_page_id,
                    'post_parent' => $parent_page_id,
                ];
                $update_result = wp_update_post($update_args, true);

                if (is_wp_error($update_result)) {
                    error_log("AISMG: Failed to set parent (ID: $parent_page_id) for page '$page_info[title]' (ID: $current_page_id): " . $update_result->get_error_message());
                    // Add to failed list or update status?
                    $failed_pages[] = ['title' => $page_info['title'], 'error' => 'Failed to set parent page.'];
                    // Remove from created map? Or maybe just don't add to final list
                    // Let's mark it as failed setting parent
                    $processed_pages[$index]['status'] = 'failed_parent';
                } else {
                    error_log("AISMG: Successfully set parent (ID: $parent_page_id) for page '$page_info[title]' (ID: $current_page_id).");
                }
            } else {
                error_log("AISMG: Could not set parent for page '$page_info[title]' (ID: {$page_info['id']}) because parent with original index $parent_original_index was not created or found.");
                $failed_pages[] = ['title' => $page_info['title'], 'error' => 'Parent page was not created or found.'];
                $processed_pages[$index]['status'] = 'failed_parent_missing';
            }
        }

        // Collect final created page details for the success message
        if ($page_info['status'] === 'created' || $page_info['status'] === 'skipped') {
            $page_id_for_links = $page_info['id'];
            $final_created_list[] = [
                'title' => isset($page_info['title']) ? $page_info['title'] : get_the_title($page_id_for_links), // Get title if only skipped
                'id' => $page_id_for_links,
                'edit_link' => get_edit_post_link($page_id_for_links, 'raw'),
                'view_link' => get_permalink($page_id_for_links)
            ];
        }
    }

    $message = sprintf(
        __('Page creation process complete. Created/Updated: %d, Skipped: %d, Failed: %d', 'ai-sitemap-menu-generator'),
        count($final_created_list) - count($skipped_pages), // Subtract skipped from final list count for accurate created count
        count($skipped_pages),
        count($failed_pages)
    );

    wp_send_json_success([
        'message' => $message,
        // Send final lists for accurate reporting
        'created' => $final_created_list, // Contains successfully created/updated pages
        'skipped' => $skipped_pages,    // Contains skipped pages
        'failed' => $failed_pages      // Contains pages failed during creation or parent update
    ]);
}
add_action('wp_ajax_aismg_create_pages', 'aismg_ajax_create_pages');


/**
 * AJAX handler for generating the menu structure.
 */
function aismg_ajax_generate_menu()
{
    check_ajax_referer('aismg_ajax_nonce', 'nonce');

    $user_prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';

    if (empty($user_prompt)) {
        wp_send_json_error(['message' => __('Menu description cannot be empty.', 'ai-sitemap-menu-generator')]);
        return;
    }

    // Fetch existing page titles to help the LLM
    $existing_pages = get_pages();
    $page_titles = [];
    if ($existing_pages) {
        foreach ($existing_pages as $page) {
            $page_titles[] = $page->post_title;
        }
    }
    $page_list_string = !empty($page_titles) ? "Available Pages:\n" . implode("\n", array_map(function ($t) {
        return "- $t";
    }, $page_titles)) : "No existing pages found.";


    // Updated System Prompt for Nested Menus
    $system_message = <<<PROMPT
# Role: WordPress Hierarchical Menu Structure Assistant

# Task:
Generate a hierarchical navigation menu structure based on the user's request and the provided 'Available Pages' list. Use indentation to represent sub-menu items.

# Input:
1.  **User Request:** A description of the desired menu.
2.  **Available Pages:** A list of existing page titles on the WordPress site.

# Output Format:
Respond ONLY with a bulleted list. Use indentation (two spaces per level) to indicate sub-items.
Each line MUST follow one of these formats:
- `[Page Title] (page: [Exact Page Title from Available Pages])`
- `[Link Text] (custom: [URL])`

Example Output (with nesting):
- Home (page: Home)
- About Us (page: About Us)
  - Our Mission (page: Our Mission)
  - Our Team (page: Our Team)
- Services (page: Services)
  - Web Design (page: Web Design)
  - SEO Services (page: SEO Services)
  - Consulting (custom: /consulting)
- Contact (page: Contact)

# Critical Rules:
*   **PRIORITIZE EXISTING PAGES:** Wherever the user's request matches a page title in the 'Available Pages' list, use the `(page: ...)` format.
*   **USE INDENTATION:** Use two spaces per level to indicate sub-menu items.
*   **STRICT FORMAT:** Adhere strictly to the specified output formats.
*   **NO EXTRA TEXT:** Do not include introductions, explanations, apologies, or summaries. Just the bulleted list.
*   **NONE:** If no suitable menu can be generated, respond with the single word: `NONE`.

# Available Pages:
{$page_list_string}

# User Request:
(The user's goal will be provided in the next message)
PROMPT;

    $llm_response = aismg_call_openai_api($user_prompt, $system_message);

    if (is_wp_error($llm_response)) {
        wp_send_json_error(['message' => $llm_response->get_error_message()]);
        return;
    }

    if (empty($llm_response) || strtoupper(trim($llm_response)) === 'NONE') {
        wp_send_json_error(['message' => __('The AI could not generate a menu based on your description. Please try refining your prompt.', 'ai-sitemap-menu-generator')]);
        return;
    }

    // Updated Parsing for Hierarchy
    $structured_menu_items = [];
    $lines = preg_split('/\r\n|\r|\n/', $llm_response);
    // Updated pattern to capture indentation
    $pattern = '/^(\s*)[\*\-\–\—\+]\s*(.+?)\s+\((page|custom):\s*(.+)\)$/u';

    foreach ($lines as $line) {
        if (preg_match($pattern, $line, $matches)) {
            $indentation = strlen($matches[1]);
            $level = $indentation / 2; // Assuming 2 spaces per level
            $title = trim($matches[2]);
            $type = trim($matches[3]); // 'page' or 'custom'
            $value = trim($matches[4]); // Page Title or URL

            if (!empty($title) && !empty($type) && !empty($value)) {
                if ($type === 'custom' && !filter_var($value, FILTER_VALIDATE_URL) && substr($value, 0, 1) !== '/') { // Allow relative URLs starting with /
                    error_log("AISMG: Skipping menu item '$title' - Invalid custom URL/path '$value'");
                    continue;
                }

                $structured_menu_items[] = [
                    'title' => $title,
                    'type' => $type,
                    'value' => $value,
                    'level' => $level // Store the hierarchy level
                ];
            }
        } else {
            if (!empty(trim($line))) { // Avoid logging empty lines
                error_log("AISMG: Failed to parse menu line: " . $line);
            }
        }
    }

    if (empty($structured_menu_items)) {
        wp_send_json_error(['message' => __('The AI response did not contain a recognizable list of menu items in the expected format.', 'ai-sitemap-menu-generator') . ' Raw: ' . esc_html($llm_response)]);
        return;
    }

    // Send structured data including level
    wp_send_json_success(['menu_items' => $structured_menu_items]);
}
add_action('wp_ajax_aismg_generate_menu', 'aismg_ajax_generate_menu');


/**
 * AJAX handler for creating the WordPress menu and its items.
 */
function aismg_ajax_create_menu()
{
    check_ajax_referer('aismg_ajax_nonce', 'nonce');

    if (!current_user_can('edit_theme_options')) {
        wp_send_json_error(['message' => __('You do not have permission to manage menus.', 'ai-sitemap-menu-generator')]);
        return;
    }

    // Updated: Receive structured item data
    $selected_items_data = isset($_POST['items_data']) && is_array($_POST['items_data']) ? $_POST['items_data'] : [];
    $menu_name = isset($_POST['menu_name']) ? sanitize_text_field(wp_unslash($_POST['menu_name'])) : '';

    if (empty($menu_name)) {
        wp_send_json_error(['message' => __('Menu name cannot be empty.', 'ai-sitemap-menu-generator')]);
        return;
    }
    if (empty($selected_items_data)) {
        wp_send_json_error(['message' => __('No menu items selected for creation.', 'ai-sitemap-menu-generator')]);
        return;
    }

    // Create the menu if it doesn't exist
    $menu_object = wp_get_nav_menu_object($menu_name);
    $menu_id = false;

    if (!$menu_object) {
        $menu_id = wp_create_nav_menu($menu_name);
        if (is_wp_error($menu_id)) {
            error_log("AISMG: Failed to create menu '$menu_name': " . $menu_id->get_error_message());
            wp_send_json_error(['message' => __('Failed to create menu:', 'ai-sitemap-menu-generator') . ' ' . $menu_id->get_error_message()]);
            return;
        }
        error_log("AISMG: Successfully created menu '$menu_name' (ID: $menu_id).");
    } else {
        $menu_id = $menu_object->term_id;
        error_log("AISMG: Using existing menu '$menu_name' (ID: $menu_id). Existing items might be duplicated if run again.");
        // Note: We are adding to an existing menu if the name matches.
    }

    if (!$menu_id) {
        wp_send_json_error(['message' => __('Could not create or find the specified menu ID.', 'ai-sitemap-menu-generator')]);
        return;
    }

    $added_items_map = []; // Map original index => new menu item DB ID
    $processed_items = []; // Store processed data with hierarchy info
    $failed_items = [];

    // --- First Pass: Create all menu items without parents --- 
    foreach ($selected_items_data as $index => $item_string) {
        $parts = explode('||', $item_string, 4); // Expect 4 parts now
        if (count($parts) !== 4) {
            error_log("AISMG: Malformed menu item data string: " . $item_string);
            $failed_items[] = ['title' => 'Invalid Data', 'reason' => 'Malformed item data received.'];
            $processed_items[$index] = ['status' => 'failed'];
            continue;
        }

        $type = sanitize_text_field(wp_unslash($parts[0]));
        $title = sanitize_text_field(wp_unslash($parts[1]));
        $value = wp_unslash($parts[2]); // Sanitize based on type below
        $level = intval($parts[3]);

        $menu_item_data = [
            'menu-item-title' => $title,
            'menu-item-status' => 'publish',
            'menu-item-parent-id' => 0 // Set parent in second pass
        ];

        $item_details_for_log = ['title' => $title, 'type' => $type, 'value' => $value, 'level' => $level];
        $linked_page_title = null;
        $final_url = null;

        if ($type === 'page') {
            $page_title_to_find = sanitize_text_field($value);
            $page_object = get_page_by_title($page_title_to_find, OBJECT, 'page');
            if ($page_object) {
                $menu_item_data['menu-item-type'] = 'post_type';
                $menu_item_data['menu-item-object'] = 'page';
                $menu_item_data['menu-item-object-id'] = $page_object->ID;
                $linked_page_title = $page_object->post_title;
            } else {
                error_log("AISMG: Failed to add menu item '$title' - Page '$page_title_to_find' not found.");
                $failed_items[] = ['title' => $title, 'reason' => sprintf(__('Page \'%s\' not found.', 'ai-sitemap-menu-generator'), $page_title_to_find)];
                $processed_items[$index] = ['status' => 'failed'];
                continue;
            }
        } elseif ($type === 'custom') {
            // Allow relative paths starting with / or full URLs
            if (substr($value, 0, 1) === '/') {
                $url = esc_url_raw(home_url($value)); // Make relative path absolute
            } else {
                $url = esc_url_raw($value);
            }

            if (!empty($url)) {
                $menu_item_data['menu-item-type'] = 'custom';
                $menu_item_data['menu-item-url'] = $url;
                $final_url = $url;
            } else {
                error_log("AISMG: Failed to add menu item '$title' - Invalid custom URL/path '$value'.");
                $failed_items[] = ['title' => $title, 'reason' => __('Invalid custom URL/path.', 'ai-sitemap-menu-generator')];
                $processed_items[$index] = ['status' => 'failed'];
                continue;
            }
        } else {
            error_log("AISMG: Failed to add menu item '$title' - Unknown type '$type'.");
            $failed_items[] = ['title' => $title, 'reason' => __('Unknown item type.', 'ai-sitemap-menu-generator')];
            $processed_items[$index] = ['status' => 'failed'];
            continue;
        }

        // Add the menu item (without parent ID yet)
        $item_db_id = wp_update_nav_menu_item($menu_id, 0, $menu_item_data);

        if (is_wp_error($item_db_id)) {
            error_log("AISMG: Failed to add menu item '$title': " . $item_db_id->get_error_message());
            $failed_items[] = ['title' => $title, 'reason' => $item_db_id->get_error_message()];
            $processed_items[$index] = ['status' => 'failed'];
        } elseif ($item_db_id === false || $item_db_id === 0) {
            error_log("AISMG: Failed to add menu item '$title' (wp_update_nav_menu_item returned false or 0).");
            $failed_items[] = ['title' => $title, 'reason' => __('Failed to add item to menu (API returned error).', 'ai-sitemap-menu-generator')];
            $processed_items[$index] = ['status' => 'failed'];
        } else {
            $added_items_map[$index] = $item_db_id; // Map original index to new item DB ID
            // Store the determined menu item args for the second pass
            $processed_items[$index] = [
                'status' => 'created',
                'db_id' => $item_db_id,
                'level' => $level,
                'details' => $item_details_for_log,
                'item_args' => $menu_item_data // Store the args used for creation
            ];
            if ($linked_page_title)
                $processed_items[$index]['details']['page_title'] = $linked_page_title;
            if ($final_url)
                $processed_items[$index]['details']['url'] = $final_url;
            error_log("AISMG: Successfully created menu item '$title' (DB ID: $item_db_id). Parent to be set.");
        }
    }

    // --- Second Pass: Update parent IDs for nested items --- 
    $parent_stack = []; // Stack to keep track of parent DB IDs at each level
    $final_added_list = []; // For reporting success

    foreach ($processed_items as $index => $item_info) {
        if ($item_info['status'] !== 'created') {
            continue; // Skip items that failed in the first pass
        }

        $current_level = $item_info['level'];
        $current_db_id = $item_info['db_id'];
        $original_item_args = $item_info['item_args']; // Retrieve original args

        // Adjust parent stack based on current level
        while ($current_level < count($parent_stack) - 1) {
            array_pop($parent_stack);
        }

        // Get parent DB ID from the stack (if it exists)
        $parent_db_id = isset($parent_stack[$current_level]) ? $parent_stack[$current_level] : 0;

        if ($parent_db_id > 0) {
            // Update the menu item with the correct parent ID, *including original args*
            $update_data = $original_item_args;
            $update_data['menu-item-parent-id'] = $parent_db_id; // Set/override parent ID

            // Ensure other crucial fields aren't lost (though they should be in $original_item_args)
            if (!isset($update_data['menu-item-status'])) {
                $update_data['menu-item-status'] = 'publish';
            }

            $update_result = wp_update_nav_menu_item($menu_id, $current_db_id, $update_data);

            if (is_wp_error($update_result) || $update_result === false) {
                $error_msg = is_wp_error($update_result) ? $update_result->get_error_message() : 'wp_update_nav_menu_item failed';
                error_log("AISMG: Failed to set parent (ID: $parent_db_id) for menu item '{$item_info['details']['title']}' (DB ID: $current_db_id): " . $error_msg);
                $failed_items[] = ['title' => $item_info['details']['title'], 'reason' => 'Failed to set parent item.'];
                $processed_items[$index]['status'] = 'failed_parent'; // Mark as failed
            } else {
                error_log("AISMG: Successfully set parent (ID: $parent_db_id) for menu item '{$item_info['details']['title']}' (DB ID: $current_db_id).");
            }
        }

        // Update the parent stack for the *next* level
        $parent_stack[$current_level + 1] = $current_db_id;

        // Add to final success list if parent update didn't fail
        if ($processed_items[$index]['status'] !== 'failed_parent') {
            $final_added_list[] = $item_info['details'];
        }
    }

    $message = sprintf(
        __('Menu item creation process complete. Added/Updated: %d, Failed: %d', 'ai-sitemap-menu-generator'),
        count($final_added_list),
        count($failed_items)
    );

    wp_send_json_success([
        'message' => $message,
        'menu_id' => $menu_id,
        'menu_name' => $menu_name,
        'menu_edit_link' => admin_url('nav-menus.php?action=edit&menu=' . $menu_id),
        'added' => $final_added_list,
        'failed' => $failed_items
    ]);
}
add_action('wp_ajax_aismg_create_menu', 'aismg_ajax_create_menu');
?>