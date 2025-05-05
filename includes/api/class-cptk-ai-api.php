<?php
/**
 * Handles REST API endpoints for CraftedPath Toolkit AI features.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class CPTK_AI_API
{
    /**
     * Namespace for the REST API routes.
     */
    const NAMESPACE = 'craftedpath-toolkit/v1';

    /**
     * Registers the REST API routes.
     */
    public function register_routes()
    {
        register_rest_route(
            self::NAMESPACE ,
            '/generate-content',
            array(
                'methods' => WP_REST_Server::CREATABLE, // POST requests
                'callback' => array($this, 'handle_generate_request'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => array( // Define expected arguments
                    'prompt' => array(
                        'required' => true,
                        'validate_callback' => function ($param, $request, $key) {
                            return is_string($param) && !empty(trim($param));
                        },
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'description' => __('The user prompt for content generation.', 'craftedpath-toolkit'),
                    ),
                ),
            )
        );
    }

    /**
     * Permission check for the API endpoint.
     * Ensures the user can edit posts and verifies a nonce.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error True if the user has permission, WP_Error otherwise.
     */
    public function check_permissions(WP_REST_Request $request)
    {
        // Verify nonce (sent via X-WP-Nonce header by apiFetch automatically)
        // The nonce should be created in the JS part of the block editor.
        // We will need to add nonce creation/localization later.
        // For now, let's check capability.
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden_context',
                __('Sorry, you are not allowed to generate content.', 'craftedpath-toolkit'),
                array('status' => rest_authorization_required_code())
            );
        }

        // Nonce check will be added here once the frontend sends it.
        // $nonce = $request->get_header('X-WP-Nonce');
        // if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) { // Default nonce apiFetch uses
        //     return new WP_Error( 'rest_invalid_nonce', __( 'Nonce is invalid.', 'craftedpath-toolkit' ), array( 'status' => 403 ) );
        // }


        return true; // Or return true temporarily if nonce is not yet implemented
    }

    /**
     * Handles the request to generate AI content.
     *
     * @param WP_REST_Request $request The request object containing the 'prompt'.
     * @return WP_REST_Response|WP_Error Response object with generated content or WP_Error on failure.
     */
    public function handle_generate_request(WP_REST_Request $request)
    {
        $prompt = $request->get_param('prompt');

        // 1. Get OpenAI API Key and Model from settings
        $options = get_option('cptk_options');
        $api_key = $options['openai_api_key'] ?? null;
        $model = $options['openai_model'] ?? 'gpt-4o'; // Default from settings

        if (empty($api_key)) {
            return new WP_Error(
                'api_key_missing',
                __('OpenAI API key is not configured in CraftedPath Toolkit settings.', 'craftedpath-toolkit'),
                array('status' => 400) // Bad Request or 500 Internal Server Error? 400 seems ok.
            );
        }

        // 2. Prepare request for OpenAI API
        $api_url = 'https://api.openai.com/v1/chat/completions'; // Using Chat Completions endpoint
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        );
        $body = array(
            'model' => $model,
            'messages' => [
                // System message to guide the AI (IMPORTANT!)
                ['role' => 'system', 'content' => 'You are a helpful assistant. Generate content suitable for a WordPress website based on the user\'s prompt. Format the output using simple Markdown (e.g., # for H1, ## for H2, paragraphs separated by blank lines, * for list items). Do not include any preamble or explanation, only the generated content.'],
                // User's prompt
                ['role' => 'user', 'content' => $prompt]
            ],
            // Add other parameters like max_tokens, temperature if needed
            'max_tokens' => 1000, // Example limit
        );

        // 3. Call OpenAI API using wp_remote_post
        $response = wp_remote_post(
            $api_url,
            array(
                'method' => 'POST',
                'headers' => $headers,
                'body' => wp_json_encode($body),
                'timeout' => 60, // Increase timeout for potentially long AI responses
            )
        );

        // 4. Handle the response
        if (is_wp_error($response)) {
            // Network or WordPress HTTP API error
            error_log('CPT AI Request Error (wp_remote_post): ' . $response->get_error_message());
            return new WP_Error(
                'api_request_failed',
                __('Failed to communicate with the AI service.', 'craftedpath-toolkit'),
                array('status' => 500)
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if ($response_code >= 400 || isset($result['error'])) {
            // API returned an error (e.g., bad API key, rate limit, model error)
            $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown API error';
            error_log('CPT AI API Error (' . $response_code . '): ' . $error_message);
            return new WP_Error(
                'api_error',
                sprintf(__('AI service returned an error: %s', 'craftedpath-toolkit'), $error_message),
                array('status' => $response_code) // Pass the original status code
            );
        }

        // 5. Extract generated content
        // Structure depends on the API (Chat Completions usually in choices[0].message.content)
        $generated_content = $result['choices'][0]['message']['content'] ?? '';

        if (empty(trim($generated_content))) {
            // AI returned empty content
            return new WP_Error(
                'api_empty_response',
                __('The AI service returned an empty response.', 'craftedpath-toolkit'),
                array('status' => 500)
            );
        }


        // 6. Return success response
        return new WP_REST_Response(array(
            'success' => true,
            'content' => $generated_content, // Send the raw Markdown content back
        ), 200);
    }
}

// Hook into rest_api_init to register the routes
function cptk_register_ai_api_routes()
{
    $controller = new CPTK_AI_API();
    $controller->register_routes();
}
add_action('rest_api_init', 'cptk_register_ai_api_routes');