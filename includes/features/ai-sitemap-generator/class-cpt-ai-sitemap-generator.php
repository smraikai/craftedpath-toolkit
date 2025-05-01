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
            'max_tokens' => 2000,
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
                    'content' => 'You are a helpful assistant specialized in website structure and information architecture.',
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens'],
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
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_message = wp_remote_retrieve_response_message($response);
            $body = wp_remote_retrieve_body($response);
            $body_data = json_decode($body, true);

            if (isset($body_data['error']['message'])) {
                $error_message = $body_data['error']['message'];
            }

            return new WP_Error(
                'openai_api_error',
                sprintf(__('OpenAI API Error (%d): %s', 'craftedpath-toolkit'), $response_code, $error_message)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['choices'][0]['message']['content'])) {
            return new WP_Error(
                'openai_api_error',
                __('OpenAI API returned an empty response.', 'craftedpath-toolkit')
            );
        }

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
        $response = $this->call_openai_api($prompt, array(
            'temperature' => 0.7,
            'max_tokens' => 4000,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        // Try to parse the response as JSON
        $cleaned_response = $this->clean_json_response($response);
        $sitemap_data = json_decode($cleaned_response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($sitemap_data)) {
            wp_send_json_error(__('Failed to parse AI response. Please try again.', 'craftedpath-toolkit'));
        }

        // Send back the sitemap data
        wp_send_json_success($sitemap_data);
    }

    /**
     * Clean JSON response from OpenAI (remove markdown code blocks etc)
     */
    private function clean_json_response($response)
    {
        // Remove markdown code blocks if present
        $response = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/m', '$1', $response);

        // Remove any non-JSON text before or after
        if (($start = strpos($response, '{')) !== false) {
            $response = substr($response, $start);

            // Find the last closing brace
            $lastBrace = strrpos($response, '}');
            if ($lastBrace !== false) {
                $response = substr($response, 0, $lastBrace + 1);
            }
        }

        return $response;
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

        $prompt .= "Please respond with a JSON structure representing the sitemap. Each page should include:
1. title - The page title
2. path - The URL path (e.g., '/about-us')
3. description - Short description of the page content
4. children - Array of subpages (if any)

The structure should be an array of top-level pages, each with potential children. For example:

[
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

Please include standard/common pages for this type of website, but also suggest innovative or unique pages that would benefit the target audience described.";

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
        $response = $this->call_openai_api($prompt, array(
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        // Try to parse the response as JSON
        $cleaned_response = $this->clean_json_response($response);
        $menu_data = json_decode($cleaned_response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($menu_data)) {
            wp_send_json_error(__('Failed to parse AI response. Please try again.', 'craftedpath-toolkit'));
        }

        // Send back the menu data
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

        $prompt .= "Please respond with a JSON structure representing the menu. Each menu item should include:
1. title - The menu item text
2. path - The URL path to link to
3. children - Array of submenu items (if any)

For example:
[
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
]";

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