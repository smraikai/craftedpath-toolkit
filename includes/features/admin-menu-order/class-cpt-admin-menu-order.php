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
        add_action('wp_ajax_auto_sort_menu', array($this, 'ajax_auto_sort'));
        add_filter('menu_order', array($this, 'apply_custom_menu_order'), 99);
        add_filter('custom_menu_order', array($this, 'enable_custom_menu_order'));

        // Log when the class is initialized
        error_log('CPT_Admin_Menu_Order class initialized');
    }

    /**
     * Enable custom menu order
     */
    public function enable_custom_menu_order()
    {
        error_log('enable_custom_menu_order filter called');
        return true;
    }

    public function enqueue_assets($hook)
    {
        // Debug the hook
        error_log('enqueue_assets called on hook: ' . $hook);

        // Check for all possible hook names for our admin page
        if ($hook === 'craftedpath_page_cpt-admin-menu-order' || $hook === 'craftedpath-toolkit_page_cpt-admin-menu-order') {
            error_log('Enqueueing Admin Menu Order assets');
            // Enqueue the main plugin admin CSS for consistent styling
            wp_enqueue_style(
                'craftedpath-toolkit-admin',
                CPT_PLUGIN_URL . 'includes/admin/css/settings.css',
                array(),
                CPT_VERSION
            );

            // Enqueue Sortable.js instead of jQuery UI Sortable
            wp_enqueue_script(
                'sortablejs',
                CPT_PLUGIN_URL . 'assets/js/vendor/Sortable.min.js',
                array('jquery'),
                '1.15.6',
                true
            );

            // Enqueue Toast.js for notifications
            wp_enqueue_script(
                'cpt-toast-js',
                CPT_PLUGIN_URL . 'assets/js/toast.js',
                array('jquery'),
                CPT_VERSION,
                true
            );

            wp_enqueue_style(
                'cpt-toast-css',
                CPT_PLUGIN_URL . 'assets/css/toast.css',
                array(),
                CPT_VERSION
            );

            // Enqueue our custom styles and scripts
            wp_enqueue_style(
                'cpt-admin-menu-order',
                CPT_PLUGIN_URL . 'includes/features/admin-menu-order/css/admin-menu-order.css',
                array('dashicons', 'craftedpath-toolkit-admin'),
                CPT_VERSION
            );

            wp_enqueue_script(
                'cpt-admin-menu-order',
                CPT_PLUGIN_URL . 'includes/features/admin-menu-order/js/admin-menu-order.js',
                array('jquery', 'sortablejs', 'cpt-toast-js'),
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

            error_log('Admin Menu Order assets enqueued successfully');
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

        error_log('render_menu_order_content called');

        if (!is_array($menu)) {
            error_log('$menu is not an array');
        } else {
            $menu_items = array();
            foreach ($menu as $index => $menu_item) {
                if (!empty($menu_item[0])) {
                    $menu_items[] = array(
                        'index' => $index,
                        'title' => wp_strip_all_tags($menu_item[0]),
                        'id' => sanitize_title($menu_item[2]),
                        'original_id' => $menu_item[2]
                    );
                }
            }
            error_log('Menu items to render: ' . wp_json_encode($menu_items));
        }

        // Load saved menu order to look for spacers
        $saved_order = get_option('cpt_admin_menu_order', array());
        $spacers_in_saved_order = array_filter($saved_order, function ($item) {
            return strpos($item, 'cpt-spacer-') === 0;
        });

        ?>
        <div class="cpt-menu-order-container">
            <p><?php esc_html_e('Drag and drop menu items to reorder them. Changes will take effect after you click Save Order.', 'craftedpath-toolkit'); ?>
            </p>

            <div class="cpt-menu-actions">
                <button type="button" class="button button-secondary" id="auto-sort-btn">
                    <i class="iconoir-sparks" style="vertical-align: middle; margin-right: 4px;"></i>
                    <?php esc_html_e('Auto Sort with AI', 'craftedpath-toolkit'); ?>
                </button>
                <button type="button" class="button button-secondary" id="add-spacer-btn">
                    <span class="dashicons dashicons-minus"></span>
                    <?php esc_html_e('Add Spacer', 'craftedpath-toolkit'); ?>
                </button>

                <div id="menu_order_loading" class="cpt-loading" style="display: none; margin-left: 10px;">
                    <span class="spinner is-active"></span>
                    <p><?php esc_html_e('Processing with AI...', 'craftedpath-toolkit'); ?></p>
                </div>
            </div>

            <ul class="cpt-menu-order-list">
                <?php
                // We need to show both regular menu items and spacers in the correct order based on saved_order
                $menu_by_id = array();

                // Create a map of menu items by ID for quick lookup
                foreach ($menu as $menu_item) {
                    if (!empty($menu_item[0])) {
                        $menu_id = sanitize_title($menu_item[2]);
                        $menu_by_id[$menu_id] = $menu_item;
                    }
                }

                // Render items in the order specified in saved_order
                if (!empty($saved_order)) {
                    foreach ($saved_order as $item_id) {
                        if (strpos($item_id, 'cpt-spacer-') === 0) {
                            // This is a spacer
                            echo '<li class="cpt-menu-order-item cpt-menu-spacer" data-menu-id="' . esc_attr($item_id) . '" data-id="' . esc_attr($item_id) . '">';
                            echo '<span class="dashicons dashicons-menu"></span>';
                            echo '<span class="menu-title">' . esc_html__('Spacer', 'craftedpath-toolkit') . '</span>';
                            echo '<button type="button" class="cpt-remove-spacer" title="' . esc_attr__('Remove Spacer', 'craftedpath-toolkit') . '">';
                            echo '<span class="dashicons dashicons-no-alt"></span>';
                            echo '</button>';
                            echo '</li>';
                        } else if (isset($menu_by_id[$item_id])) {
                            // This is a regular menu item that exists in the current menu
                            $menu_item = $menu_by_id[$item_id];
                            echo '<li class="cpt-menu-order-item" data-menu-id="' . esc_attr($item_id) . '" data-id="' . esc_attr($item_id) . '">';
                            echo '<span class="dashicons dashicons-menu"></span>';
                            echo '<span class="menu-title">' . wp_strip_all_tags($menu_item[0]) . '</span>';
                            echo '</li>';

                            // Remove from the map so we know we've rendered it
                            unset($menu_by_id[$item_id]);
                        }
                    }
                }

                // Add any remaining menu items that weren't in the saved order
                foreach ($menu_by_id as $id => $menu_item) {
                    echo '<li class="cpt-menu-order-item" data-menu-id="' . esc_attr($id) . '" data-id="' . esc_attr($id) . '">';
                    echo '<span class="dashicons dashicons-menu"></span>';
                    echo '<span class="menu-title">' . wp_strip_all_tags($menu_item[0]) . '</span>';
                    echo '</li>';
                }
                ?>
            </ul>
        </div>
        <?php
    }

    public function save_admin_menu_order()
    {
        error_log('save_admin_menu_order AJAX handler called');

        // Check nonce
        if (!check_ajax_referer('cpt_admin_menu_order_nonce', 'nonce', false)) {
            error_log('Nonce verification failed');
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            error_log('User does not have manage_options capability');
            wp_send_json_error('You do not have permission to modify menu order.');
            return;
        }

        // Get and sanitize the menu order
        $menu_order = isset($_POST['menu_order']) ? (array) $_POST['menu_order'] : array();

        error_log('Raw menu_order from POST: ' . wp_json_encode($_POST['menu_order']));

        if (empty($menu_order)) {
            error_log('menu_order is empty');
            wp_send_json_error('No menu items to save.');
            return;
        }

        // Debug log for troubleshooting
        error_log('Saving menu order: ' . wp_json_encode($menu_order));

        $menu_order = array_map('sanitize_text_field', $menu_order);
        error_log('Sanitized menu order: ' . wp_json_encode($menu_order));

        // Save the menu order
        $result = update_option('cpt_admin_menu_order', $menu_order);
        error_log('update_option result: ' . ($result ? 'true' : 'false'));

        // Verify the option was saved
        $saved_order = get_option('cpt_admin_menu_order');
        error_log('Verification - get_option result: ' . wp_json_encode($saved_order));

        // Success response with menu data
        wp_send_json_success(array(
            'message' => __('Menu order saved successfully.', 'craftedpath-toolkit'),
            'menu_order' => $menu_order,
            'option_updated' => $result
        ));
    }

    public function apply_custom_menu_order($menu_order)
    {
        global $menu;

        error_log('apply_custom_menu_order filter called');

        $saved_order = get_option('cpt_admin_menu_order', array());
        if (empty($saved_order)) {
            error_log('No saved menu order found');
            return $menu_order;
        }

        // Add detailed menu debugging
        if (is_array($menu)) {
            error_log('Current menu structure before modification:');
            foreach ($menu as $pos => $item) {
                if (!empty($item[2])) {
                    error_log("Menu item at {$pos}: {$item[0]} ({$item[2]}) - Type: " . (isset($item[4]) ? $item[4] : 'regular'));
                }
            }
        }

        error_log('Saved menu order: ' . wp_json_encode($saved_order));
        error_log('Original menu_order: ' . wp_json_encode($menu_order));

        // Create a map of positions for each item in our saved order
        $position_map = array();
        $position = 10; // Starting position
        $position_increment = 10; // Increment each item by 10 for easy insertion

        // Clear existing separators from menu
        foreach ($menu as $index => $item) {
            if (isset($item[4]) && $item[4] === 'wp-menu-separator') {
                unset($menu[$index]);
            }
        }

        // First pass: assign positions to regular menu items
        foreach ($saved_order as $item_slug) {
            if (strpos($item_slug, 'cpt-spacer-') === 0) {
                // This is a spacer - we'll handle it in the next pass
                $position_map[$item_slug] = $position;
                $position += $position_increment;
            } else {
                // Regular item
                $position_map[$item_slug] = $position;
                $position += $position_increment;
            }
        }

        // Create a new sorted menu from scratch
        $new_menu = array();

        // Second pass: move menu items to their assigned positions
        foreach ($menu as $index => $item) {
            if (!empty($item[2])) {
                $slug = sanitize_title($item[2]);
                if (isset($position_map[$slug])) {
                    $new_pos = $position_map[$slug];
                    $new_menu[$new_pos] = $item;
                    unset($menu[$index]);
                }
            }
        }

        // Add separators where spacers were defined - use WordPress standard naming
        $separator_count = 1;  // Standard WordPress separators are named separator1, separator2, etc.
        foreach ($saved_order as $i => $item_slug) {
            if (strpos($item_slug, 'cpt-spacer-') === 0) {
                $sep_position = $position_map[$item_slug];

                // Use WordPress native separator format exactly - they use these specific values
                $separator_name = "separator" . $separator_count++;

                $new_menu[$sep_position] = array(
                    '',
                    'read',
                    $separator_name,
                    '',
                    'wp-menu-separator'
                );
                error_log("Added separator '{$separator_name}' at position {$sep_position}");
            }
        }

        // Add any remaining items
        foreach ($menu as $index => $item) {
            if (!empty($item[2])) {
                $new_menu[$position] = $item;
                $position += $position_increment;
            }
        }

        // Sort by key to ensure correct order
        ksort($new_menu);

        // Replace the global menu with our new one
        $menu = $new_menu;

        // Now use the same order for menu_order
        $new_menu_order = array();
        foreach ($new_menu as $index => $item) {
            if (!empty($item[2]) && !isset($item[4])) { // Skip separators
                $new_menu_order[] = $item[2];
            } else if (isset($item[4]) && $item[4] === 'wp-menu-separator') {
                // Add separators to the menu_order array too
                $new_menu_order[] = $item[2];
            }
        }

        error_log('Final menu structure after modification:');
        foreach ($menu as $pos => $item) {
            $type = isset($item[4]) ? $item[4] : 'regular';
            $name = isset($item[0]) ? $item[0] : 'Unnamed';
            $slug = isset($item[2]) ? $item[2] : 'No slug';
            error_log("Menu item at {$pos}: {$name} ({$slug}) - Type: {$type}");
        }

        // Force the menu to appear as we defined it by directly manipulating
        // the global variable in functions that run after apply_custom_menu_order
        add_action('admin_head', array($this, 'force_custom_menu'), 999);

        return $new_menu_order;
    }

    /**
     * Force the custom menu to be used by re-applying our menu structure
     * This ensures separators are not removed by later WordPress processes
     */
    public function force_custom_menu()
    {
        global $menu;

        // Check if we need to force separators
        $needs_separator = false;
        $saved_order = get_option('cpt_admin_menu_order', array());

        foreach ($saved_order as $item) {
            if (strpos($item, 'cpt-spacer-') === 0) {
                $needs_separator = true;
                break;
            }
        }

        if (!$needs_separator) {
            return;
        }

        error_log('Forcing custom menu with separators in admin_head');

        // Check if separators are missing
        $has_separators = false;
        foreach ($menu as $pos => $item) {
            if (isset($item[4]) && $item[4] === 'wp-menu-separator') {
                $has_separators = true;
                break;
            }
        }

        if (!$has_separators) {
            error_log('Separators are missing from menu, re-adding them');

            // Re-apply our custom menu ordering with separators
            $this->apply_custom_menu_order(array());

            // Output a JavaScript fix to ensure separators display correctly
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    console.log('Forcing separators to appear correctly');
                    // Force refresh of admin menu display
                    $('#adminmenu').css('opacity', '0.99').css('opacity', '1');
                });
            </script>
            <?php
        }
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
     * Auto-sort the menu items using AI
     * 
     * @return array Sorted menu items or error message
     */
    public function auto_sort_menu()
    {
        if (!current_user_can('manage_options')) {
            return array('error' => __('You do not have permission to perform this action.', 'craftedpath-toolkit'));
        }

        // Get the saved menu items
        $menu_order = get_option('cpt_admin_menu_order', array());
        $menu_items = $this->get_menu_items();

        // Look for potential categories by parsing titles
        $categories = $this->detect_potential_categories($menu_items);

        $this->set_processing_flag(true);

        // Build the prompt
        $prompt = $this->build_ai_prompt($menu_items, $categories);

        // Log the prompt for debugging
        error_log('AI Prompt: ' . $prompt);

        try {
            // Get API settings
            $settings = $this->get_openai_settings();
            if (empty($settings['api_key'])) {
                $this->set_processing_flag(false);
                return array('error' => __('OpenAI API Key is not configured. Please add it in the CraftedPath Settings page.', 'craftedpath-toolkit'));
            }

            // Call OpenAI API
            $response = $this->call_openai_api($prompt);

            if (is_wp_error($response)) {
                $this->set_processing_flag(false);
                return array('error' => $response->get_error_message());
            }

            // Process the AI response to get ordered menu IDs
            // This new implementation will directly use the AI's spacer suggestions
            $ordered_menu_ids = $this->process_ai_response($response, $menu_items);

            if (empty($ordered_menu_ids)) {
                $this->set_processing_flag(false);
                return array('error' => __('Failed to process AI response', 'craftedpath-toolkit'));
            }

            // Save the new order
            update_option('cpt_admin_menu_order', $ordered_menu_ids);
            $this->set_processing_flag(false);

            return array(
                'success' => true,
                'message' => __('Menu sorted successfully! Refreshing page...', 'craftedpath-toolkit'),
            );
        } catch (Exception $e) {
            $this->set_processing_flag(false);
            return array('error' => $e->getMessage());
        }
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

        // Filter out spacers before listing items
        $filtered_items = array_filter($menu_items, function ($item) {
            return strpos($item['id'], 'cpt-spacer-') !== 0;
        });

        // List the actual menu items
        $prompt .= "Here are the current WordPress admin menu items:\n\n";
        foreach ($filtered_items as $item) {
            $prompt .= "- " . $item['title'] . " (ID: " . $item['id'] . ")\n";
        }

        // Detailed instructions for output format with emphasis on using the exact IDs
        $prompt .= "\nPlease organize these items into logical groups, following WordPress conventions and best UX practices.\n";
        $prompt .= "IMPORTANT: Return a JSON object with this exact format:\n";
        $prompt .= "{\n  \"menu_order\": [\"id1\", \"id2\", ..., \"cpt-spacer-1\", \"id3\", ...],\n";
        $prompt .= "  \"explanation\": \"Brief explanation of your organization logic\"\n}\n\n";

        // Critical instructions about ID format
        $prompt .= "CRITICAL: For the menu_order array, you MUST:\n";
        $prompt .= "1. Use ONLY the exact IDs as shown in parentheses above (e.g., 'index-php', not 'index.php')\n";
        $prompt .= "2. Include ONLY string values in the array, no numbers or objects\n";
        $prompt .= "3. Include ALL the menu items listed above, don't skip any\n";
        $prompt .= "4. Don't make up new IDs that weren't in the list\n";
        $prompt .= "5. INSERT SPACERS ('cpt-spacer-1', 'cpt-spacer-2', etc.) between logical groups of menu items\n";
        $prompt .= "6. USE AT LEAST 3-4 SPACERS to separate different functional groups (content, appearance, functionality, settings)\n";
        $prompt .= "7. Don't worry about preserving existing spacers, just create a clean logical grouping\n";

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

        // Store the explanation if available for later use
        if (isset($data['explanation'])) {
            update_option('cpt_admin_menu_ai_explanation', sanitize_text_field($data['explanation']));
        }

        // If we have a menu_order key, use that
        $menu_order = array();
        if (isset($data['menu_order']) && is_array($data['menu_order'])) {
            $menu_order = $data['menu_order'];
        } else {
            // Otherwise use the first array found
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $menu_order = $value;
                    break;
                }
            }

            // Fall back to using the entire response if it's an array and we haven't found an array yet
            if (empty($menu_order)) {
                $menu_order = $data;
            }
        }

        // Process the menu order - handle both regular items and spacers
        $valid_ids = array();
        $next_spacer_id = 1;
        $spacer_map = array(); // Map AI-suggested spacer IDs to sequential ones

        foreach ($menu_order as $id) {
            if (!is_string($id)) {
                continue; // Skip non-string values
            }

            // Check if this is a spacer
            if (strpos($id, 'cpt-spacer-') === 0) {
                // Normalize spacer ID to ensure sequential numbering
                if (!isset($spacer_map[$id])) {
                    $spacer_map[$id] = 'cpt-spacer-' . $next_spacer_id++;
                }
                $valid_ids[] = $spacer_map[$id];
                continue;
            }

            // Convert periods to dashes to match DOM format (index.php -> index-php)
            $formatted_id = str_replace('.', '-', $id);
            // Convert ? to nothing (edit.php?post_type=page -> edit-phppost_typepage)
            $formatted_id = str_replace('?', '', $formatted_id);
            // Convert = to nothing
            $formatted_id = str_replace('=', '', $formatted_id);

            // Check if this ID exists in our original items
            $found = false;
            foreach ($original_items as $original) {
                if ($original['id'] === $formatted_id) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                $valid_ids[] = $formatted_id;
            } else {
                error_log('AI returned ID not found in original menu: ' . $id . ' -> ' . $formatted_id);
            }
        }

        error_log('Processed menu order with intelligent spacer placement: ' . wp_json_encode($valid_ids));
        return $valid_ids;
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

    /**
     * Get all available menu items
     * 
     * @return array Menu items with title and ID
     */
    private function get_menu_items()
    {
        global $menu;
        $menu_items = array();

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
            return $this->get_registered_menu_items();
        }

        // Convert the menu global to our format
        foreach ($menu as $item) {
            if (!empty($item[0])) {
                // Store both the sanitized and original ID for reference
                $sanitized_id = sanitize_title($item[2]);

                // Skip any separators
                if (isset($item[4]) && $item[4] === 'wp-menu-separator') {
                    continue;
                }

                $menu_items[] = array(
                    'title' => html_entity_decode(wp_strip_all_tags($item[0])),
                    'id' => $sanitized_id,
                    'original_id' => $item[2]
                );
            }
        }

        return $menu_items;
    }

    /**
     * Detect potential categories in menu items
     * 
     * @param array $menu_items Menu items
     * @return array Categories and their counts
     */
    private function detect_potential_categories($menu_items)
    {
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

        return $categories;
    }

    /**
     * Set or clear the processing flag
     * 
     * @param bool $processing Whether AI processing is in progress
     */
    private function set_processing_flag($processing = false)
    {
        update_option('cpt_admin_menu_ai_processing', $processing ? time() : false);
    }

    /**
     * Handle AJAX request to auto-sort the menu
     */
    public function ajax_auto_sort()
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

        // Call the auto_sort_menu method and get result
        $result = $this->auto_sort_menu();

        // Handle result
        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        } else {
            wp_send_json_success(array(
                'message' => $result['message'],
                'success' => true
            ));
        }
    }
}
