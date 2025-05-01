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

        // Enqueue scripts (Adjust path)
        wp_enqueue_script(
            'cpt-ai-menu-generator-script',
            CPT_PLUGIN_URL . 'includes/features/ai-menu-generator/js/ai-menu-generator.js', // Adjusted path
            array('jquery'),
            CPT_VERSION,
            true
        );

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
            <p><?php esc_html_e('Generate an optimized navigation menu structure for your website using AI.', 'craftedpath-toolkit'); ?>
            </p>

            <div class="cpt-form-row">
                <label for="menu_type"><?php esc_html_e('Menu Type', 'craftedpath-toolkit'); ?></label>
                <select id="menu_type" name="menu_type">
                    <option value="main"><?php esc_html_e('Main Navigation', 'craftedpath-toolkit'); ?></option>
                    <option value="footer"><?php esc_html_e('Footer Menu', 'craftedpath-toolkit'); ?></option>
                    <option value="mobile"><?php esc_html_e('Mobile Menu', 'craftedpath-toolkit'); ?></option>
                </select>
            </div>

            <div class="cpt-form-row">
                <label
                    for="use_existing_sitemap"><?php esc_html_e('Use Existing Sitemap (Optional)', 'craftedpath-toolkit'); ?></label>
                <input type="checkbox" id="use_existing_sitemap" name="use_existing_sitemap">
                <span
                    class="description"><?php esc_html_e('Use previously generated page structure as base.', 'craftedpath-toolkit'); ?></span>
                <textarea id="existing_sitemap_data" name="existing_sitemap_data" rows="8"
                    style="display:none; width: 100%; margin-top: 5px;"
                    placeholder="<?php esc_attr_e('Paste JSON sitemap structure here if not using checkbox...', 'craftedpath-toolkit'); ?>"></textarea>
            </div>

            <div id="menu_results" class="cpt-results-container" style="display: none;">
                <h3><?php esc_html_e('Generated Menu Structure', 'craftedpath-toolkit'); ?></h3>
                <pre><code class="language-json menu-structure"></code></pre>
                <div class="cpt-actions">
                    <button class="button button-secondary"
                        id="create_wp_menu"><?php esc_html_e('Create WordPress Menu', 'craftedpath-toolkit'); ?></button>
                    <button class="button button-secondary"
                        id="copy_menu_json"><?php esc_html_e('Copy JSON', 'craftedpath-toolkit'); ?></button>
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
        $sitemap_json = isset($_POST['sitemap_data']) ? wp_unslash($_POST['sitemap_data']) : ''; // Allow JSON
        $use_sitemap = isset($_POST['use_sitemap']) && $_POST['use_sitemap'] === 'true';

        $sitemap_data = null;
        if ($use_sitemap && !empty($sitemap_json)) {
            $sitemap_data = json_decode($sitemap_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(__('Invalid JSON provided for existing sitemap.', 'craftedpath-toolkit'));
                return;
            }
        } elseif ($use_sitemap) {
            // Optionally, fetch a saved sitemap from options or transient if checkbox is true but no JSON pasted
            // For now, we assume JSON is provided if the checkbox is checked AND text area is filled
            // Or potentially retrieve from the Page Generator's last run?
            // Requires inter-feature communication - maybe via options/transients.
            $sitemap_data = get_transient('cpt_last_generated_sitemap'); // Example: Get from transient
            if (!$sitemap_data) {
                // Send a specific notice instead of error? Or let the prompt handle it?
                // wp_send_json_error(__('Sitemap data not found. Please generate pages first or provide JSON.', 'craftedpath-toolkit'));
                // return;
                $sitemap_data = null; // Proceed without sitemap if not found
            }
        }


        $prompt = $this->build_menu_prompt($menu_type, $sitemap_data);
        $system_message = 'You are an expert website architect specializing in creating intuitive navigation menu structures.'; // Define system message

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
     * Build the prompt for the OpenAI API to generate a menu structure.
     *
     * @param string $menu_type Type of menu (main, footer, mobile).
     * @param array|null $sitemap_data Optional sitemap data (associative array).
     * @return string The generated prompt.
     */
    private function build_menu_prompt($menu_type, $sitemap_data)
    {
        $prompt = sprintf("Generate an optimal navigation menu structure for a website's '%s' menu.", esc_html($menu_type));

        if (!empty($sitemap_data)) {
            $formatted_sitemap = $this->format_sitemap_for_prompt($sitemap_data);
            $prompt .= "\n\nBase the menu structure primarily on the following proposed sitemap (represented as nested JSON):";
            $prompt .= "\n" . $formatted_sitemap;
            $prompt .= "\n\nConsider these pages when deciding the menu items and hierarchy.";
        } else {
            $prompt .= "\n\nAssume a standard website structure if no sitemap is provided. Include common top-level pages like Home, About, Services, Blog, Contact.";
        }

        $prompt .= sprintf("\n\nThe menu type is '%s', so tailor the items and depth accordingly.", esc_html($menu_type));
        if ($menu_type === 'footer') {
            $prompt .= " Footer menus are often flatter and may include links like Privacy Policy, Terms of Service, and secondary navigation.";
        } else if ($menu_type === 'main') {
            $prompt .= " Main navigation should prioritize key user tasks and top-level pages. Aim for a depth of 1-2 levels, maximum 3.";
        } else if ($menu_type === 'mobile') {
            $prompt .= " Mobile menus need to be concise and easy to navigate on small screens. It might be similar to the main menu but potentially simplified.";
        }

        $prompt .= "\n\nProvide the output ONLY as a valid JSON object with a single root key 'items'. Each item in the 'items' array should be an object with 'title' (string, navigation label), and optionally 'children' (an array of nested item objects for sub-menus), and 'url' (string, use '#' for placeholders if specific URL is unknown). Example format:";
        $prompt .= "\n{\"items\": [ {\"title\": \"Home\", \"url\": \"#home\"}, {\"title\": \"About Us\", \"url\": \"#about\", \"children\": [ {\"title\": \"Team\", \"url\": \"#team\"} ] } ]}";
        $prompt .= "\n\nDo not include any explanatory text before or after the JSON object. Just the JSON.";

        return $prompt;
    }

    /**
     * Format sitemap data into a string suitable for the prompt.
     *
     * @param array $sitemap_data The sitemap data.
     * @param int $level Current nesting level.
     * @return string Formatted sitemap string.
     */
    private function format_sitemap_for_prompt($sitemap_data, $level = 0)
    {
        $output = "";
        $indent = str_repeat("  ", $level);

        // Check if it's the top-level 'sitemap' structure or already the 'pages' array
        $pages = isset($sitemap_data['pages']) ? $sitemap_data['pages'] : (isset($sitemap_data['items']) ? $sitemap_data['items'] : $sitemap_data);

        if (is_array($pages)) {
            foreach ($pages as $page) {
                if (isset($page['title'])) {
                    $output .= $indent . "- " . $page['title'] . "\n";
                    if (!empty($page['children'])) {
                        $output .= $this->format_sitemap_for_prompt($page['children'], $level + 1);
                    }
                } else if (is_string($page)) { // Handle simpler array of strings if needed
                    $output .= $indent . "- " . $page . "\n";
                }
            }
        }

        // If the initial input was the sitemap structure, wrap it
        if ($level === 0 && (isset($sitemap_data['pages']) || isset($sitemap_data['items']))) {
            return "{\n" . rtrim($output) . "\n}";
        } elseif ($level === 0) {
            // Attempt to handle array directly passed
            return "{\n  \"items\": [\n" . rtrim($output) . "\n  ]\n}";
        }

        return rtrim($output);
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