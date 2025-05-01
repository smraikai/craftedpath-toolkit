<?php
/**
 * AI Menu Generator Feature for CraftedPath Toolkit
 *
 * @package CraftedPath_Toolkit
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CPT_AI_Menu_Generator Class
 */
class CPT_AI_Menu_Generator
{

    /**
     * Singleton instance
     * @var CPT_AI_Menu_Generator|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Initialize the feature
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Register AJAX handlers
        add_action('wp_ajax_cpt_generate_menu', array($this, 'ajax_generate_menu'));
        add_action('wp_ajax_cpt_create_wp_menu', array($this, 'ajax_create_wp_menu'));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        $screen = get_current_screen();

        // Only load on menu generator page
        if (!$screen || $screen->id !== 'craftedpath_page_cpt-aimg-menu') { // Adjusted screen ID
            return;
        }

        // Enqueue styles (Adjust path)
        wp_enqueue_style(
            'cpt-ai-menu-generator-style',
            CPT_PLUGIN_URL . 'includes/features/ai-menu-generator/css/ai-menu-generator.css', // Adjusted path
            array(),
            CPT_VERSION
        );

        // --- Enqueue SortableJS --- 
        // 1. Register the core SortableJS library
        wp_register_script(
            'sortablejs-core',
            CPT_PLUGIN_URL . 'assets/js/vendor/Sortable.min.js', // Path to the downloaded core library
            array(), // No dependencies for the core library itself
            '1.15.2', // Specify version (optional but good practice)
            true // Load in footer
        );

        // 2. Register the jQuery wrapper for SortableJS
        wp_register_script(
            'sortablejs-jquery',
            CPT_PLUGIN_URL . 'includes/features/ai-menu-generator/js/jquery-sortable.js', // Path to the wrapper
            array('jquery', 'sortablejs-core'), // Depends on jQuery and the core SortableJS library
            CPT_VERSION, // Use plugin version
            true // Load in footer
        );

        // 3. Enqueue the main feature script, now depending on the jQuery wrapper
        wp_enqueue_script(
            'cpt-ai-menu-generator-script',
            CPT_PLUGIN_URL . 'includes/features/ai-menu-generator/js/ai-menu-generator.js', // Adjusted path
            array('jquery', 'sortablejs-jquery'), // Depends on jQuery and the Sortable jQuery wrapper
            CPT_VERSION,
            true
        );
        // --- End Enqueue SortableJS ---

        // Localize script with nonce (Adjust variable name)
        wp_localize_script(
            'cpt-ai-menu-generator-script',
            'cptMenuVars', // Adjusted name
            array(
                'nonce' => wp_create_nonce('cpt_menu_nonce'), // Adjusted nonce name
                'ajaxurl' => admin_url('admin-ajax.php')
            )
        );
    }

    /**
     * Render the menu generator page
     */
    public function render_menu_page()
    {
        ?>
        <div class="wrap craftedpath-settings">
            <?php cptk_render_header_card(); ?>

            <div class="craftedpath-content">
                <?php
                ob_start();
                // Adjusted button ID
                submit_button(__('Generate Menu', 'craftedpath-toolkit'), 'primary', 'generate_menu', false, ['id' => 'cpt-generate-menu-btn']);
                $footer_html = ob_get_clean();

                cptk_render_card(
                    __('AI Menu Generator', 'craftedpath-toolkit'),
                    'dashicons-menu',
                    array($this, 'render_menu_content'),
                    $footer_html
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the menu generator content
     */
    public function render_menu_content()
    {
        ?>
        <div class="cpt-menu-generator-container">
            <p><?php esc_html_e('Generate an optimized navigation menu structure for your website using AI, based on your existing published pages.', 'craftedpath-toolkit'); ?>
            </p>

            <div class="cpt-form-row">
                <label for="menu_type"><?php esc_html_e('Menu Type', 'craftedpath-toolkit'); ?></label>
                <select id="menu_type" name="menu_type">
                    <option value="main"><?php esc_html_e('Main Navigation', 'craftedpath-toolkit'); ?></option>
                    <option value="footer"><?php esc_html_e('Footer Menu', 'craftedpath-toolkit'); ?></option>
                    <option value="mobile"><?php esc_html_e('Mobile Menu', 'craftedpath-toolkit'); ?></option>
                </select>
            </div>

            <div id="menu_results" class="cpt-results-container" style="display: none;">
                <h3><?php esc_html_e('Generated Menu Structure', 'craftedpath-toolkit'); ?></h3>
                <p><?php esc_html_e('Review the structure below. Drag & drop to reorder items before creating the menu.', 'craftedpath-toolkit'); ?>
                </p>
                <div class="menu-structure interactive-menu-list"></div>
                <div class="cpt-actions">
                    <button class="button button-secondary" id="create_wp_menu" disabled>
                        <?php esc_html_e('Create WordPress Menu', 'craftedpath-toolkit'); ?></button>
                </div>
            </div>

            <div id="menu_status" class="cpt-status-message" style="display: none;"></div>
            <div id="menu_error" class="cpt-error-message" style="display: none;"></div>
            <div id="menu_loading" class="cpt-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <p><?php esc_html_e('Generating menu with AI...', 'craftedpath-toolkit'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Get OpenAI API settings from the option
     *
     * @return array OpenAI settings array with api_key and model
     */
    public function get_openai_settings()
    {
        $options = get_option('cptk_options', array());
        // Ensure defaults are set if options are missing
        // $defaults = cptk_get_default_settings(); // REMOVED - Function doesn't exist here

        return array(
            'api_key' => isset($options['openai_api_key']) ? $options['openai_api_key'] : '',
            'model' => isset($options['openai_model']) ? $options['openai_model'] : 'gpt-4o', // Hardcoded default matching settings-page.php
        );
    }

    /**
     * Call OpenAI API directly using wp_remote_post
     *
     * @param string $prompt The prompt to send to the API.
     * @param array $options Additional options for the API call (temperature, max_tokens).
     * @param string $system_message The system message for the chat completion.
     * @return array|WP_Error The decoded JSON response body as an array or a WP_Error object.
     */
    public function call_openai_api($prompt, $options = array(), $system_message = 'You are a helpful assistant.')
    {
        $settings = $this->get_openai_settings();

        if (empty($settings['api_key'])) {
            return new \WP_Error('api_key_missing', __('OpenAI API Key is not configured in CraftedPath Toolkit settings.', 'craftedpath-toolkit'));
        }

        $default_options = [
            'model' => $settings['model'],
            'max_tokens' => 1500, // Adjusted default based on potential menu size
            'temperature' => 0.5, // Slightly creative for menu structure
        ];

        $request_options = wp_parse_args($options, $default_options);

        $headers = array(
            'Authorization' => 'Bearer ' . $settings['api_key'],
            'Content-Type' => 'application/json',
        );

        $body = array(
            'model' => $request_options['model'],
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_message,
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => floatval($request_options['temperature']), // Ensure float
            'max_tokens' => intval($request_options['max_tokens']),   // Ensure int
            'response_format' => ['type' => 'json_object'], // Request JSON object format
        );

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'headers' => $headers,
                'body' => wp_json_encode($body),
                'timeout' => 90, // Increased timeout for potentially long AI responses
                'data_format' => 'body',
            )
        );

        if (is_wp_error($response)) {
            error_log('[CPT OpenAI MenuGen] HTTP Error: ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_message = wp_remote_retrieve_response_message($response);
            $body_data = json_decode($response_body, true);
            if (isset($body_data['error']['message'])) {
                $error_message = $body_data['error']['message'];
            }
            error_log(sprintf('[CPT OpenAI MenuGen] API Error (%d): %s', $response_code, $error_message));
            error_log('[CPT OpenAI MenuGen] API Error Body: ' . $response_body);
            return new \WP_Error(
                'openai_api_error',
                sprintf(__('OpenAI API Error (%d): %s', 'craftedpath-toolkit'), $response_code, $error_message)
            );
        }

        $decoded_body = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[CPT OpenAI MenuGen] Failed to decode API JSON response. Error: ' . json_last_error_msg());
            error_log('[CPT OpenAI MenuGen] Raw Body: ' . $response_body);
            return new \WP_Error(
                'openai_response_decode_error',
                __('Failed to decode the response from OpenAI API.', 'craftedpath-toolkit')
            );
        }

        // Return the decoded body (which should be an array)
        return $decoded_body;
    }

    /**
     * AJAX handler for generating the menu structure
     */
    public function ajax_generate_menu()
    {
        check_ajax_referer('cpt_menu_nonce', 'nonce'); // Adjusted nonce name

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'craftedpath-toolkit'));
            return;
        }

        $menu_type = isset($_POST['menu_type']) ? sanitize_text_field(wp_unslash($_POST['menu_type'])) : 'main';
        // Get existing pages instead
        $existing_pages = $this->get_existing_pages_structured();
        if (empty($existing_pages)) {
            wp_send_json_error(__('No published pages found to build a menu from.', 'craftedpath-toolkit'));
            return;
        }

        // Pass existing pages to the prompt builder
        $prompt = $this->build_menu_prompt($menu_type, $existing_pages);
        $system_message = 'You are an expert website architect specializing in creating intuitive navigation menu structures based on existing website pages.'; // Updated system message

        // Pass system message to the API call
        $response = $this->call_openai_api($prompt, [], $system_message);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        // The response is now the already decoded JSON body array
        $api_response = $response;
        if (!isset($api_response['choices'][0]['message']['content'])) {
            error_log('[CPT MenuGen] Unexpected API response structure: ' . print_r($api_response, true));
            wp_send_json_error(__('Unexpected API response format.', 'craftedpath-toolkit'));
            return;
        }

        $menu_json = $api_response['choices'][0]['message']['content'];

        // Basic validation and cleaning of the JSON response
        // Find the first `{` and the last `}` to extract potential JSON string from content
        $first_brace = strpos($menu_json, '{');
        $last_brace = strrpos($menu_json, '}');

        if ($first_brace === false || $last_brace === false || $last_brace < $first_brace) {
            wp_send_json_error(__('AI response did not contain a valid structure. Please try again.', 'craftedpath-toolkit'));
            return;
        }

        $extracted_json = substr($menu_json, $first_brace, $last_brace - $first_brace + 1);
        $decoded_menu = json_decode($extracted_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("CPT AI Menu JSON Decode Error: " . json_last_error_msg() . "\nRaw Response:\n" . $menu_json);
            wp_send_json_error(__('AI response could not be parsed as valid JSON. Please try again.', 'craftedpath-toolkit'));
            return;
        }

        // Simple validation: check if it looks like a menu structure (e.g., has 'items' key)
        if (!isset($decoded_menu['items']) || !is_array($decoded_menu['items'])) {
            wp_send_json_error(__('AI response did not follow the expected menu structure format (missing \'items\' array). Please try again.', 'craftedpath-toolkit'));
            return;
        }

        wp_send_json_success(array('menu_structure' => $decoded_menu));
    }

    /**
     * Build the prompt for the OpenAI API to generate a menu structure based on existing pages.
     *
     * @param string $menu_type Type of menu (main, footer, mobile).
     * @param array $existing_pages Hierarchical array of existing page data.
     * @return string The generated prompt.
     */
    private function build_menu_prompt($menu_type, $existing_pages)
    {
        $prompt = sprintf("Generate an optimal navigation menu structure for a website's '%s' menu.", esc_html($menu_type));
        $prompt .= "\n\nBase the menu structure ONLY on the following list of existing published pages on the website. Consider the provided hierarchy (parent/child relationships) when building the menu.";

        // Format existing pages for the prompt
        $formatted_pages = $this->format_existing_pages_for_prompt($existing_pages);
        $prompt .= "\n\nExisting Pages (Hierarchy indicated by indentation):\n";
        $prompt .= $formatted_pages;

        $prompt .= sprintf("\n\nThe menu type is '%s', so tailor the items and depth accordingly. For example:", esc_html($menu_type));
        if ($menu_type === 'footer') {
            $prompt .= " Footer menus often contain essential pages like Home, Contact, Privacy Policy, Terms, etc., and might be flatter.";
        } else if ($menu_type === 'main') {
            $prompt .= " Main navigation should prioritize key pages. Aim for a depth of 1-2 levels, maximum 3. Only include pages from the provided list.";
        } else if ($menu_type === 'mobile') {
            $prompt .= " Mobile menus need to be concise. Select the most important pages from the provided list.";
        }

        $prompt .= "\n\nIMPORTANT: Only use pages titles that appear in the 'Existing Pages' list provided above.";
        $prompt .= "\n\nProvide the output ONLY as a valid JSON object with a single root key 'items'. Each item in the 'items' array should be an object with 'title' (string, matching an existing page title exactly), and optionally 'children' (an array of nested item objects for sub-menus), and 'url' (string, use the actual page permalink if available in the provided data, otherwise use '#' placeholder). Example format:";
        $prompt .= "\n{\"items\": [ {\"title\": \"Home\", \"url\": \"/\"}, {\"title\": \"About Us\", \"url\": \"/about/\", \"children\": [ {\"title\": \"Team\", \"url\": \"/about/team/\"} ] } ]}";
        $prompt .= "\n\nDo not include any explanatory text before or after the JSON object. Just the JSON.";

        return $prompt;
    }

    /**
     * Helper function to format the hierarchical list of existing pages for the prompt.
     *
     * @param array $pages Hierarchical array of page data from get_existing_pages_structured.
     * @param int $level Current indentation level.
     * @return string Formatted string list of pages.
     */
    private function format_existing_pages_for_prompt($pages, $level = 0)
    {
        $output = "";
        $indent = str_repeat("  ", $level);

        foreach ($pages as $page) {
            $output .= $indent . "- " . esc_html($page['title']) . " (URL: " . esc_html($page['url']) . ")\n";
            if (!empty($page['children'])) {
                $output .= $this->format_existing_pages_for_prompt($page['children'], $level + 1);
            }
        }
        return $output;
    }

    /**
     * Get existing published pages in a hierarchical structure.
     *
     * @return array Hierarchical array of page data (id, title, url, children).
     */
    private function get_existing_pages_structured()
    {
        $pages = get_pages(array(
            'post_status' => 'publish', // Only published pages
            'sort_column' => 'menu_order, post_title',
            'hierarchical' => 1, // Fetch in hierarchy
        ));

        if (empty($pages)) {
            return array();
        }

        // Build hierarchical array
        $page_map = array();
        foreach ($pages as $page) {
            $page_map[$page->ID] = array(
                'id' => $page->ID,
                'parent_id' => $page->post_parent,
                'title' => $page->post_title,
                'url' => get_permalink($page->ID),
                'children' => array(),
            );
        }

        $structured_pages = array();
        foreach ($page_map as $page_id => &$page_data) {
            if ($page_data['parent_id'] && isset($page_map[$page_data['parent_id']])) {
                $page_map[$page_data['parent_id']]['children'][] = &$page_data;
            } else {
                // Add top-level pages
                $structured_pages[] = &$page_data;
            }
        }
        unset($page_data); // Unset reference

        return $structured_pages;
    }

    /**
     * AJAX handler for creating the WordPress menu
     */
    public function ajax_create_wp_menu()
    {
        check_ajax_referer('cpt_menu_nonce', 'nonce'); // Adjusted nonce name

        if (!current_user_can('edit_theme_options')) { // Menu creation capability
            wp_send_json_error(__('Permission denied.', 'craftedpath-toolkit'));
            return;
        }

        $menu_structure_json = isset($_POST['menu_structure']) ? wp_unslash($_POST['menu_structure']) : '';
        $menu_name = isset($_POST['menu_name']) ? sanitize_text_field(wp_unslash($_POST['menu_name'])) : 'AI Generated Menu';

        if (empty($menu_structure_json)) {
            wp_send_json_error(__('Menu structure data is missing.', 'craftedpath-toolkit'));
            return;
        }

        $menu_structure = json_decode($menu_structure_json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($menu_structure['items'])) {
            wp_send_json_error(__('Invalid menu structure format.', 'craftedpath-toolkit'));
            return;
        }

        // Create the menu
        $menu_object = wp_create_nav_menu($menu_name);

        if (is_wp_error($menu_object)) {
            // Handle potential error where menu name already exists
            if ($menu_object->get_error_code() === 'menu_exists') {
                $existing_menu = wp_get_nav_menu_object($menu_name);
                if ($existing_menu) {
                    $menu_id = $existing_menu->term_id;
                    // Optional: Ask user if they want to overwrite or clear existing items?
                    // For now, we'll just add to it, which might duplicate items.
                    // A better approach might be to delete existing items first or create a uniquely named menu.
                    // Let's create a unique name to avoid conflicts:
                    $menu_name .= '-' . time();
                    $menu_object = wp_create_nav_menu($menu_name);
                    if (is_wp_error($menu_object)) {
                        wp_send_json_error('Failed to create menu: ' . $menu_object->get_error_message());
                        return;
                    }
                    $menu_id = $menu_object;
                } else {
                    wp_send_json_error('Failed to create menu: ' . $menu_object->get_error_message());
                    return;
                }
            } else {
                wp_send_json_error('Failed to create menu: ' . $menu_object->get_error_message());
                return;
            }
        } else {
            $menu_id = $menu_object; // Contains the term_id on success
        }

        // Add items to the menu
        $result = $this->add_menu_items($menu_structure['items'], $menu_id);

        if (is_wp_error($result)) {
            // Clean up the potentially created menu if adding items failed?
            // wp_delete_nav_menu($menu_id);
            wp_send_json_error('Failed to add menu items: ' . $result->get_error_message());
        } else {
            $menu_edit_url = admin_url('nav-menus.php?action=edit&menu=' . $menu_id);
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Menu "%s" created successfully. %s', 'craftedpath-toolkit'),
                    esc_html(wp_get_nav_menu_object($menu_id)->name), // Get the actual final menu name
                    sprintf('<a href="%s" target="_blank">%s</a>', esc_url($menu_edit_url), __('Edit Menu', 'craftedpath-toolkit'))
                ),
                'menu_id' => $menu_id,
                'edit_url' => $menu_edit_url
            ));
        }
    }

    /**
     * Recursively add items to a WordPress navigation menu.
     *
     * @param array $items Array of menu item objects from the AI response.
     * @param int $menu_id The ID of the menu being edited.
     * @param int $parent_id The ID of the parent menu item (0 for top level).
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private function add_menu_items($items, $menu_id, $parent_id = 0)
    {
        foreach ($items as $item) {
            if (!isset($item['title']))
                continue; // Skip items without titles

            // Basic sanitization
            $title = sanitize_text_field($item['title']);
            $url = isset($item['url']) ? esc_url_raw($item['url']) : '#'; // Default to # if no URL
            if (empty($url))
                $url = '#';

            // Prepare item data for wp_update_nav_menu_item
            $item_data = array(
                'menu-item-title' => $title,
                'menu-item-url' => $url,
                'menu-item-status' => 'publish',
                'menu-item-parent-id' => $parent_id,
                // 'menu-item-type' => 'custom', // Let WP figure it out or set explicitly if needed
            );

            $item_id = wp_update_nav_menu_item($menu_id, 0, $item_data);

            if (is_wp_error($item_id)) {
                error_log("CPT AI Menu: Error adding item '{$title}': " . $item_id->get_error_message());
                // Decide whether to continue or return the error
                // return $item_id; // Stop on first error
                continue; // Log error and continue with other items
            }

            // If the item has children, recursively add them
            if (!empty($item['children']) && is_array($item['children'])) {
                $result = $this->add_menu_items($item['children'], $menu_id, $item_id);
                if (is_wp_error($result)) {
                    // Log or handle nested error
                    // return $result; // Propagate error up
                }
            }
        }
        return true; // Indicate success (or partial success if errors were skipped)
    }


}