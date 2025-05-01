<?php
/**
 * AI Page Generator Feature for CraftedPath Toolkit
 *
 * @package CraftedPath_Toolkit
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CPT_AI_Page_Generator Class
 */
class CPT_AI_Page_Generator
{

    /**
     * Singleton instance
     * @var CPT_AI_Page_Generator|null
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
        add_action('wp_ajax_cpt_generate_page_structure', array($this, 'ajax_generate_page_structure'));
        add_action('wp_ajax_cpt_create_wp_pages', array($this, 'ajax_create_wp_pages'));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        $screen = get_current_screen();

        // Only load on page generator page
        if (!$screen || $screen->id !== 'craftedpath_page_cpt-aipg-pages') {
            return;
        }

        // Enqueue styles (Adjust path)
        wp_enqueue_style(
            'cpt-ai-page-generator-style',
            CPT_PLUGIN_URL . 'includes/features/ai-page-generator/css/ai-page-generator.css',
            array(),
            CPT_VERSION
        );

        // Enqueue scripts (Adjust path)
        wp_enqueue_script(
            'cpt-ai-page-generator-script',
            CPT_PLUGIN_URL . 'includes/features/ai-page-generator/js/ai-page-generator.js',
            array('jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'),
            CPT_VERSION,
            true
        );

        // Localize script with nonce (Adjust variable name)
        wp_localize_script(
            'cpt-ai-page-generator-script',
            'cptPageGenVars',
            array(
                'nonce' => wp_create_nonce('cpt_page_gen_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php')
            )
        );
    }

    /**
     * Render the page generator admin page
     */
    public function render_page_generator_page()
    {
        ?>
        <div class="wrap craftedpath-settings">
            <?php cptk_render_header_card(); ?>

            <div class="craftedpath-content">
                <?php
                ob_start();
                submit_button(__('Generate Page Structure', 'craftedpath-toolkit'), 'primary', 'generate_page_structure', false, ['id' => 'cpt-generate-pages-btn']);
                $footer_html = ob_get_clean();

                cptk_render_card(
                    __('AI Page Structure Generator', 'craftedpath-toolkit'),
                    '<i class="iconoir-sparks" style="vertical-align: text-bottom; margin-right: 5px;"></i>',
                    array($this, 'render_page_generator_content'),
                    $footer_html
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the page generator content area within the card
     */
    public function render_page_generator_content()
    {
        ?>
        <div class="cpt-page-generator-container">
            <p><?php esc_html_e('Generate a comprehensive page structure (sitemap) for your website using AI. This tool will analyze your description and suggest an optimal structure. You can then create WordPress pages based on the AI suggestions.', 'craftedpath-toolkit'); ?>
            </p>

            <div class="cpt-form-row">
                <label for="sitemap_description"><?php esc_html_e('Website Description', 'craftedpath-toolkit'); ?></label>
                <textarea id="sitemap_description" name="sitemap_description" rows="4"
                    placeholder="<?php esc_attr_e('Describe your website purpose, target audience, key topics, and goals...', 'craftedpath-toolkit'); ?>"></textarea>
            </div>

            <div class="cpt-form-row">
                <label for="sitemap_depth"><?php esc_html_e('Structure Depth', 'craftedpath-toolkit'); ?></label>
                <select id="sitemap_depth" name="sitemap_depth">
                    <option value="2"><?php esc_html_e('2 Levels (Main pages + Subpages)', 'craftedpath-toolkit'); ?></option>
                    <option value="3" selected><?php esc_html_e('3 Levels (Recommended)', 'craftedpath-toolkit'); ?></option>
                    <option value="4"><?php esc_html_e('4 Levels (Complex site)', 'craftedpath-toolkit'); ?></option>
                </select>
            </div>

            <div id="sitemap_results" class="cpt-results-container" style="display: none;">
                <h3><?php esc_html_e('Generated Page Structure', 'craftedpath-toolkit'); ?></h3>
                <p><?php esc_html_e('Review the structure below. Select the pages you want to create, then click the button.', 'craftedpath-toolkit'); ?>
                </p>
                <div class="sitemap-tree"></div>
                <div class="cpt-actions">
                    <button class="button button-secondary" id="create_wp_pages"
                        disabled><?php esc_html_e('Create Selected Pages', 'craftedpath-toolkit'); ?></button>
                    <button class="button button-secondary"
                        id="copy_page_structure_json"><?php esc_html_e('Copy JSON', 'craftedpath-toolkit'); ?></button>
                </div>
            </div>

            <div id="page_gen_status" class="cpt-status-message" style="display: none;"></div>
            <div id="sitemap_error" class="cpt-error-message" style="display: none;"></div>
            <div id="sitemap_loading" class="cpt-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <p><?php esc_html_e('Generating page structure with AI...', 'craftedpath-toolkit'); ?></p>
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
            'model' => isset($options['openai_model']) ? $options['openai_model'] : 'gpt-4o',
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
            'max_tokens' => 2000,
            'temperature' => 0.6,
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
            'temperature' => floatval($request_options['temperature']),
            'max_tokens' => intval($request_options['max_tokens']),
            'response_format' => ['type' => 'json_object'],
        );

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'headers' => $headers,
                'body' => wp_json_encode($body),
                'timeout' => 90,
                'data_format' => 'body',
            )
        );

        if (is_wp_error($response)) {
            error_log('[CPT OpenAI PageGen] HTTP Error: ' . $response->get_error_message());
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
            error_log(sprintf('[CPT OpenAI PageGen] API Error (%d): %s', $response_code, $error_message));
            error_log('[CPT OpenAI PageGen] API Error Body: ' . $response_body);
            return new \WP_Error(
                'openai_api_error',
                sprintf(__('OpenAI API Error (%d): %s', 'craftedpath-toolkit'), $response_code, $error_message)
            );
        }

        $decoded_body = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[CPT OpenAI PageGen] Failed to decode API JSON response. Error: ' . json_last_error_msg());
            error_log('[CPT OpenAI PageGen] Raw Body: ' . $response_body);
            return new \WP_Error(
                'openai_response_decode_error',
                __('Failed to decode the response from OpenAI API.', 'craftedpath-toolkit')
            );
        }

        return $decoded_body;
    }

    /**
     * AJAX handler for generating the page structure (sitemap)
     */
    public function ajax_generate_page_structure()
    {
        check_ajax_referer('cpt_page_gen_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'craftedpath-toolkit'));
            return;
        }

        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $depth = isset($_POST['depth']) ? intval($_POST['depth']) : 3;

        if (empty($description)) {
            wp_send_json_error(__('Website description is required.', 'craftedpath-toolkit'));
            return;
        }

        $existing_pages = $this->get_existing_pages();
        $prompt = $this->build_page_structure_prompt($description, $depth, $existing_pages);
        $system_message = 'You are an expert website information architect. Your task is to generate a logical, hierarchical sitemap (page structure) for a website based on its description.';

        $response = $this->call_openai_api($prompt, [], $system_message);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        $api_response = $response;
        if (!isset($api_response['choices'][0]['message']['content'])) {
            wp_send_json_error(__('Unexpected API response format.', 'craftedpath-toolkit'));
            return;
        }

        $structure_json = $api_response['choices'][0]['message']['content'];

        // Extract JSON from the response *content string*
        // The content *itself* should be the JSON string we requested with response_format
        $first_brace = strpos($structure_json, '{');
        $last_brace = strrpos($structure_json, '}');

        if ($first_brace === false || $last_brace === false || $last_brace < $first_brace) {
            wp_send_json_error(__('AI response did not contain a valid structure. Please try again.', 'craftedpath-toolkit'));
            return;
        }

        $extracted_json = substr($structure_json, $first_brace, $last_brace - $first_brace + 1);
        $decoded_structure = json_decode($extracted_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("CPT AI Page Gen JSON Decode Error: " . json_last_error_msg() . "\nRaw Response:\n" . $structure_json);
            wp_send_json_error(__('AI response could not be parsed as valid JSON. Please try again.', 'craftedpath-toolkit'));
            return;
        }

        // Simple validation: check if it looks like a page structure (e.g., has 'pages' key)
        if (!isset($decoded_structure['pages']) || !is_array($decoded_structure['pages'])) {
            wp_send_json_error(__('AI response did not follow the expected page structure format (missing \'pages\' array). Please try again.', 'craftedpath-toolkit'));
            return;
        }

        // Store the generated structure in a transient for potential use by the menu generator
        set_transient('cpt_last_generated_sitemap', $decoded_structure, HOUR_IN_SECONDS);

        wp_send_json_success(array('page_structure' => $decoded_structure));
    }

    /**
     * Build the prompt for the OpenAI API to generate a page structure.
     *
     * @param string $description User-provided website description.
     * @param int $depth Desired depth of the sitemap.
     * @param array $existing_pages List of existing page titles.
     * @return string The generated prompt.
     */
    private function build_page_structure_prompt($description, $depth, $existing_pages)
    {
        $prompt = "Based on the following website description, generate a logical hierarchical sitemap (page structure):\n\n";
        $prompt .= "Description: " . $description . "\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= sprintf("- The structure should be up to %d levels deep.\n", $depth);
        $prompt .= "- Suggest clear and concise page titles.\n";

        if (!empty($existing_pages)) {
            $prompt .= "- Consider these existing pages (avoid exact duplicates unless logical): " . implode(', ', $existing_pages) . "\n";
        }

        $prompt .= "\nProvide the output ONLY as a valid JSON object with a single root key 'pages'. The value should be an array of page objects. Each page object must have a 'title' (string) and optionally 'children' (an array of nested page objects). Example format:";
        $prompt .= "\n{\"pages\": [ {\"title\": \"Home\"}, {\"title\": \"About Us\", \"children\": [ {\"title\": \"Our Team\"}, {\"title\": \"History\"} ] }, {\"title\": \"Services\"} ]}";
        $prompt .= "\n\nDo not include any explanatory text before or after the JSON object. Just the JSON.";

        return $prompt;
    }

    /**
     * Get a simple list of existing page titles.
     *
     * @return array List of page titles.
     */
    private function get_existing_pages()
    {
        $pages = get_pages();
        $titles = array();
        if ($pages) {
            foreach ($pages as $page) {
                $titles[] = $page->post_title;
            }
        }
        return $titles;
    }

    /**
     * AJAX handler for creating WordPress pages based on the generated structure.
     */
    public function ajax_create_wp_pages()
    {
        check_ajax_referer('cpt_page_gen_nonce', 'nonce');

        if (!current_user_can('publish_pages')) {
            wp_send_json_error(__('Permission denied.', 'craftedpath-toolkit'));
            return;
        }

        $pages_to_create_json = isset($_POST['pages_to_create']) ? wp_unslash($_POST['pages_to_create']) : '';

        if (empty($pages_to_create_json)) {
            wp_send_json_error(__('No page data received.', 'craftedpath-toolkit'));
            return;
        }

        $pages_to_create = json_decode($pages_to_create_json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($pages_to_create)) {
            wp_send_json_error(__('Invalid page data format.', 'craftedpath-toolkit'));
            return;
        }

        $created_pages = array();
        $failed_pages = array();
        $page_id_map = array();

        $this->create_pages_recursive($pages_to_create, 0, $created_pages, $failed_pages, $page_id_map);

        if (!empty($failed_pages)) {
            wp_send_json_error(array(
                'message' => __('Some pages could not be created.', 'craftedpath-toolkit'),
                'created' => $created_pages,
                'failed' => $failed_pages
            ));
        } else {
            wp_send_json_success(array(
                'message' => sprintf(_n('%d page created successfully.', '%d pages created successfully.', count($created_pages), 'craftedpath-toolkit'), count($created_pages)),
                'created' => $created_pages,
                'page_id_map' => $page_id_map
            ));
        }
    }

    /**
     * Recursively create WordPress pages.
     *
     * @param array $pages Array of page objects to create.
     * @param int $parent_id The ID of the parent page.
     * @param array &$created_pages Array to store successfully created page info.
     * @param array &$failed_pages Array to store titles of failed pages.
     * @param array &$page_id_map Array mapping created page titles to their IDs.
     */
    private function create_pages_recursive($pages, $parent_id, &$created_pages, &$failed_pages, &$page_id_map)
    {
        foreach ($pages as $page_data) {
            if (!isset($page_data['title']) || empty(trim($page_data['title']))) {
                continue;
            }

            $page_title = sanitize_text_field(trim($page_data['title']));
            $page_exists = get_page_by_title($page_title, OBJECT, 'page');

            $page_id = null;
            $status = 'failed';

            if ($page_exists) {
                $page_id = $page_exists->ID;
                $status = 'skipped';
            } else {
                $new_page = array(
                    'post_title' => $page_title,
                    'post_content' => '',
                    'post_status' => 'draft',
                    'post_author' => get_current_user_id(),
                    'post_type' => 'page',
                    'post_parent' => $parent_id
                );

                $insert_result = wp_insert_post($new_page, true);

                if (is_wp_error($insert_result)) {
                    $failed_pages[] = $page_title . ' (' . $insert_result->get_error_message() . ')';
                    error_log("CPT AI Page Gen: Failed to create page '{$page_title}': " . $insert_result->get_error_message());
                } else {
                    $page_id = $insert_result;
                    $status = 'created';
                }
            }

            if ($page_id !== null) {
                $page_info = [
                    'title' => $page_title,
                    'id' => $page_id,
                    'status' => $status,
                    'edit_url' => get_edit_post_link($page_id, 'raw')
                ];
                $created_pages[] = $page_info;
                $page_id_map[$page_title] = $page_id;

                if (!empty($page_data['children']) && is_array($page_data['children'])) {
                    $this->create_pages_recursive($page_data['children'], $page_id, $created_pages, $failed_pages, $page_id_map);
                }
            } else if ($status === 'failed' && !in_array($page_title, $failed_pages)) {
                $failed_pages[] = $page_title . ' (Unknown Error)';
            }

        }
    }

}