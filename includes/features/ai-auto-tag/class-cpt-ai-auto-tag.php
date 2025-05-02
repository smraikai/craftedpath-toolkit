<?php
/**
 * CPT_AI_Auto_Tag Class
 *
 * Handles the AI-powered automatic tagging of posts.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('CPT_AI_Auto_Tag')) {

    class CPT_AI_Auto_Tag
    {
        /** @var CPT_AI_Auto_Tag|null */
        private static $instance = null;

        public static function instance(): CPT_AI_Auto_Tag
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct()
        {
            $this->init();
        }

        private function init()
        {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('wp_ajax_cptk_auto_tag_post', [$this, 'ajax_auto_tag_post']);
        }

        /**
         * Enqueue scripts and localize data for the block editor.
         */
        public function enqueue_scripts($hook)
        {
            $screen = get_current_screen();
            if (!$screen || !$screen->is_block_editor() || 'post' !== $screen->post_type) {
                // TODO: Make post type configurable?
                return;
            }

            // Localize data for the main build script (build/index.js)
            wp_localize_script('wp-blocks', 'cptAiAutoTagData', [
                'is_enabled' => true,
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cptk_auto_tag_nonce'),
                'i18n' => [
                    'panelTitle' => __('AI Auto Tagging', 'craftedpath-toolkit'),
                    'buttonText' => __('Auto Tag Post', 'craftedpath-toolkit'),
                    'loadingText' => __('Tagging...', 'craftedpath-toolkit'),
                    'successPrefix' => __('Tags assigned:', 'craftedpath-toolkit'),
                    'noTagsAdded' => __('No relevant tags suggested by AI.', 'craftedpath-toolkit'),
                    'errorPrefix' => __('Error:', 'craftedpath-toolkit'),
                    'genericError' => __('An error occurred during tagging.', 'craftedpath-toolkit'),
                    'needsSaveError' => __('Please save the post before auto-tagging.', 'craftedpath-toolkit'),
                    'noContentError' => __('Post title and content are needed for tagging.', 'craftedpath-toolkit'),
                ]
            ]);
        }

        /**
         * AJAX handler to perform auto-tagging.
         */
        public function ajax_auto_tag_post()
        {
            check_ajax_referer('cptk_auto_tag_nonce', 'nonce');

            if (!current_user_can('edit_post', isset($_POST['post_id']) ? intval($_POST['post_id']) : 0)) {
                wp_send_json_error(['message' => __('Permission denied.', 'craftedpath-toolkit')], 403);
                return;
            }

            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $post = get_post($post_id);
            if (!$post || $post->post_status === 'auto-draft') {
                wp_send_json_error(['message' => __('Invalid Post ID or post not saved yet.', 'craftedpath-toolkit')], 400);
                return;
            }

            // --- Start Core Logic (Placeholder) ---
            // 1. Get Post Content & Title
            $post_title = $post->post_title;
            $post_content = wp_strip_all_tags($post->post_content);
            $post_excerpt = !empty($post->post_excerpt) ? wp_strip_all_tags($post->post_excerpt) : wp_trim_words($post_content, 300, '...'); // Slightly longer excerpt for tags

            if (empty(trim($post_title)) && empty(trim($post_excerpt))) {
                wp_send_json_error(['message' => __('Post title and content are empty. Cannot suggest tags.', 'craftedpath-toolkit')], 400);
                return;
            }

            // 2. Get OpenAI Settings
            $settings = $this->get_openai_settings();
            if (empty($settings['api_key'])) {
                wp_send_json_error(['message' => __('OpenAI API Key is not configured.', 'craftedpath-toolkit')], 500);
                return;
            }

            // 3. Build Prompt
            $prompt = $this->build_tagging_prompt($post_title, $post_excerpt);
            $system_message = 'You are an expert content tagger. Analyze the provided post title and content/excerpt, and suggest relevant tags.';

            // 4. Call OpenAI API
            $api_response = $this->call_openai_api($prompt, [], $system_message);
            if (is_wp_error($api_response)) {
                wp_send_json_error(['message' => $api_response->get_error_message()], 500);
                return;
            }

            // 5. Process Response (Parse JSON, Validate Tags)
            if (!isset($api_response['choices'][0]['message']['content'])) {
                error_log('[CPT AutoTag] Unexpected API response structure: ' . print_r($api_response, true));
                wp_send_json_error(['message' => __('Unexpected API response format for tags.', 'craftedpath-toolkit')], 500);
                return;
            }
            $tag_json_string = $api_response['choices'][0]['message']['content'];
            error_log('[CPT AutoTag] Raw API JSON content: ' . $tag_json_string);

            $json_start = strpos($tag_json_string, '{');
            $json_end = strrpos($tag_json_string, '}');
            if ($json_start === false || $json_end === false || $json_end < $json_start) {
                error_log('[CPT AutoTag] Could not find valid JSON braces in tag response: ' . $tag_json_string);
                wp_send_json_error(['message' => __('AI tag response did not contain a valid JSON structure.', 'craftedpath-toolkit')], 500);
                return;
            }
            $extracted_json = substr($tag_json_string, $json_start, ($json_end - $json_start + 1));
            $suggestion_data = json_decode($extracted_json, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($suggestion_data['suggested_tags']) || !is_array($suggestion_data['suggested_tags'])) {
                error_log('[CPT AutoTag] JSON Decode Error or invalid format: ' . json_last_error_msg() . ' | Extracted JSON: ' . $extracted_json);
                wp_send_json_error(['message' => __('Could not parse AI tag suggestion JSON or format invalid.', 'craftedpath-toolkit')], 500);
                return;
            }

            $suggested_tags = $suggestion_data['suggested_tags'];

            // 6. Handle empty tag list
            if (empty($suggested_tags)) {
                error_log('[CPT AutoTag] AI suggested no tags for post ID: ' . $post_id);
                // Clear existing tags if replacing is desired and AI suggests none
                wp_set_post_tags($post_id, [], false); // Replace with empty array
                wp_send_json_success([
                    'message' => __('No relevant tags suggested by AI.', 'craftedpath-toolkit'),
                    'assigned_tags' => [],
                    'tag_term_ids' => []
                ]);
                return;
            }

            // Sanitize tags
            $sanitized_tags = array_map(function ($tag) {
                return trim(sanitize_text_field($tag));
            }, $suggested_tags);
            $sanitized_tags = array_filter($sanitized_tags); // Remove empty tags after trimming/sanitizing

            if (empty($sanitized_tags)) {
                error_log('[CPT AutoTag] All suggested tags were empty after sanitization for post ID: ' . $post_id);
                wp_set_post_tags($post_id, [], false);
                wp_send_json_success([
                    'message' => __('AI suggested tags were empty after sanitization.', 'craftedpath-toolkit'),
                    'assigned_tags' => [],
                    'tag_term_ids' => []
                ]);
                return;
            }

            // 7. Assign Tags (wp_set_post_tags - replaces existing by default)
            $term_taxonomy_ids = wp_set_post_tags($post_id, $sanitized_tags, false); // false = replace

            if (is_wp_error($term_taxonomy_ids)) {
                error_log('[CPT AutoTag] Error setting post tags for post ' . $post_id . ': ' . $term_taxonomy_ids->get_error_message());
                wp_send_json_error(['message' => __('Failed to assign tags to post.', 'craftedpath-toolkit')], 500);
                return;
            }

            // 8. Get assigned tag IDs (and names for the message)
            $assigned_tags_objects = wp_get_post_tags($post_id);
            $assigned_tag_names = wp_list_pluck($assigned_tags_objects, 'name');
            $assigned_tag_ids = wp_list_pluck($assigned_tags_objects, 'term_id');

            error_log('[CPT AutoTag] Successfully assigned tags: ' . implode(', ', $assigned_tag_names) . ' for post ID: ' . $post_id);

            // 9. Send Success JSON
            wp_send_json_success([
                'message' => sprintf('%s %s', __('Tags assigned:', 'craftedpath-toolkit'), implode(', ', $assigned_tag_names)),
                'assigned_tags' => $assigned_tag_names,
                'tag_term_ids' => $assigned_tag_ids // Send IDs back to JS
            ]);

            // --- End Core Logic ---

            wp_die(); // this is required to terminate immediately and return a proper response
        }

        /**
         * Get OpenAI API settings (Helper Function - adapted).
         */
        private function get_openai_settings(): array
        {
            // This can be copied from CPT_AI_Auto_Categorize
            $options = get_option('cptk_options', []);
            return [
                'api_key' => $options['openai_api_key'] ?? '',
                'model' => $options['openai_model'] ?? 'gpt-4o',
            ];
        }

        /**
         * Call OpenAI API (Helper Function - adapted).
         */
        private function call_openai_api(string $prompt, array $options = [], string $system_message = 'You are a helpful assistant.'): array|\WP_Error
        {
            // This can be copied from CPT_AI_Auto_Categorize
            // Adjust default options if needed (e.g., temperature for tags)
            $settings = $this->get_openai_settings();
            if (empty($settings['api_key'])) {
                return new \WP_Error('api_key_missing', __('OpenAI API Key is not configured.', 'craftedpath-toolkit'));
            }
            $default_options = [
                'model' => $settings['model'],
                'max_tokens' => 150, // Allow for a few tags
                'temperature' => 0.5, // Slightly more creative for tags
            ];
            $request_options = wp_parse_args($options, $default_options);
            $headers = [
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json',
            ];
            $body = [
                'model' => $request_options['model'],
                'messages' => [
                    ['role' => 'system', 'content' => $system_message],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => floatval($request_options['temperature']),
                'max_tokens' => intval($request_options['max_tokens']),
                'response_format' => ['type' => 'json_object'], // Request JSON
            ];
            $response = wp_remote_post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'headers' => $headers,
                    'body' => wp_json_encode($body),
                    'timeout' => 45, // Slightly longer timeout for tag generation
                    'data_format' => 'body',
                ]
            );
            // Error handling copied from CPT_AI_Auto_Categorize...
            if (is_wp_error($response)) {
                error_log('[CPT AutoTag] HTTP Error: ' . $response->get_error_message());
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
                error_log(sprintf('[CPT AutoTag] API Error (%d): %s', $response_code, $error_message));
                error_log('[CPT AutoTag] API Error Body: ' . $response_body);
                return new \WP_Error('openai_api_error', sprintf(__('OpenAI API Error (%d): %s', 'craftedpath-toolkit'), $response_code, $error_message));
            }
            $decoded_body = json_decode($response_body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[CPT AutoTag] Failed to decode API JSON response. Error: ' . json_last_error_msg());
                error_log('[CPT AutoTag] Raw Body: ' . $response_body);
                return new \WP_Error('openai_response_decode_error', __('Failed to decode the response from OpenAI API.', 'craftedpath-toolkit'));
            }
            return $decoded_body;
        }

        /**
         * Build the prompt for the OpenAI API to suggest tags.
         */
        private function build_tagging_prompt(string $title, string $excerpt): string
        {
            // Implementation needed
            $prompt = "Analyze the following blog post title and excerpt.\n\n";
            $prompt .= "Title: " . $title . "\n";
            $prompt .= "Content Excerpt: " . $excerpt . "\n\n";
            $prompt .= "Instructions:\n";
            $prompt .= "1. Identify the main specific topics, keywords, or concepts discussed.
";
            $prompt .= "2. Suggest a list of 3-5 relevant tags (single words or short phrases, 1-3 words max per tag).
";
            $prompt .= "3. Focus on specific keywords, avoid overly broad terms, and do not simply repeat the main category.
";
            $prompt .= "4. If no specific, relevant tags can be identified, provide an empty list.
";
            $prompt .= "\nOutput Format:\n";
            $prompt .= "Provide your response ONLY as a valid JSON object with a single root key 'suggested_tags'. The value should be an array of strings.\n";
            $prompt .= "Example: {\"suggested_tags\": [\"WordPress Plugins\", \"AI Development\", \"ReactJS\"]}\n";
            $prompt .= "Example (No Tags): {\"suggested_tags\": []}\n";
            $prompt .= "\nDo not include any explanatory text before or after the JSON object. Just the JSON.";
            return $prompt;
        }

    } // END class CPT_AI_Auto_Tag

    CPT_AI_Auto_Tag::instance();

} // END if (!class_exists('CPT_AI_Auto_Tag')) 