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
                    '<i class="iconoir-sort" style="vertical-align: text-bottom; margin-right: 5px;"></i>',
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
}
