<?php
/**
 * Class CPT_AI_Alt_Text
 *
 * Handles the AI-powered alternative text generation for images.
 */

if (!defined('WPINC')) {
    die;
}

class CPT_AI_Alt_Text
{

    private static $instance = null;
    const AJAX_ACTION = 'cpt_generate_alt_text';

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        // AJAX hook for generating alt text
        add_action('wp_ajax_' . self::AJAX_ACTION, array($this, 'handle_generate_alt_text_ajax'));

        // Hooks to add custom column to Media Library list view
        add_filter('manage_media_columns', array($this, 'add_alt_text_column'));
        add_action('manage_media_custom_column', array($this, 'display_alt_text_column'), 10, 2);

        // Hook to enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_scripts'));
    }

    // Method to handle the AJAX request for generating alt text
    public function handle_generate_alt_text_ajax()
    {
        // Basic security checks
        check_ajax_referer(self::AJAX_ACTION . '_nonce', 'security');
        if (!current_user_can('upload_files')) { // Check if user can manage media
            wp_send_json_error(['message' => __('Permission denied.', 'craftedpath-toolkit')], 403);
            return;
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

        if (!$attachment_id || 'attachment' !== get_post_type($attachment_id) || !wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => __('Invalid attachment ID.', 'craftedpath-toolkit')], 400);
            return;
        }

        // Get image URL
        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            wp_send_json_error(['message' => __('Could not get image URL.', 'craftedpath-toolkit')], 500);
            return;
        }

        // Fetch API key from the General Settings option
        $cptk_options = get_option('cptk_options');
        $api_key = isset($cptk_options['openai_api_key']) ? $cptk_options['openai_api_key'] : '';

        if (empty($api_key)) {
            wp_send_json_error(['message' => __('OpenAI API key is not configured in CraftedPath Toolkit settings.', 'craftedpath-toolkit')], 400);
            return;
        }

        // Get selected model from settings, default to gpt-4o if not set or invalid
        $selected_model = isset($cptk_options['openai_model']) ? $cptk_options['openai_model'] : 'gpt-4o';
        // For vision, gpt-4o is often preferred, but we allow using the selected one.
        // Consider forcing gpt-4o or adding specific logic if other models don't support vision well.
        $model_to_use = $selected_model;
        // Add a filter if needed: $model_to_use = apply_filters('cptk_alt_text_openai_model', $selected_model);

        // Call OpenAI API
        $generated_alt = $this->call_openai_vision_api($image_url, $api_key, $model_to_use);

        if (is_wp_error($generated_alt)) {
            wp_send_json_error(['message' => $generated_alt->get_error_message()], 500);
            return;
        }

        if (empty($generated_alt)) {
            wp_send_json_error(['message' => __('AI did not return any text.', 'craftedpath-toolkit')], 500);
            return;
        }

        // Update alt text
        $updated = $this->update_image_alt($attachment_id, $generated_alt);

        if ($updated) {
            wp_send_json_success(['alt_text' => $generated_alt]);
        } else {
            wp_send_json_error(['message' => __('Failed to update alt text meta.', 'craftedpath-toolkit')], 500);
        }

        wp_die();
    }

    // Method to call OpenAI Vision API
    private function call_openai_vision_api($image_url, $api_key, $model = 'gpt-4o')
    {
        $api_endpoint = 'https://api.openai.com/v1/chat/completions';

        // Basic prompt - can be refined
        $prompt = "Describe this image concisely for use as alternative text (alt text). Focus on the main subject and key details relevant for accessibility. Do not include phrases like 'Image of' or 'Picture of'.";

        $payload = array(
            'model' => $model, // Use the selected model
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => $prompt
                        ),
                        array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => $image_url
                            )
                        )
                    )
                )
            ),
            'max_tokens' => 100, // Limit response length
            'temperature' => 0.5 // Adjust for creativity vs factual description
        );

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode($payload),
            'timeout' => 30 // Increase timeout for potentially longer API calls
        );

        // Make the API request
        $response = wp_remote_post($api_endpoint, $args);

        // Check for WP HTTP API errors
        if (is_wp_error($response)) {
            error_log('CPT AI Alt Text - WP Error: ' . $response->get_error_message());
            return new WP_Error('openai_wp_error', __('Error communicating with OpenAI (WP Error).', 'craftedpath-toolkit') . ' ' . $response->get_error_message());
        }

        // Check the response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($response_body, true);

        if ($response_code >= 300) {
            $error_message = isset($decoded_body['error']['message']) ? $decoded_body['error']['message'] : 'Unknown error';
            error_log('CPT AI Alt Text - OpenAI API Error (' . $response_code . '): ' . $error_message . ' | Body: ' . $response_body);
            return new WP_Error('openai_api_error', sprintf(__('OpenAI API Error (%s): %s', 'craftedpath-toolkit'), $response_code, $error_message));
        }

        // Check if the expected content is present
        if (isset($decoded_body['choices'][0]['message']['content'])) {
            $alt_text = trim($decoded_body['choices'][0]['message']['content']);
            // Optional: Further clean up (e.g., remove surrounding quotes if API adds them)
            $alt_text = trim($alt_text, '\"');
            return $alt_text;
        } else {
            error_log('CPT AI Alt Text - Unexpected OpenAI Response: ' . $response_body);
            return new WP_Error('openai_unexpected_response', __('Unexpected response format from OpenAI.', 'craftedpath-toolkit'));
        }
    }

    // Method to update the image alt text
    private function update_image_alt($attachment_id, $alt_text)
    {
        // Sanitize the alt text
        $sanitized_alt = sanitize_text_field($alt_text);
        // Update the '_wp_attachment_image_alt' post meta
        return update_post_meta($attachment_id, '_wp_attachment_image_alt', $sanitized_alt);
    }

    // Method to enqueue scripts for the media library
    public function enqueue_media_scripts($hook_suffix)
    {
        // Only load on the Media Library page (upload.php)
        if ('upload.php' !== $hook_suffix) {
            return;
        }

        // Enqueue the JS file
        wp_enqueue_script(
            'cpt-ai-alt-text-script',
            CPT_PLUGIN_URL . 'includes/features/ai-alt-text/js/alt-text-generator.js',
            array('jquery', 'cpt-toast-script'),
            CPT_VERSION,
            true
        );

        // Localize script with necessary data
        wp_localize_script(
            'cpt-ai-alt-text-script',
            'cptAiAltTextData',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(self::AJAX_ACTION . '_nonce'),
                'ajax_action' => self::AJAX_ACTION,
                'generating_text' => __('Generating...', 'craftedpath-toolkit'),
                'generate_button_text' => __('Generate Alt Text', 'craftedpath-toolkit')
            )
        );
    }

    // Add custom column to Media Library
    public function add_alt_text_column($columns)
    {
        // Use an icon and text for the header
        $header_text = __('Alt Text', 'craftedpath-toolkit');
        $icon_header = '<span class="cptk-col-header-icon" title="' . esc_attr__('AI Generated Alt Text', 'craftedpath-toolkit') . '" style="display:inline-flex; align-items:center; gap: 4px;"><i class="iconoir-sparks" style="vertical-align: text-bottom;"></i>' . esc_html($header_text) . '</span>';

        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ('title' === $key) { // Place it after the title column
                $new_columns['cpt_ai_alt_text'] = $icon_header;
            }
        }
        // If 'title' wasn't found, add it near the end before comments/posts
        if (!isset($new_columns['cpt_ai_alt_text'])) {
            $offset = array_search('comments', array_keys($columns));
            if ($offset === false)
                $offset = array_search('posts', array_keys($columns));
            if ($offset === false)
                $offset = count($columns);
            $new_columns = array_slice($columns, 0, $offset, true) +
                array('cpt_ai_alt_text' => $icon_header) +
                array_slice($columns, $offset, null, true);
        }
        return $new_columns;
    }

    // Display content in the custom column
    public function display_alt_text_column($column_name, $attachment_id)
    {
        if ('cpt_ai_alt_text' === $column_name) {
            if (!wp_attachment_is_image($attachment_id)) {
                echo '-';
                return;
            }

            $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

            echo '<div class="cpt-alt-text-container" style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">'; // Flex container
            // Display area for the alt text
            echo '<span class="cpt-alt-text-display" style="flex-grow: 1; word-break: break-all; min-height: 28px; display: inline-block; line-height: 28px;">' . esc_html($alt) . '</span>';

            // Always show the same "Generate Alt Text" button
            printf(
                '<button type="button" class="button button-secondary button-small cpt-generate-alt-button" data-attachment-id="%d" style="flex-shrink: 0;">%s</button>',
                esc_attr($attachment_id),
                __('Generate Alt Text', 'craftedpath-toolkit') // Use the new button text
            );

            // Status indicator
            echo '<span class="cpt-alt-status spinner" style="display: none; visibility: visible; flex-shrink: 0;"></span>';
            echo '</div>'; // Close flex container
        }
    }
}