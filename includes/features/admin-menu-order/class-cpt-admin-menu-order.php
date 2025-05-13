<?php
/**
 * Admin Menu Order Feature for CraftedPath Toolkit
 *
 * @package CraftedPath_Toolkit
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

// Ensure WordPress core is loaded
if (!function_exists('add_action')) {
    return;
}

/**
 * CPT_Admin_Menu_Order Class
 */
class CPT_Admin_Menu_Order {
    /**
     * Singleton instance
     * @var CPT_Admin_Menu_Order|null
     */
    private static $instance = null;

    /**
     * Option name for storing menu order
     */
    const OPTION_NAME = 'craftedpath_admin_menu_order';

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_menu', array($this, 'apply_menu_order'), 99); // Late priority
        add_action('wp_ajax_cpt_save_menu_order', array($this, 'ajax_save_menu_order'));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
        
        // Only load on our settings page
        if (!$screen || $screen->id !== 'craftedpath_page_cpt-admin-menu-order') {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'cpt-admin-menu-order-style',
            CPT_PLUGIN_URL . 'includes/features/admin-menu-order/css/admin-menu-order.css',
            array(),
            CPT_VERSION
        );

        // Register and enqueue SortableJS
        wp_enqueue_script(
            'sortablejs-core',
            CPT_PLUGIN_URL . 'assets/js/vendor/Sortable.min.js',
            array(),
            '1.15.2',
            true
        );

        // Enqueue our script
        wp_enqueue_script(
            'cpt-admin-menu-order-script',
            CPT_PLUGIN_URL . 'includes/features/admin-menu-order/js/admin-menu-order.js',
            array('jquery', 'sortablejs-core'),
            CPT_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'cpt-admin-menu-order-script',
            'cptMenuOrderVars',
            array(
                'nonce' => wp_create_nonce('cpt_menu_order_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php')
            )
        );
    }

    /**
     * Apply the custom menu order
     */
    public function apply_menu_order() {
        global $menu;

        if (!is_array($menu) || empty($menu)) {
            return;
        }

        $saved_order_slugs = get_option(self::OPTION_NAME);
        if (empty($saved_order_slugs) || !is_array($saved_order_slugs)) {
            return; // No custom order or invalid format
        }

        $menu_items_by_slug = [];
        $original_menu_items_map = [];

        // Map current menu items by slug and store all original items
        foreach ($menu as $priority => $item_details) {
            if (!empty($item_details[2])) { // Item has a slug
                $menu_items_by_slug[$item_details[2]] = $item_details;
            }
            $original_menu_items_map[$priority] = $item_details;
        }

        $final_menu = [];
        $used_slugs = [];
        $next_custom_priority = 5; // Starting priority for custom ordered items

        // Place items according to saved_order_slugs
        foreach ($saved_order_slugs as $slug) {
            if (isset($menu_items_by_slug[$slug])) {
                $item_to_add = $menu_items_by_slug[$slug];
                // Ensure unique priority key
                while(isset($final_menu[$next_custom_priority])) {
                    $next_custom_priority++;
                }
                $final_menu[$next_custom_priority] = $item_to_add;
                $used_slugs[$slug] = true;
                $next_custom_priority += 5; // Increment for the next custom item
            }
        }

        // Add remaining items (those not in saved_order_slugs or separators)
        $remaining_items_sorted_by_original_priority = [];
        foreach($original_menu_items_map as $priority => $item_details) {
            $slug = $item_details[2] ?? null;
            if (!($slug && isset($used_slugs[$slug]))) {
                $remaining_items_sorted_by_original_priority[$priority] = $item_details;
            }
        }
        ksort($remaining_items_sorted_by_original_priority); // Sort by original priority

        foreach ($remaining_items_sorted_by_original_priority as $original_priority => $item_details) {
            // Try to place at original priority if free, otherwise append
            if (!isset($final_menu[$original_priority])) {
                $final_menu[$original_priority] = $item_details;
            } else {
                // Slot taken, append to the end
                while(isset($final_menu[$next_custom_priority])) {
                    $next_custom_priority++;
                }
                $final_menu[$next_custom_priority] = $item_details;
                $next_custom_priority += 5; 
            }
        }
        
        ksort($final_menu); // Sort by final priorities
        $menu = $final_menu; // Replace the global menu
    }

    /**
     * Render the menu order settings page
     */
    public function render_menu_order_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap craftedpath-settings">
            <?php cptk_render_header_card(); ?>

            <div class="craftedpath-content">
                <?php
                ob_start();
                $this->render_menu_order_content();
                $content = ob_get_clean();

                ob_start();
                submit_button(__('Save Menu Order', 'craftedpath-toolkit'), 'primary', 'save_menu_order', false);
                $footer = ob_get_clean();

                cptk_render_card(
                    __('Admin Menu Order', 'craftedpath-toolkit'),
                    '<i class="iconoir-list" style="vertical-align: text-bottom; margin-right: 5px;"></i>',
                    function() use ($content) { echo $content; },
                    $footer
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the menu order interface
     */
    private function render_menu_order_content() {
        global $menu;
        
        if (!is_array($menu)) {
            echo '<p>' . esc_html__('No menu items found.', 'craftedpath-toolkit') . '</p>';
            return;
        }

        ?>
        <div class="cpt-menu-order-container">
            <p><?php esc_html_e('Drag and drop menu items to reorder them. The new order will be applied after saving.', 'craftedpath-toolkit'); ?></p>
            
            <div id="menu-order-error" class="notice notice-error" style="display: none;"></div>
            
            <ul id="sortable-menu-list" class="sortable-menu-list">
                <?php
                foreach ($menu as $item) {
                    // Skip separators and empty items
                    if (!isset($item[0]) || !isset($item[2]) || $item[4] === 'wp-menu-separator') {
                        continue;
                    }

                    $title = strip_tags($item[0]); // Clean up menu title
                    $slug = $item[2];
                    $icon = $item[6] ?? 'dashicons-admin-generic';
                    ?>
                    <li class="menu-order-item" data-slug="<?php echo esc_attr($slug); ?>">
                        <div class="menu-order-item-content">
                            <span class="menu-order-handle dashicons <?php echo esc_attr($icon); ?>"></span>
                            <span class="menu-order-title"><?php echo esc_html($title); ?></span>
                        </div>
                    </li>
                    <?php
                }
                ?>
            </ul>
        </div>
        <?php
    }

    /**
     * AJAX handler for saving menu order
     */
    public function ajax_save_menu_order() {
        // Check nonce and capabilities
        check_ajax_referer('cpt_menu_order_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'craftedpath-toolkit'));
            return;
        }

        // Get and validate the menu order data
        $menu_order = isset($_POST['menu_order']) ? json_decode(wp_unslash($_POST['menu_order']), true) : null;
        
        if (!is_array($menu_order)) {
            wp_send_json_error(__('Invalid menu order data.', 'craftedpath-toolkit'));
            return;
        }

        // Save the order
        update_option(self::OPTION_NAME, $menu_order);
        
        wp_send_json_success(array(
            'message' => __('Menu order saved successfully.', 'craftedpath-toolkit')
        ));
    }
}