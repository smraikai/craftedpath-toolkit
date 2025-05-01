<?php
/**
 * AI Sitemap Generator Feature for CraftedPath Toolkit
 *
 * @package CraftedPath_Toolkit
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CPT_AI_Sitemap_Generator Class
 */
class CPT_AI_Sitemap_Generator
{

    /**
     * Singleton instance
     * @var CPT_AI_Sitemap_Generator|null
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
        add_action('wp_ajax_cpt_generate_sitemap', array($this, 'ajax_generate_sitemap'));
        add_action('wp_ajax_cpt_generate_menu', array($this, 'ajax_generate_menu'));
        add_action('wp_ajax_cpt_create_wp_menu', array($this, 'ajax_create_wp_menu'));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        $screen = get_current_screen();

        // Only load on sitemap generator pages
        if (
            !$screen || !in_array($screen->id, [
                'craftedpath_page_cpt-aismg-sitemap',
                'craftedpath_page_cpt-aismg-menu'
            ])
        ) {
            return;
        }

        // Enqueue sitemap generator styles
        wp_enqueue_style(
            'cpt-ai-sitemap-generator-style',
            CPT_PLUGIN_URL . 'includes/features/ai-sitemap-generator/css/ai-sitemap-generator.css',
            array(),
            CPT_VERSION
        );

        // Enqueue sitemap generator scripts
        wp_enqueue_script(
            'cpt-ai-sitemap-generator-script',
            CPT_PLUGIN_URL . 'includes/features/ai-sitemap-generator/js/ai-sitemap-generator.js',
            array('jquery'),
            CPT_VERSION,
            true
        );

        // Localize script with nonce
        wp_localize_script(
            'cpt-ai-sitemap-generator-script',
            'cptSitemapVars',
            array(
                'nonce' => wp_create_nonce('cpt_sitemap_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php')
            )
        );
    }

    /**
     * Render the sitemap generator page
     */
    public function render_sitemap_page()
    {
        ?>
        <div class="wrap craftedpath-settings">
            <?php cptk_render_header_card(); ?>

            <div class="craftedpath-content">
                <?php
                ob_start();
                submit_button(__('Generate Sitemap', 'craftedpath-toolkit'), 'primary', 'generate_sitemap', false);
                $footer_html = ob_get_clean();

                cptk_render_card(
                    __('AI Sitemap Generator', 'craftedpath-toolkit'),
                    'dashicons-networking',
                    array($this, 'render_sitemap_content'),
                    $footer_html
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the sitemap generator content
     */
    public function render_sitemap_content()
    {
        ?>
        <div class="cpt-sitemap-generator-container">
            <p><?php esc_html_e('Generate a comprehensive sitemap for your website using AI. This tool will analyze your existing content and suggest an optimal sitemap structure.', 'craftedpath-toolkit'); ?>
            </p>

            <div class="cpt-form-row">
                <label for="sitemap_description"><?php esc_html_e('Website Description', 'craftedpath-toolkit'); ?></label>
                <textarea id="sitemap_description" name="sitemap_description" rows="4"
                    placeholder="<?php esc_attr_e('Describe your website purpose, target audience, and goals...', 'craftedpath-toolkit'); ?>"></textarea>
            </div>

            <div class="cpt-form-row">
                <label for="sitemap_depth"><?php esc_html_e('Sitemap Depth', 'craftedpath-toolkit'); ?></label>
                <select id="sitemap_depth" name="sitemap_depth">
                    <option value="2"><?php esc_html_e('2 Levels (Main pages + Subpages)', 'craftedpath-toolkit'); ?></option>
                    <option value="3" selected><?php esc_html_e('3 Levels (Recommended)', 'craftedpath-toolkit'); ?></option>
                    <option value="4"><?php esc_html_e('4 Levels (Complex site)', 'craftedpath-toolkit'); ?></option>
                </select>
            </div>

            <div id="sitemap_results" class="cpt-results-container" style="display: none;">
                <h3><?php esc_html_e('Generated Sitemap', 'craftedpath-toolkit'); ?></h3>
                <div class="sitemap-tree"></div>
                <div class="cpt-actions">
                    <button class="button button-secondary"
                        id="save_sitemap"><?php esc_html_e('Save as JSON', 'craftedpath-toolkit'); ?></button>
                    <button class="button button-secondary"
                        id="export_sitemap"><?php esc_html_e('Export to CSV', 'craftedpath-toolkit'); ?></button>
                </div>
            </div>

            <div id="sitemap_error" class="cpt-error-message" style="display: none;"></div>
            <div id="sitemap_loading" class="cpt-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <p><?php esc_html_e('Generating sitemap with AI...', 'craftedpath-toolkit'); ?></p>
            </div>
        </div>
        <?php
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
                submit_button(__('Generate Menu', 'craftedpath-toolkit'), 'primary', 'generate_menu', false);
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
                <label for="use_existing_sitemap"><?php esc_html_e('Use Existing Sitemap', 'craftedpath-toolkit'); ?></label>
                <input type="checkbox" id="use_existing_sitemap" name="use_existing_sitemap">
                <span
                    class="description"><?php esc_html_e('Use previously generated sitemap as base', 'craftedpath-toolkit'); ?></span>
            </div>

            <div id="menu_results" class="cpt-results-container" style="display: none;">
                <h3><?php esc_html_e('Generated Menu', 'craftedpath-toolkit'); ?></h3>
                <div class="menu-structure"></div>
                <div class="cpt-actions">
                    <button class="button button-secondary"
                        id="create_wp_menu"><?php esc_html_e('Create WordPress Menu', 'craftedpath-toolkit'); ?></button>
                    <button class="button button-secondary"
                        id="export_menu"><?php esc_html_e('Export to JSON', 'craftedpath-toolkit'); ?></button>
                </div>
            </div>

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

        return array(
            'api_key' => isset($options['openai_api_key']) ? $options['openai_api_key'] : '',
            'model' => isset($options['openai_model']) ? $options['openai_model'] : 'gpt-4',
        );
    }

    /**
     * Make request to OpenAI API
     *
     * @param string $prompt The prompt to send to OpenAI
     * @param array $options Additional options for the API call
     * @return string|WP_Error Response from OpenAI or error
     */
    public function call_openai_api($prompt, $options = array())
    {
        $settings = $this->get_openai_settings();

        if (empty($settings['api_key'])) {
            return new WP_Error(
                'missing_api_key',
                __('OpenAI API key is not configured. Please add it in the settings.', 'craftedpath-toolkit')
            );
        }

        $default_options = array(
            'model' => $settings['model'],
            'temperature' => 0.7,
            'max_tokens' => 2000, // Keep a reasonable default, can be overridden
        );

        $options = wp_parse_args($options, $default_options);

        $headers = array(
            'Authorization' => 'Bearer ' . $settings['api_key'],
            'Content-Type' => 'application/json',
        );

        $body = array(
            'model' => $options['model'],
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'Create a JSON sitemap structure for a website. 

# Steps

1. **Identify Main Categories**: Determine the primary sections or categories of the website based on its purpose and target audience.
2. **Define Subcategories**: For each main category, list potential subcategories or pages that fall under them, considering the hierarchy of information.
3. **Determine Additional Pages**: Identify any additional pages that do not fit into the main category structure but are essential, such as contact or about pages.
4. **Establish Hierarchy**: Arrange the categories, subcategories, and pages to reflect their organizational hierarchy and user navigation flow.
5. **Use Descriptive Labels**: Ensure that each category and page is labeled with a clear and descriptive name that communicates its purpose or content.
6. **Review and Refine**: Check the sitemap for completeness and usability, making necessary adjustments to improve organization and clarity.

# Output Format

Provide the sitemap in a structured text format, using nested bullet points to indicate hierarchy and organization.

# Examples

**Example 1:**

- Home
  - About Us
    - Company History
    - Team
    - Careers
  - Services
    - Consulting
    - Implementation
    - Support
  - Products
    - Product A
    - Product B
  - Blog
    - Industry Insights
    - News
  - Contact Us
    - Contact Form
    - Location Map

(The actual sitemap should be customized based on the specifics of the site being structured, with appropriate categories and pages.)',
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens'],
            'response_format' => ['type' => 'json_object'], // Enforce JSON object output
        );

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'headers' => $headers,
                'body' => wp_json_encode($body),
                'timeout' => 60,
                'data_format' => 'body',
            )
        );

        if (is_wp_error($response)) {
            error_log('[CPT OpenAI] HTTP Error: ' . $response->get_error_message());
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
            error_log(sprintf('[CPT OpenAI] API Error (%d): %s', $response_code, $error_message));
            error_log('[CPT OpenAI] API Error Body: ' . $response_body);
            return new WP_Error(
                'openai_api_error',
                sprintf(__('OpenAI API Error (%d): %s', 'craftedpath-toolkit'), $response_code, $error_message)
            );
        }

        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[CPT OpenAI] Failed to decode API JSON response. Error: ' . json_last_error_msg());
            error_log('[CPT OpenAI] Raw Body: ' . $response_body);
            return new WP_Error(
                'openai_response_decode_error',
                __('Failed to decode the response from OpenAI API.', 'craftedpath-toolkit')
            );
        }

        if (empty($data['choices'][0]['message']['content'])) {
            error_log('[CPT OpenAI] API returned empty content in choices.');
            error_log('[CPT OpenAI] Full Response Data: ' . print_r($data, true));
            return new WP_Error(
                'openai_empty_content',
                __('OpenAI API returned an empty content response.', 'craftedpath-toolkit')
            );
        }

        // The content itself should be a JSON string because we asked for json_object
        return $data['choices'][0]['message']['content'];
    }

    /**
     * AJAX endpoint for generating sitemap
     */
    public function ajax_generate_sitemap()
    {
        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'cpt_sitemap_nonce')) {
            wp_send_json_error(__('Security check failed.', 'craftedpath-toolkit'));
        }

        // Get parameters
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $depth = isset($_POST['depth']) ? intval($_POST['depth']) : 3;

        if (empty($description)) {
            wp_send_json_error(__('Please provide a website description.', 'craftedpath-toolkit'));
        }

        // Get existing pages
        $existing_pages = $this->get_existing_pages();

        // Build the prompt
        $prompt = $this->build_sitemap_prompt($description, $depth, $existing_pages);

        // Call OpenAI API
        $response_content = $this->call_openai_api($prompt, array(
            'temperature' => 0.7,
            'max_tokens' => 4000,
        ));

        if (is_wp_error($response_content)) {
            // Error already logged in call_openai_api
            wp_send_json_error($response_content->get_error_message());
        }

        // Log the JSON string received from the API content field
        error_log('[CPT AI Sitemap] Received JSON Content String: ' . print_r($response_content, true));

        // Decode the JSON string from the content
        $decoded_data = json_decode($response_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_data)) {
            error_log('[CPT AI Sitemap] JSON Decode Error after API call: ' . json_last_error_msg()); // Log JSON error
            error_log('[CPT AI Sitemap] Content String that failed decode: ' . $response_content);
            wp_send_json_error(__('Failed to parse AI JSON response content. Please try again.', 'craftedpath-toolkit'));
        }

        // --- Expect the array under the 'sitemap' key --- 
        if (!isset($decoded_data['sitemap']) || !is_array($decoded_data['sitemap'])) {
            error_log('[CPT AI Sitemap] Missing or invalid \'sitemap\' key in decoded JSON object.'); // Corrected quoting
            error_log('[CPT AI Sitemap] Decoded Data: ' . print_r($decoded_data, true));
            wp_send_json_error(__('AI response does not contain the expected sitemap structure. Please check the prompt or try again.', 'craftedpath-toolkit'));
        }

        $sitemap_data = $decoded_data['sitemap'];
        // --- End sitemap key check ---

        // Send back the sitemap data array
        wp_send_json_success($sitemap_data);
    }

    /**
     * Build prompt for sitemap generation
     */
    private function build_sitemap_prompt($description, $depth, $existing_pages)
    {
        $prompt = "Generate a comprehensive sitemap for a website with the following description:\n\n";
        $prompt .= "{$description}\n\n";

        $prompt .= "Generate a sitemap with a depth of {$depth} levels.\n\n";

        if (!empty($existing_pages)) {
            $prompt .= "The website already has the following pages. Please consider them in your sitemap design:\n";
            foreach ($existing_pages as $page) {
                $prompt .= "- {$page['title']} ({$page['path']})\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Please respond ONLY with a valid JSON object containing a single key named \"sitemap\". The value of \"sitemap\" must be an array of page objects. Each page object should include:
1. title - The page title (string)
2. path - The URL path (string, e.g., '/about-us')
3. description - Short description of the page content (string)
4. children - An array of child page objects (following the same structure), or an empty array if no children.

Example JSON object format:

{
  \"sitemap\": [
    {
      \"title\": \"Home\",
      \"path\": \"/\",
      \"description\": \"Main landing page\",
      \"children\": []
    },
    {
      \"title\": \"About Us\",
      \"path\": \"/about\",
      \"description\": \"Company information\",
      \"children\": [
        {
          \"title\": \"Our Team\",
          \"path\": \"/about/team\",
          \"description\": \"Team members\",
          \"children\": []
        }
      ]
    }
  ]
}

Include standard/common pages for this type of website, but also suggest innovative or unique pages that would benefit the target audience described. Ensure the output is strictly a valid JSON object starting with { and ending with }.";

        return $prompt;
    }

    /**
     * AJAX endpoint for generating menu
     */
    public function ajax_generate_menu()
    {
        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'cpt_sitemap_nonce')) {
            wp_send_json_error(__('Security check failed.', 'craftedpath-toolkit'));
        }

        // Get parameters
        $menu_type = isset($_POST['menu_type']) ? sanitize_text_field($_POST['menu_type']) : 'main';
        $use_existing_sitemap = isset($_POST['use_existing_sitemap']) && $_POST['use_existing_sitemap'] === 'true';
        $sitemap_data = null;

        if ($use_existing_sitemap && isset($_POST['sitemap_data'])) {
            $sitemap_data = json_decode(stripslashes($_POST['sitemap_data']), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $sitemap_data = null;
            }
        }

        // Build the prompt
        $prompt = $this->build_menu_prompt($menu_type, $sitemap_data);

        // Call OpenAI API
        $response_content = $this->call_openai_api($prompt, array(
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ));

        if (is_wp_error($response_content)) {
            // Error logged in call_openai_api
            wp_send_json_error($response_content->get_error_message());
        }

        // Log the JSON string received from the API content field
        error_log('[CPT AI Menu] Received JSON Content String: ' . print_r($response_content, true));

        // Initialize variable before decoding
        $decoded_data = null;
        // Decode the JSON string from the content
        $decoded_data = json_decode($response_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_data)) {
            error_log('[CPT AI Menu] JSON Decode Error after API call: ' . json_last_error_msg()); // Log JSON error
            error_log('[CPT AI Menu] Content String that failed decode: ' . $response_content);
            wp_send_json_error(__('Failed to parse AI JSON response content. Please try again.', 'craftedpath-toolkit'));
        }

        // --- Expect the array under the 'menu' key --- 
        if (!isset($decoded_data['menu']) || !is_array($decoded_data['menu'])) {
            error_log('[CPT AI Menu] Missing or invalid \'menu\' key in decoded JSON object.'); // Corrected quoting
            error_log('[CPT AI Menu] Decoded Data: ' . print_r($decoded_data, true));
            wp_send_json_error(__('AI response does not contain the expected menu structure. Please check the prompt or try again.', 'craftedpath-toolkit'));
        }

        $menu_data = $decoded_data['menu'];
        // --- End menu key check ---

        // Send back the menu data array
        wp_send_json_success($menu_data);
    }

    /**
     * Build prompt for menu generation
     */
    private function build_menu_prompt($menu_type, $sitemap_data)
    {
        $prompt = "Generate a well-structured navigation menu for a website.\n\n";

        $prompt .= "Menu type: " . ucfirst($menu_type) . "\n\n";

        if ($sitemap_data && is_array($sitemap_data)) {
            $prompt .= "Based on the following sitemap structure:\n";
            $prompt .= $this->format_sitemap_for_prompt($sitemap_data);
            $prompt .= "\n\n";
        } else {
            $prompt .= "Create a standard " . $menu_type . " menu suitable for most websites.\n\n";
        }

        $menu_specifics = array(
            'main' => 'This is the primary navigation shown in the header. Keep it concise with the most important pages. Typically has 5-7 items maximum.',
            'footer' => 'Footer menus often include links to important pages, legal information, and secondary navigation. Can be more comprehensive than the main menu.',
            'mobile' => 'Mobile menus should be simplified for mobile viewing. Focus on the most critical pages. Consider a slightly different structure than the desktop menu.',
        );

        if (isset($menu_specifics[$menu_type])) {
            $prompt .= $menu_specifics[$menu_type] . "\n\n";
        }

        $prompt .= "Please respond ONLY with a valid JSON object containing a single key named \"menu\". The value of \"menu\" must be an array of menu item objects. Each menu item object should include:
1. title - The menu item text (string)
2. path - The URL path to link to (string)
3. children - An array of submenu item objects (following the same structure), or an empty array if no children.

Example JSON object format:
{
  \"menu\": [
    {
      \"title\": \"Home\",
      \"path\": \"/\",
      \"children\": []
    },
    {
      \"title\": \"About\",
      \"path\": \"/about\",
      \"children\": [
        {
          \"title\": \"Team\",
          \"path\": \"/about/team\",
          \"children\": []
        }
      ]
    }
  ]
}

Ensure the output is strictly a valid JSON object starting with { and ending with }.";

        return $prompt;
    }

    /**
     * Format sitemap data for prompt
     */
    private function format_sitemap_for_prompt($sitemap_data, $level = 0)
    {
        $output = '';
        $indent = str_repeat('  ', $level);

        foreach ($sitemap_data as $page) {
            $output .= $indent . '- ' . $page['title'] . ' (' . $page['path'] . ")\n";

            if (!empty($page['children'])) {
                $output .= $this->format_sitemap_for_prompt($page['children'], $level + 1);
            }
        }

        return $output;
    }

    /**
     * AJAX endpoint for creating WordPress menu
     */
    public function ajax_create_wp_menu()
    {
        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'cpt_sitemap_nonce')) {
            wp_send_json_error(__('Security check failed.', 'craftedpath-toolkit'));
        }

        // Get parameters
        $menu_data = isset($_POST['menu_data']) ? json_decode(stripslashes($_POST['menu_data']), true) : array();
        $menu_name = isset($_POST['menu_name']) ? sanitize_text_field($_POST['menu_name']) : 'AI Generated Menu';

        if (empty($menu_data) || !is_array($menu_data)) {
            wp_send_json_error(__('Invalid menu data.', 'craftedpath-toolkit'));
        }

        // Create the menu
        $menu_id = wp_create_nav_menu($menu_name . ' ' . current_time('Y-m-d'));

        if (is_wp_error($menu_id)) {
            wp_send_json_error($menu_id->get_error_message());
        }

        // Add items to the menu
        $this->add_menu_items($menu_data, $menu_id, 0);

        wp_send_json_success(array(
            'menu_id' => $menu_id,
            'message' => sprintf(__('Menu "%s" created successfully.', 'craftedpath-toolkit'), $menu_name)
        ));
    }

    /**
     * Add menu items recursively
     */
    private function add_menu_items($items, $menu_id, $parent_id = 0)
    {
        foreach ($items as $item) {
            // Create menu item
            $item_data = array(
                'menu-item-title' => $item['title'],
                'menu-item-url' => home_url($item['path']),
                'menu-item-status' => 'publish',
                'menu-item-type' => 'custom',
            );

            if ($parent_id > 0) {
                $item_data['menu-item-parent-id'] = $parent_id;
            }

            $item_id = wp_update_nav_menu_item($menu_id, 0, $item_data);

            // Add children if any
            if (!is_wp_error($item_id) && !empty($item['children'])) {
                $this->add_menu_items($item['children'], $menu_id, $item_id);
            }
        }
    }

    /**
     * Get existing pages from WordPress
     */
    private function get_existing_pages()
    {
        $pages = array();

        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                $pages[] = array(
                    'title' => get_the_title(),
                    'path' => str_replace(home_url(), '', get_permalink()),
                );
            }

            wp_reset_postdata();
        }

        return $pages;
    }
}