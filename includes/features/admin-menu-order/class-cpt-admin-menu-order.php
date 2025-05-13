<?php
/**
 * Admin Menu Order Feature for CraftedPath Toolkit
 */
if (!defined('ABSPATH')) {
    exit;
}

class CPT_Admin_Menu_Order
{
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_save_admin_menu_order', array($this, 'save_admin_menu_order'));
        add_action('wp_ajax_auto_sort_menu', array($this, 'auto_sort_menu'));
        add_filter('menu_order', array($this, 'apply_custom_menu_order'), 99);
        add_filter('custom_menu_order', '__return_true');
    }

    public function enqueue_assets($hook)
    {
        // Debug the hook
        error_log('Current hook: ' . $hook);

        if ($hook === 'craftedpath_page_cpt-admin-menu-order') {
            // Enqueue the main plugin admin CSS for consistent styling
            wp_enqueue_style(
                'craftedpath-toolkit-admin',
                CPT_PLUGIN_URL . 'includes/admin/css/settings.css',
                array(),
                CPT_VERSION
            );

            // Enqueue jQuery UI Sortable and its dependencies
            wp_enqueue_style('wp-jquery-ui-dialog'); // This includes basic jQuery UI styles
            wp_enqueue_script('jquery-ui-sortable');

            // Enqueue our custom styles and scripts
            wp_enqueue_style(
                'cpt-admin-menu-order',
                CPT_PLUGIN_URL . 'includes/features/admin-menu-order/css/admin-menu-order.css',
                array('dashicons', 'wp-jquery-ui-dialog', 'craftedpath-toolkit-admin'),
                CPT_VERSION
            );

            wp_enqueue_script(
                'cpt-admin-menu-order',
                CPT_PLUGIN_URL . 'includes/features/admin-menu-order/js/admin-menu-order.js',
                array('jquery', 'jquery-ui-sortable'),
                CPT_VERSION,
                true
            );

            // Localize the script with necessary data
            wp_localize_script(
                'cpt-admin-menu-order',
                'cptAdminMenuOrder',
                array(
                    'nonce' => wp_create_nonce('cpt_admin_menu_order_nonce'),
                    'ajaxurl' => admin_url('admin-ajax.php')
                )
            );
        }
    }

    public function render_admin_menu_order_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap craftedpath-settings">
            <!-- Header Card -->
            <?php cptk_render_header_card(); ?>

            <!-- Content Area -->
            <div class="craftedpath-content">
                <?php
                // Footer content with Save button
                ob_start();
                submit_button(__('Save Order', 'craftedpath-toolkit'), 'primary', 'save_menu_order', false, ['id' => 'cpt-menu-order-save-btn', 'disabled' => 'disabled']);
                $footer_html = ob_get_clean();

                // Render the card
                cptk_render_card(
                    __('Admin Menu Order', 'craftedpath-toolkit'),
                    '<i class="iconoir-sparks" style="vertical-align: text-bottom; margin-right: 5px;"></i>',
                    array($this, 'render_menu_order_content'),
                    $footer_html
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the menu order content
     */
    public function render_menu_order_content()
    {
        global $menu;
        ?>
        <div class="cpt-menu-order-container">
            <p><?php esc_html_e('Drag and drop menu items to reorder them. Changes will take effect after you click Save Order.', 'craftedpath-toolkit'); ?>
            </p>

            <div class="cpt-menu-actions">
                <button type="button" class="button button-secondary" id="auto-sort-btn">
                    <i class="iconoir-sparks" style="vertical-align: middle; margin-right: 4px;"></i>
                    <?php esc_html_e('Auto Sort with AI', 'craftedpath-toolkit'); ?>
                </button>
            </div>

            <ul class="cpt-menu-order-list">
                <?php
                foreach ($menu as $menu_item) {
                    if (!empty($menu_item[0])) {
                        $menu_id = sanitize_title($menu_item[2]);
                        echo '<li class="cpt-menu-order-item" data-menu-id="' . esc_attr($menu_id) . '">';
                        echo '<span class="dashicons dashicons-menu"></span>';
                        echo '<span class="menu-title">' . wp_strip_all_tags($menu_item[0]) . '</span>';
                        echo '</li>';
                    }
                }
                ?>
            </ul>

            <div id="menu_order_status" class="cpt-status-message" style="display: none;"></div>
            <div id="menu_order_error" class="cpt-error-message" style="display: none;"></div>
            <div id="menu_order_loading" class="cpt-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <p><?php esc_html_e('Processing with AI...', 'craftedpath-toolkit'); ?></p>
            </div>

            <?php
            // Display AI explanation if available
            $ai_explanation = get_option('cpt_admin_menu_ai_explanation', '');
            if (!empty($ai_explanation)) {
                ?>
                <div class="cpt-ai-explanation">
                    <h3><i class="iconoir-brain" style="vertical-align: middle; margin-right: 5px;"></i>
                        <?php esc_html_e('AI Organization Logic', 'craftedpath-toolkit'); ?></h3>
                    <p><?php echo esc_html($ai_explanation); ?></p>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }

    public function save_admin_menu_order()
    {
        // Check nonce
        if (!check_ajax_referer('cpt_admin_menu_order_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to modify menu order.');
            return;
        }

        // Get and sanitize the menu order
        $menu_order = isset($_POST['menu_order']) ? (array) $_POST['menu_order'] : array();

        if (empty($menu_order)) {
            wp_send_json_error('No menu items to save.');
            return;
        }

        $menu_order = array_map('sanitize_text_field', $menu_order);

        // Save the menu order
        update_option('cpt_admin_menu_order', $menu_order);

        // Success response with menu data
        wp_send_json_success(array(
            'message' => __('Menu order saved successfully.', 'craftedpath-toolkit'),
            'menu_order' => $menu_order
        ));
    }

    public function apply_custom_menu_order($menu_order)
    {
        global $menu;

        $saved_order = get_option('cpt_admin_menu_order', array());
        if (empty($saved_order)) {
            return $menu_order;
        }

        // Create a mapping of menu slugs to their positions
        $custom_order = array_flip($saved_order);

        // Sort the menu items based on the saved order
        usort($menu, function ($a, $b) use ($custom_order) {
            if (empty($a[2]) || empty($b[2])) {
                return 0;
            }

            $a_slug = sanitize_title($a[2]);
            $b_slug = sanitize_title($b[2]);

            $a_pos = isset($custom_order[$a_slug]) ? $custom_order[$a_slug] : 999;
            $b_pos = isset($custom_order[$b_slug]) ? $custom_order[$b_slug] : 999;

            return $a_pos - $b_pos;
        });

        return $menu_order;
    }

    /**
     * Get OpenAI API settings from the option
     *
     * @return array OpenAI settings array with api_key and model
     */
    public function get_openai_settings()
    {
        $options = get_option('cptk_options', array());

        // Default to GPT-4o if not specified
        $default_model = 'gpt-4o';
        $model = isset($options['openai_model']) && !empty($options['openai_model'])
            ? $options['openai_model']
            : $default_model;

        return array(
            'api_key' => isset($options['openai_api_key']) ? $options['openai_api_key'] : '',
            'model' => $model,
            'temperature' => isset($options['openai_temperature']) ? (float) $options['openai_temperature'] : 0.5,
        );
    }

    /**
     * Call OpenAI API to automatically sort the admin menu
     */
    public function auto_sort_menu()
    {
        // Check nonce
        if (!check_ajax_referer('cpt_admin_menu_order_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to modify menu order.');
            return;
        }

        // Get current menu items
        global $menu;

        // The $menu global might not be available during AJAX calls
        // We need to load the admin menu manually
        if (empty($menu)) {
            // Load the necessary admin files if they're not already loaded
            if (!function_exists('wp_admin_css_color')) {
                require_once(ABSPATH . 'wp-admin/includes/misc.php');
            }

            if (!function_exists('add_menu_page')) {
                require_once(ABSPATH . 'wp-admin/includes/admin.php');
            }

            // Make sure the global menu is initialized
            if (!did_action('admin_menu') && current_user_can('list_users')) {
                do_action('_admin_menu');
            }
        }

        // Check again if menu is populated
        if (empty($menu)) {
            // If still empty, try to get menu items directly from database
            $menu_items = $this->get_registered_menu_items();

            if (empty($menu_items)) {
                wp_send_json_error('No menu items found. Please try again from the admin dashboard.');
                return;
            }
        } else {
            // Convert the menu global to our format
            $menu_items = array();
            foreach ($menu as $item) {
                if (!empty($item[0])) {
                    $menu_items[] = array(
                        'title' => html_entity_decode(wp_strip_all_tags($item[0])),
                        'id' => sanitize_title($item[2])
                    );
                }
            }
        }

        // Get API settings
        $settings = $this->get_openai_settings();
        if (empty($settings['api_key'])) {
            wp_send_json_error('OpenAI API Key is not configured. Please add it in the CraftedPath Settings page.');
            return;
        }

        // Prepare categories for the prompt
        $categories = array(); // Track existing categories/sections
        foreach ($menu_items as $item) {
            // Try to identify category from item name
            $parts = explode(' ', $item['title']);
            if (count($parts) > 1) {
                $potential_category = $parts[0];
                if (!isset($categories[$potential_category])) {
                    $categories[$potential_category] = 0;
                }
                $categories[$potential_category]++;
            }
        }

        // Build a more detailed prompt
        $prompt = $this->build_ai_prompt($menu_items, $categories);

        // Call OpenAI API
        $response = $this->call_openai_api($prompt);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        // Process the response to get the sorted menu
        $sorted_menu = $this->process_ai_response($response, $menu_items);

        if (empty($sorted_menu)) {
            wp_send_json_error('Failed to process AI response.');
            return;
        }

        // Return the sorted menu
        wp_send_json_success(array(
            'message' => __('Menu sorted successfully with AI.', 'craftedpath-toolkit'),
            'sorted_menu' => $sorted_menu
        ));
    }

    /**
     * Build a detailed prompt for the AI
     * 
     * @param array $menu_items The menu items to sort
     * @param array $categories Potential categories detected in menu items
     * @return string The prompt to send to the API
     */
    private function build_ai_prompt($menu_items, $categories)
    {
        // Start with general instructions
        $prompt = "You are a WordPress expert helping organize the admin menu for better user experience.\n\n";

        // Add context about WordPress admin organization
        $prompt .= "WordPress admin menus typically follow these principles:\n";
        $prompt .= "1. Dashboard should be first\n";
        $prompt .= "2. Content-related items (Posts, Pages, Media) come next\n";
        $prompt .= "3. Design/appearance items (Themes, Customizer) follow content\n";
        $prompt .= "4. Functionality items (Plugins, Users, Tools) come next\n";
        $prompt .= "5. System/settings items usually come last\n\n";

        // Add potential categories if we found any
        if (!empty($categories)) {
            $prompt .= "I've identified these potential category groupings from the menu items:\n";
            foreach ($categories as $category => $count) {
                if ($count > 1) {
                    $prompt .= "- {$category} ({$count} items)\n";
                }
            }
            $prompt .= "\n";
        }

        // List the actual menu items
        $prompt .= "Here are the current WordPress admin menu items:\n\n";
        foreach ($menu_items as $item) {
            $prompt .= "- " . $item['title'] . " (ID: " . $item['id'] . ")\n";
        }

        // Detailed instructions for output format
        $prompt .= "\nPlease organize these items into logical groups, following WordPress conventions and best UX practices.\n";
        $prompt .= "Return a JSON object with this exact format:\n";
        $prompt .= "{\n  \"menu_order\": [\"id1\", \"id2\", ...],\n";
        $prompt .= "  \"explanation\": \"Brief explanation of your organization logic\"\n}";

        return $prompt;
    }

    /**
     * Call OpenAI API
     *
     * @param string $prompt The prompt to send to the API
     * @return array|WP_Error The API response or error
     */
    private function call_openai_api($prompt)
    {
        $settings = $this->get_openai_settings();

        $headers = array(
            'Authorization' => 'Bearer ' . $settings['api_key'],
            'Content-Type' => 'application/json',
        );

        $body = array(
            'model' => $settings['model'],
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a WordPress admin menu organizer. Your task is to logically group and sort menu items for better UX.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => $settings['temperature'],
            'max_tokens' => 1500,
            'response_format' => array('type' => 'json_object')
        );

        // Log the API request for debugging (only if WP_DEBUG is enabled)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('OpenAI API Request: ' . wp_json_encode([
                'url' => 'https://api.openai.com/v1/chat/completions',
                'model' => $settings['model'],
                'temperature' => $settings['temperature'],
                'prompt_length' => strlen($prompt)
            ]));
        }

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'headers' => $headers,
                'body' => wp_json_encode($body),
                'timeout' => 30,
                'data_format' => 'body',
            )
        );

        if (is_wp_error($response)) {
            error_log('OpenAI API Error: ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error.';

            // Log the error
            error_log('OpenAI API Error: ' . $error_message);

            return new WP_Error('api_error', $error_message);
        }

        return $body;
    }

    /**
     * Process the AI API response to get the sorted menu
     *
     * @param array $response The API response
     * @param array $original_items The original menu items
     * @return array The sorted menu item IDs
     */
    private function process_ai_response($response, $original_items)
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            error_log('Invalid OpenAI API response: ' . wp_json_encode($response));
            return array();
        }

        $content = $response['choices'][0]['message']['content'];

        // Try to parse JSON response
        $data = json_decode($content, true);

        // If parsing failed, try to extract JSON from the text
        if (json_last_error() !== JSON_ERROR_NONE) {
            $pattern = '/\{[\s\S]*\}/';
            if (preg_match($pattern, $content, $matches)) {
                $data = json_decode($matches[0], true);
            }
        }

        // Check if response is a valid array
        if (!is_array($data)) {
            error_log('Failed to parse OpenAI API response as JSON: ' . $content);

            // Fallback: Try to parse a simple array if JSON parsing failed
            if (preg_match_all('/\"([^\"]+)\"/', $content, $matches)) {
                return $matches[1];
            }

            return array();
        }

        // If we have a menu_order key, use that
        if (isset($data['menu_order']) && is_array($data['menu_order'])) {
            // Store the explanation if available for later use
            if (isset($data['explanation'])) {
                update_option('cpt_admin_menu_ai_explanation', sanitize_text_field($data['explanation']));
            }

            return $data['menu_order'];
        }

        // Otherwise use the first array found
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                return $value;
            }
        }

        // Fall back to using the entire response if it's an array
        return $data;
    }

    /**
     * Get registered menu items from the database
     * 
     * @return array Menu items in our standard format
     */
    private function get_registered_menu_items()
    {
        // These are the core WordPress menu items we know will exist
        $default_items = array(
            array('title' => 'Dashboard', 'id' => 'index.php'),
            array('title' => 'Posts', 'id' => 'edit.php'),
            array('title' => 'Media', 'id' => 'upload.php'),
            array('title' => 'Pages', 'id' => 'edit.php?post_type=page'),
            array('title' => 'Comments', 'id' => 'edit-comments.php'),
            array('title' => 'Appearance', 'id' => 'themes.php'),
            array('title' => 'Plugins', 'id' => 'plugins.php'),
            array('title' => 'Users', 'id' => 'users.php'),
            array('title' => 'Tools', 'id' => 'tools.php'),
            array('title' => 'Settings', 'id' => 'options-general.php')
        );

        // Get custom post types to add them to the menu as well
        $post_types = get_post_types(array(
            'public' => true,
            '_builtin' => false
        ), 'objects');

        foreach ($post_types as $post_type) {
            $default_items[] = array(
                'title' => $post_type->labels->name,
                'id' => 'edit.php?post_type=' . $post_type->name
            );
        }

        // Try to get previously saved menu order to find additional items
        $saved_order = get_option('cpt_admin_menu_order', array());
        if (!empty($saved_order)) {
            foreach ($saved_order as $menu_id) {
                $found = false;
                foreach ($default_items as $item) {
                    if ($item['id'] === $menu_id) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    // This is a custom menu item, try to get a title from its ID
                    $title = ucwords(str_replace(array('-', '.php', '?'), array(' ', '', ' '), $menu_id));
                    $default_items[] = array(
                        'title' => $title,
                        'id' => $menu_id
                    );
                }
            }
        }

        return $default_items;
    }
}
