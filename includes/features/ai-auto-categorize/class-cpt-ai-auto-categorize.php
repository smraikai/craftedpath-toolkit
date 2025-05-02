<?php
/**
 * CPT_AI_Auto_Categorize Class
 *
 * Handles the AI-powered automatic categorization of posts.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('CPT_AI_Auto_Categorize')) {

    class CPT_AI_Auto_Categorize
    {
        /**
         * Singleton instance
         * @var CPT_AI_Auto_Categorize|null
         */
        private static $instance = null;

        /**
         * Get singleton instance.
         *
         * @return CPT_AI_Auto_Categorize
         */
        public static function instance(): CPT_AI_Auto_Categorize
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor.
         */
        private function __construct()
        {
            $this->init();
        }

        /**
         * Initialize hooks.
         */
        private function init()
        {
            // Hook to add the meta box or button to the post editor - REMOVED
            // add_action('add_meta_boxes', [$this, 'add_meta_box']);

            // Hook to enqueue scripts for the post editor
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

            // AJAX handler for categorization
            add_action('wp_ajax_cptk_auto_categorize_post', [$this, 'ajax_auto_categorize_post']);
        }

        /**
         * Enqueue scripts and styles for the post edit screen.
         *
         * @param string $hook The current admin page hook.
         */
        public function enqueue_scripts($hook)
        {
            // Get current screen information
            $screen = get_current_screen();

            // Only proceed if we are on a block editor screen for the 'post' post type
            if (!$screen || !$screen->is_block_editor() || 'post' !== $screen->post_type) {
                // TODO: Make post type configurable?
                return;
            }

            // No longer enqueueing a separate script here.
            // We will localize data for the main build script (build/index.js)
            // which is enqueued in the main plugin file (craftedpath-toolkit.php)
            // when the SEO feature (or potentially others using the build) is active.

            // The main script should already be enqueued by CraftedPath_Toolkit::enqueue_assets
            // if the build file exists. We just add our localized data.

            // Localize script with necessary data FOR THIS FEATURE
            // Use a unique object name to avoid conflicts
            // Attach to 'wp-blocks' handle, which is reliable in the block editor.
            wp_localize_script('wp-blocks', 'cptAiAutoCategorizeData', [
                'is_enabled' => true, // Flag to tell the main script to render our panel
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cptk_auto_categorize_nonce'),
                // Note: Post ID will be fetched using JS data stores (`useSelect`) in the React component.
                'i18n' => [ // Internationalization strings for the JS component
                    'panelTitle' => __('AI Tools', 'craftedpath-toolkit'),
                    'buttonText' => __('Auto Categorize', 'craftedpath-toolkit'),
                    'loadingText' => __('Categorizing...', 'craftedpath-toolkit'),
                    'successPrefix' => __('Categorized as:', 'craftedpath-toolkit'),
                    'errorPrefix' => __('Error:', 'craftedpath-toolkit'),
                    'genericError' => __('An error occurred. Please try again.', 'craftedpath-toolkit'),
                    'apiError' => __('Could not reach AI service.', 'craftedpath-toolkit'),
                    'needsSaveError' => __('Please save the post before auto-categorizing.', 'craftedpath-toolkit'),
                    'noContentError' => __('Post title and content are needed for categorization.', 'craftedpath-toolkit'),
                ]
            ]);

            /* // OLD SCRIPT ENQUEUE - REMOVED
            // Only load on post edit screens (post.php and post-new.php)
            if ('post.php' !== $hook && 'post-new.php' !== $hook) {
                return;
            }

            // Ensure we are on a 'post' type screen (or adjust for CPTs if needed)
            $screen = get_current_screen();
            if (!$screen || 'post' !== $screen->post_type) {
                // TODO: Make this configurable or support CPTs?
                return;
            }

            // Use the already defined CPT_PLUGIN_URL constant
            $plugin_url = CPT_PLUGIN_URL;

            wp_enqueue_script(
                'cptk-ai-auto-categorize',
                $plugin_url . 'includes/features/ai-auto-categorize/js/ai-auto-categorize.js',
                ['jquery', 'wp-util', 'wp-api-fetch'], // Added wp-api-fetch for potential REST use
                CPT_VERSION, // Use the correct constant
                true // Load in footer
            );

            // Localize script with necessary data
            wp_localize_script('cptk-ai-auto-categorize', 'cptAutoCategorize', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cptk_auto_categorize_nonce'),
                'postId' => get_the_ID(), // Get current post ID
                'i18n' => [ // Internationalization strings
                    'buttonText' => __('Auto Categorize', 'craftedpath-toolkit'),
                    'loadingText' => __('Categorizing...', 'craftedpath-toolkit'),
                    'successPrefix' => __('Categorized as:', 'craftedpath-toolkit'),
                    'errorPrefix' => __('Error:', 'craftedpath-toolkit'),
                    'genericError' => __('An error occurred. Please try again.', 'craftedpath-toolkit'),
                ]
            ]);

            // Potentially enqueue CSS if needed later
            // wp_enqueue_style(
            //     'cptk-ai-auto-categorize',
            //     $plugin_url . 'includes/features/ai-auto-categorize/css/ai-auto-categorize.css',
            //     [],
            //     CPT_VERSION
            // );
            */
        }

        /**
         * Add Meta Box to Post Editor.
         * Uses classic editor hook, Gutenberg equivalent might be needed too.
         */
        /* // REMOVING CLASSIC META BOX
        public function add_meta_box()
        {
             // Add to 'post' post type. Adjust if needed for CPTs.
            add_meta_box(
                'cptk-auto-categorize-metabox',             // ID
                __('AI Auto Categorize', 'craftedpath-toolkit'), // Title
                [$this, 'render_meta_box_content'],        // Callback
                'post',                                     // Screen (post type)
                'side',                                     // Context (side, normal, advanced)
                'low'                                      // Priority
            );
            // Consider adding to other relevant post types
        }
        */

        /**
         * Render the content of the meta box.
         *
         * @param WP_Post $post The current post object.
         */
        /* // REMOVING CLASSIC META BOX
        public function render_meta_box_content($post)
        {
            // Security nonce for the button action (though main action uses AJAX nonce)
            wp_nonce_field('cptk_auto_categorize_metabox', 'cptk_auto_categorize_metabox_nonce');

            ?>
            <div id="cptk-auto-categorize-controls">
                <button type="button" id="cptk-auto-categorize-button" class="button">
                    <?php echo esc_html(__('Auto Categorize', 'craftedpath-toolkit')); // Correct: Use PHP translation function ?>
                </button>
                <span class="spinner" style="float: none; vertical-align: middle;"></span>
                <p id="cptk-auto-categorize-status" class="description" style="margin-top: 8px;"></p>
            </div>
            <p class="howto">
                <?php esc_html_e('Click the button to automatically assign the most relevant category using AI.', 'craftedpath-toolkit'); ?>
            </p>
            <?php
             // Note: This places a button in a meta box. For better Gutenberg integration,
             // a SlotFill approach (PluginSidebar, DocumentSettingPanel) would be preferred.
        }
        */


        /**
         * AJAX handler to perform auto-categorization.
         */
        public function ajax_auto_categorize_post()
        {
            // 1. Verify Nonce
            check_ajax_referer('cptk_auto_categorize_nonce', 'nonce');

            // 2. Check User Permissions
            if (!current_user_can('edit_post', isset($_POST['post_id']) ? intval($_POST['post_id']) : 0)) { // Check edit capability for specific post
                wp_send_json_error(['message' => __('Permission denied.', 'craftedpath-toolkit')], 403);
                return;
            }

            // 3. Get Post ID from AJAX request
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $post = get_post($post_id);
            if (!$post || $post->post_status === 'auto-draft') {
                wp_send_json_error(['message' => __('Invalid Post ID or post not saved yet.', 'craftedpath-toolkit')], 400);
                return;
            }

            // --- Start Core Logic ---

            // 4. Get Post Content & Title
            $post_title = $post->post_title;
            $post_content = wp_strip_all_tags($post->post_content); // Strip tags for cleaner input
            // Maybe truncate content if too long?
            $post_excerpt = !empty($post->post_excerpt) ? wp_strip_all_tags($post->post_excerpt) : wp_trim_words($post_content, 200, '...'); // Use excerpt or trim content

            if (empty(trim($post_title)) && empty(trim($post_excerpt))) {
                wp_send_json_error(['message' => __('Post title and content are empty. Cannot categorize.', 'craftedpath-toolkit')], 400);
                return;
            }

            // 5. Get Existing Categories
            $existing_terms = get_terms([
                'taxonomy' => 'category',
                'hide_empty' => false, // Include empty categories
                'fields' => 'names' // Only get the names
            ]);
            if (is_wp_error($existing_terms)) {
                error_log('[CPT AutoCategorize] Error getting terms: ' . $existing_terms->get_error_message());
                wp_send_json_error(['message' => __('Could not retrieve existing categories.', 'craftedpath-toolkit')], 500);
                return;
            }
            // Exclude 'Uncategorized' if it exists, as it's usually not a useful suggestion
            $existing_categories = array_filter($existing_terms, function ($term_name) {
                return strtolower($term_name) !== 'uncategorized';
            });

            // 6. Get OpenAI Settings
            $settings = $this->get_openai_settings();
            if (empty($settings['api_key'])) {
                wp_send_json_error(['message' => __('OpenAI API Key is not configured.', 'craftedpath-toolkit')], 500);
                return;
            }

            // 7. Build Prompt
            $prompt = $this->build_categorization_prompt($post_title, $post_excerpt, $existing_categories);
            $system_message = 'You are an expert content classifier. Analyze the provided post title and content/excerpt, and determine the single most appropriate category. Prefer existing categories if a good match is found.';

            // 8. Call OpenAI API
            $api_response = $this->call_openai_api($prompt, [], $system_message);

            if (is_wp_error($api_response)) {
                wp_send_json_error(['message' => $api_response->get_error_message()], 500);
                return;
            }

            // 9. Process Response (Placeholder for next step)
            if (!isset($api_response['choices'][0]['message']['content'])) {
                error_log('[CPT AutoCategorize] Unexpected API response structure: ' . print_r($api_response, true));
                wp_send_json_error(['message' => __('Unexpected API response format.', 'craftedpath-toolkit')], 500);
                return;
            }

            // 9a. Parse and Validate JSON Response
            // Try to find JSON within potential markdown code blocks or surrounding text
            $category_json_string = $api_response['choices'][0]['message']['content'];
            error_log('[CPT AutoCategorize] Raw API JSON content: ' . $category_json_string); // Log raw response for debugging

            $json_start = strpos($category_json_string, '{');
            $json_end = strrpos($category_json_string, '}');

            if ($json_start === false || $json_end === false || $json_end < $json_start) {
                error_log('[CPT AutoCategorize] Could not find valid JSON braces in response: ' . $category_json_string);
                wp_send_json_error(['message' => __('AI response did not contain a valid JSON structure.', 'craftedpath-toolkit')], 500);
                return;
            }

            $extracted_json = substr($category_json_string, $json_start, ($json_end - $json_start + 1));
            $suggestion_data = json_decode($extracted_json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[CPT AutoCategorize] JSON Decode Error: ' . json_last_error_msg() . ' | Extracted JSON: ' . $extracted_json);
                wp_send_json_error(['message' => __('Could not parse AI suggestion JSON.', 'craftedpath-toolkit')], 500);
                return;
            }

            if (!isset($suggestion_data['suggested_category']) || !isset($suggestion_data['is_new']) || !is_string($suggestion_data['suggested_category']) || !is_bool($suggestion_data['is_new'])) {
                error_log('[CPT AutoCategorize] Invalid JSON format received: ' . $extracted_json);
                wp_send_json_error(['message' => __('AI suggestion format is invalid.', 'craftedpath-toolkit')], 500);
                return;
            }

            $suggested_name = trim(sanitize_text_field($suggestion_data['suggested_category']));
            $is_new = (bool) $suggestion_data['is_new'];

            if (empty($suggested_name)) {
                error_log('[CPT AutoCategorize] Empty category name suggested.');
                wp_send_json_error(['message' => __('AI suggested an empty category name.', 'craftedpath-toolkit')], 500);
                return;
            }

            // 9b. Find or Create Category Term
            $category_id = null;
            $final_category_name = $suggested_name; // Initialize with suggested name

            if ($is_new) {
                // Try to create the new term
                $term_result = wp_insert_term($suggested_name, 'category');
                if (is_wp_error($term_result)) {
                    // If it already exists, get the existing ID
                    if ($term_result->get_error_code() === 'term_exists') {
                        $existing_term = get_term_by('name', $suggested_name, 'category');
                        if ($existing_term) {
                            $category_id = $existing_term->term_id;
                            $final_category_name = $existing_term->name; // Use existing name casing
                            error_log('[CPT AutoCategorize] AI suggested new term, but it already existed: ' . $suggested_name);
                        } else {
                            error_log('[CPT AutoCategorize] Error finding term after term_exists error: ' . $suggested_name);
                            wp_send_json_error(['message' => __('Error finding existing category after suggesting new.', 'craftedpath-toolkit')], 500);
                            return;
                        }
                    } else {
                        // Other error during insert
                        error_log('[CPT AutoCategorize] Error inserting new term: ' . $term_result->get_error_message());
                        wp_send_json_error(['message' => __('Could not create new category:', 'craftedpath-toolkit') . ' ' . $term_result->get_error_message()], 500);
                        return;
                    }
                } else {
                    // Successfully created
                    $category_id = $term_result['term_id'];
                    error_log('[CPT AutoCategorize] Successfully created new category: ' . $suggested_name . ' (ID: ' . $category_id . ')');
                }
            } else {
                // AI suggested using an existing term, find it (case-insensitive check might be needed)
                $term = get_term_by('name', $suggested_name, 'category');
                if ($term) {
                    $category_id = $term->term_id;
                    $final_category_name = $term->name; // Use actual term name casing
                } else {
                    // AI suggested an existing category, but it wasn't found.
                    error_log('[CPT AutoCategorize] AI suggested existing category (' . esc_html($suggested_name) . '), but it was not found. Attempting to create it as a fallback.');

                    // Fallback: Assume API was mistaken about is_new flag, try creating it.
                    $term_result = wp_insert_term($suggested_name, 'category');

                    if (!is_wp_error($term_result)) {
                        // Successfully created the term
                        $category_id = $term_result['term_id'];
                        $final_category_name = $suggested_name; // Use the name we just created
                        error_log('[CPT AutoCategorize] Successfully created category \'' . esc_html($final_category_name) . '\' (ID: ' . esc_html($category_id) . ') after API suggested existing but not found.');
                    } else {
                        // Failed to create the term after get_term_by also failed.
                        // This could happen if there was a race condition or another issue.
                        error_log('[CPT AutoCategorize] Error inserting term \'' . esc_html($suggested_name) . '\' after get_term_by failed: ' . $term_result->get_error_message());
                        // Send error only if term creation failed *after* the initial check failed.
                        wp_send_json_error(['message' => __('AI suggested an existing category that was not found, and then failed to create it.', 'craftedpath-toolkit')], 500);
                        return;
                    }
                }
            }

            if (empty($category_id)) {
                error_log('[CPT AutoCategorize] Failed to determine a valid category ID for: ' . $suggested_name);
                wp_send_json_error(['message' => __('Could not determine a valid category ID.', 'craftedpath-toolkit')], 500);
                return;
            }

            // 10. Assign Category to Post (Replace existing categories)
            $result = wp_set_post_categories($post_id, [$category_id], false); // false = replace

            if (is_wp_error($result)) {
                error_log('[CPT AutoCategorize] Error setting post categories for post ' . $post_id . ': ' . $result->get_error_message());
                wp_send_json_error(['message' => __('Failed to assign category to post.', 'craftedpath-toolkit')], 500);
                return;
            }

            // Dummy success response for now
            wp_send_json_success([
                'message' => sprintf('%s %s', __('Categorized as:', 'craftedpath-toolkit'), esc_html($final_category_name)),
                'category_name' => $final_category_name,
                'category_id' => $category_id
            ]);

            // --- End Core Logic ---

            wp_die(); // this is required to terminate immediately and return a proper response
        }

        /**
         * Get OpenAI API settings (Helper Function - adapted from other features).
         *
         * @return array OpenAI settings array with api_key and model.
         */
        private function get_openai_settings(): array
        {
            $options = get_option('cptk_options', []);
            return [
                'api_key' => $options['openai_api_key'] ?? '',
                'model' => $options['openai_model'] ?? 'gpt-4o', // Default model
            ];
        }

        /**
         * Call OpenAI API (Helper Function - adapted from other features).
         *
         * @param string $prompt The prompt to send to the API.
         * @param array $options Additional options for the API call (temperature, max_tokens).
         * @param string $system_message The system message for the chat completion.
         * @return array|WP_Error The decoded JSON response body as an array or a WP_Error object.
         */
        private function call_openai_api(string $prompt, array $options = [], string $system_message = 'You are a helpful assistant.'): array|\WP_Error
        {
            $settings = $this->get_openai_settings();

            if (empty($settings['api_key'])) {
                return new \WP_Error('api_key_missing', __('OpenAI API Key is not configured in CraftedPath Toolkit settings.', 'craftedpath-toolkit'));
            }

            $default_options = [
                'model' => $settings['model'],
                'max_tokens' => 100, // Keep response short for category suggestion
                'temperature' => 0.3, // Low temp for more deterministic category choice
            ];

            $request_options = wp_parse_args($options, $default_options);

            $headers = [
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json',
            ];

            // Ensure JSON object format is requested
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
                    'timeout' => 30, // Shorter timeout for category suggestion
                    'data_format' => 'body',
                ]
            );

            if (is_wp_error($response)) {
                error_log('[CPT AutoCategorize] HTTP Error: ' . $response->get_error_message());
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
                error_log(sprintf('[CPT AutoCategorize] API Error (%d): %s', $response_code, $error_message));
                error_log('[CPT AutoCategorize] API Error Body: ' . $response_body);
                return new \WP_Error(
                    'openai_api_error',
                    sprintf(__('OpenAI API Error (%d): %s', 'craftedpath-toolkit'), $response_code, $error_message)
                );
            }

            $decoded_body = json_decode($response_body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[CPT AutoCategorize] Failed to decode API JSON response. Error: ' . json_last_error_msg());
                error_log('[CPT AutoCategorize] Raw Body: ' . $response_body);
                return new \WP_Error(
                    'openai_response_decode_error',
                    __('Failed to decode the response from OpenAI API.', 'craftedpath-toolkit')
                );
            }

            // Return the decoded body
            return $decoded_body;
        }

        /**
         * Build the prompt for the OpenAI API to suggest a category.
         *
         * @param string $title Post title.
         * @param string $excerpt Post excerpt or trimmed content.
         * @param array $existing_categories List of existing category names.
         * @return string The generated prompt.
         */
        private function build_categorization_prompt(string $title, string $excerpt, array $existing_categories): string
        {
            $prompt = "Analyze the following blog post title and excerpt/content.\n\n";
            $prompt .= "Title: " . $title . "\n";
            $prompt .= "Content Excerpt: " . $excerpt . "\n\n";

            if (!empty($existing_categories)) {
                $prompt .= "Here is a list of existing categories:\n";
                $prompt .= "- " . implode("\n- ", $existing_categories) . "\n\n";
                $prompt .= "Instructions:\n";
                $prompt .= "1. Determine the *single* most relevant category for this post.\n";
                $prompt .= "2. If one of the existing categories listed above is a strong match, choose that exact category name.\n";
                $prompt .= "3. If *none* of the existing categories are a good fit, suggest a *new*, concise category name (1-3 words max) that accurately reflects the post's main topic.\n";
            } else {
                $prompt .= "Instructions:\n";
                $prompt .= "1. Determine the *single* most relevant category for this post.\n";
                $prompt .= "2. Suggest a concise category name (1-3 words max) that accurately reflects the post's main topic.\n";
            }

            $prompt .= "\nOutput Format:\n";
            $prompt .= "Provide your response ONLY as a valid JSON object with two keys:\n";
            $prompt .= "- \"suggested_category\": (string) The chosen or newly suggested category name.\n";
            $prompt .= "- \"is_new\": (boolean) Set to `true` if you are suggesting a new category name, `false` if you are using one from the existing list.\n";
            $prompt .= "Example for existing category: {\"suggested_category\": \"WordPress Development\", \"is_new\": false}\n";
            $prompt .= "Example for new category: {\"suggested_category\": \"AI Tools\", \"is_new\": true}\n";
            $prompt .= "\nDo not include any explanatory text before or after the JSON object. Just the JSON.";

            return $prompt;
        }

        // --- Add methods for response processing, category handling etc. later ---

    } // END class CPT_AI_Auto_Categorize

    // Instantiate the class using the singleton pattern
    CPT_AI_Auto_Categorize::instance();

} // END if (!class_exists('CPT_AI_Auto_Categorize')) 