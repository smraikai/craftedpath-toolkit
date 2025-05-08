<?php
/**
 * Bricks Colors Admin Page for CraftedPath Toolkit
 *
 * @package CraftedPath_Toolkit
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class CPT_Bricks_Colors_Page
{
    private static $instance = null;
    const NOTICE_GROUP = 'cpt_bricks_colors_notices';

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        add_action('admin_menu', array($this, 'add_admin_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Enqueue required admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only load on our admin page
        if ('craftedpath_page_cpt-bricks-colors' !== $hook) {
            return;
        }

        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Enqueue the admin settings CSS
        wp_enqueue_style(
            'cpt-admin-settings',
            CPT_PLUGIN_URL . 'includes/admin/css/settings.css',
            array('cpt-variables'), // Depends on variables
            CPT_VERSION
        );

        // Enqueue our custom script
        wp_enqueue_script(
            'cpt-bricks-colors-admin',
            CPT_PLUGIN_URL . 'includes/admin/js/bricks-colors-admin.js',
            array('jquery', 'wp-color-picker'),
            CPT_VERSION,
            true
        );
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu_page()
    {
        add_submenu_page(
            'craftedpath-toolkit', // Parent slug (main plugin menu slug)
            __('Bricks Colors', 'craftedpath-toolkit'), // Page title
            __('Bricks Colors', 'craftedpath-toolkit'), // Menu title
            'manage_options', // Capability required
            'cpt-bricks-colors', // Menu slug
            array($this, 'render_admin_page') // Callback function
        );
    }

    /**
     * Process form submission
     */
    private function handle_form_submission()
    {
        // Verify nonce
        if (!isset($_POST['cpt_bricks_colors_nonce']) || !wp_verify_nonce($_POST['cpt_bricks_colors_nonce'], 'save_bricks_colors')) {
            add_settings_error(
                self::NOTICE_GROUP,
                'invalid_nonce',
                __('Security verification failed. Please try again.', 'craftedpath-toolkit'),
                'error'
            );
            return;
        }

        // Get the Bricks global variables
        $bricks_variables_option = get_option('bricks_global_variables');
        if (false === $bricks_variables_option || !is_array($bricks_variables_option)) {
            add_settings_error(
                self::NOTICE_GROUP,
                'no_variables',
                __('Could not retrieve Bricks global variables.', 'craftedpath-toolkit'),
                'error'
            );
            return;
        }

        // Process the form submissions
        $updated = false;
        $updated_count = 0;

        foreach ($bricks_variables_option as $key => $variable) {
            $variable_id = isset($variable['id']) ? $variable['id'] : '';

            if (empty($variable_id) || !isset($_POST['color_' . $variable_id])) {
                continue;
            }

            $new_color_value = sanitize_text_field($_POST['color_' . $variable_id]);

            // Only process if value has changed
            if ($variable['value'] === $new_color_value) {
                continue;
            }

            // Basic validation for color format (hex, rgb, rgba, etc.)
            if (preg_match('/^(#[a-f0-9]{3,8}|rgb\(.*\)|rgba\(.*\)|var\(.*\)|hsla?\(.*\))$/i', $new_color_value) || empty($new_color_value)) {
                $bricks_variables_option[$key]['value'] = $new_color_value;
                $updated = true;
                $updated_count++;
            } else {
                add_settings_error(
                    self::NOTICE_GROUP,
                    'invalid_color_' . $variable_id,
                    sprintf(
                        __('Invalid color format for %s. Please use a valid CSS color format.', 'craftedpath-toolkit'),
                        esc_html($variable['name'])
                    ),
                    'error'
                );
            }
        }

        // Save changes if any updates were made
        if ($updated) {
            update_option('bricks_global_variables', $bricks_variables_option);
            add_settings_error(
                self::NOTICE_GROUP,
                'settings_updated',
                sprintf(_n('%d variable updated successfully.', '%d variables updated successfully.', $updated_count, 'craftedpath-toolkit'), $updated_count),
                'success'
            );
        } else {
            add_settings_error(
                self::NOTICE_GROUP,
                'no_changes',
                __('No changes were made to the variables.', 'craftedpath-toolkit'),
                'info'
            );
        }
    }

    /**
     * Render admin page
     */
    public function render_admin_page()
    {
        // Handle form submission if POST request
        if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['cpt_bricks_colors_submit'])) {
            $this->handle_form_submission();
        }

        ?>
        <div class="wrap craftedpath-settings">
            <?php
            // Call the header card rendering function if it exists
            if (function_exists('cptk_render_header_card')) {
                cptk_render_header_card();
            } else {
                echo '<h1>' . esc_html__('Bricks Global Colors', 'craftedpath-toolkit') . '</h1>';
            }

            // Display admin notices/errors
            settings_errors(self::NOTICE_GROUP);
            ?>

            <div class="craftedpath-content">
                <form method="post" action="">
                    <?php wp_nonce_field('save_bricks_colors', 'cpt_bricks_colors_nonce'); ?>

                    <?php
                    // Prepare the submit button HTML for footer
                    ob_start();
                    submit_button(__('Save Color Changes', 'craftedpath-toolkit'), 'primary', 'cpt_bricks_colors_submit', false);
                    $submit_button = ob_get_clean();

                    // Use the card rendering function if it exists
                    if (function_exists('cptk_render_card')) {
                        cptk_render_card(
                            __('Bricks Color Variables', 'craftedpath-toolkit'),
                            '<i class="iconoir-color-filter" style="vertical-align: text-bottom; margin-right: 5px;"></i>',
                            array($this, 'render_card_content'),
                            $submit_button
                        );
                    } else {
                        // Fallback if the card function doesn't exist
                        ?>
                        <div class="card">
                            <h2><?php echo esc_html__('Bricks Color Variables', 'craftedpath-toolkit'); ?></h2>
                            <?php $this->render_card_content(); ?>
                            <div class="submit">
                                <?php echo $submit_button; ?>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render the content of the card, used by cptk_render_card()
     */
    public function render_card_content()
    {
        // Get the Bricks global variables
        $bricks_variables_option = get_option('bricks_global_variables');
        $categories_option = get_option('bricks_global_variables_categories');

        if (false === $bricks_variables_option) {
            echo '<p>' . esc_html__('No Bricks global variables found.', 'craftedpath-toolkit') . '</p>';
            return;
        }
        if (false === $categories_option) {
            echo '<p>' . esc_html__('No Bricks global variable categories found.', 'craftedpath-toolkit') . '</p>';
            return;
        }

        $all_variables = $bricks_variables_option;
        $all_categories = $categories_option;

        if (!is_array($all_variables) || !is_array($all_categories)) {
            echo '<p>' . esc_html__('Bricks global variables or categories are not in the expected array format.', 'craftedpath-toolkit') . '</p>';
            return;
        }

        // Find the category ID for "colors"
        $color_category_id = null;
        foreach ($all_categories as $cat) {
            if (isset($cat['name']) && strtolower($cat['name']) === 'colors') {
                $color_category_id = isset($cat['id']) ? $cat['id'] : null;
                break;
            }
        }

        // If not found, fallback to previous logic
        if (!$color_category_id) {
            $color_category_id = 'color';
        }

        // Check if in debug mode
        $debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';
        $show_all = isset($_GET['show_all']) && $_GET['show_all'] === '1';

        // Debugging section (only when debug=1)
        if ($debug_mode) {
            echo '<div class="debug-info" style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-left: 4px solid #ddd;">';
            echo '<h3>' . esc_html__('Debug Information', 'craftedpath-toolkit') . '</h3>';
            echo '<p><strong>' . esc_html__('Color category ID', 'craftedpath-toolkit') . ':</strong> ' . esc_html($color_category_id) . '</p>';
            // ... (rest of debug info as before)
            $categories = [];
            foreach ($all_variables as $var) {
                if (isset($var['category'])) {
                    $cat = $var['category'];
                    if (!isset($categories[$cat])) {
                        $categories[$cat] = 0;
                    }
                    $categories[$cat]++;
                }
            }
            echo '<p><strong>' . esc_html__('Total variables', 'craftedpath-toolkit') . ':</strong> ' . count($all_variables) . '</p>';
            echo '<p><strong>' . esc_html__('Categories found', 'craftedpath-toolkit') . ':</strong></p>';
            echo '<ul>';
            foreach ($categories as $cat => $count) {
                echo '<li><strong>' . esc_html($cat) . ':</strong> ' . $count . ' ' . esc_html__('variables', 'craftedpath-toolkit') . '</li>';
            }
            echo '</ul>';
            if (!empty($all_variables)) {
                echo '<p><strong>' . esc_html__('First variable structure', 'craftedpath-toolkit') . ':</strong></p>';
                echo '<pre style="background: #fff; padding: 10px; overflow: auto; max-height: 200px;">';
                print_r(reset($all_variables));
                echo '</pre>';
            }
            echo '</div>';
        }

        // Filter to get color variables using the detected color category ID
        $color_variables = array_filter($all_variables, function ($var) use ($color_category_id, $show_all) {
            if ($show_all)
                return true;
            return (isset($var['category']) && $var['category'] === $color_category_id);
        });

        if (empty($color_variables)) {
            echo '<div class="no-variables-found">';
            echo '<p>' . esc_html__('No color variables found in Bricks settings.', 'craftedpath-toolkit') . '</p>';
            echo '<div class="action-buttons">';
            echo '<a href="' . esc_url(add_query_arg('show_all', '1')) . '" class="button button-secondary">' . esc_html__('Show All Variables', 'craftedpath-toolkit') . '</a> ';
            if ($debug_mode) {
                echo '<a href="' . esc_url(remove_query_arg('debug')) . '" class="button button-secondary">' . esc_html__('Hide Debug Info', 'craftedpath-toolkit') . '</a>';
            } else {
                echo '<a href="' . esc_url(add_query_arg('debug', '1')) . '" class="button button-secondary">' . esc_html__('Show Debug Info', 'craftedpath-toolkit') . '</a>';
            }
            echo '</div></div>';
            return;
        }

        // Display the color variables in a grid
        ?>
        <div class="bricks-colors-controls">
            <p class="description">
                <?php
                if ($show_all) {
                    esc_html_e('Showing all Bricks variables. This is useful for debugging.', 'craftedpath-toolkit');
                } else {
                    esc_html_e('Use this interface to manage your Bricks color variables. Changes will affect your entire site where these variables are used.', 'craftedpath-toolkit');
                }
                ?>
            </p>
            <div class="action-buttons">
                <?php if ($show_all): ?>
                    <a href="<?php echo esc_url(remove_query_arg('show_all')); ?>"
                        class="button button-secondary"><?php esc_html_e('Show Only Colors', 'craftedpath-toolkit'); ?></a>
                <?php else: ?>
                    <a href="<?php echo esc_url(add_query_arg('show_all', '1')); ?>"
                        class="button button-secondary"><?php esc_html_e('Show All Variables', 'craftedpath-toolkit'); ?></a>
                <?php endif; ?>
                <?php if ($debug_mode): ?>
                    <a href="<?php echo esc_url(remove_query_arg('debug')); ?>"
                        class="button button-secondary"><?php esc_html_e('Hide Debug Info', 'craftedpath-toolkit'); ?></a>
                <?php else: ?>
                    <a href="<?php echo esc_url(add_query_arg('debug', '1')); ?>"
                        class="button button-secondary"><?php esc_html_e('Show Debug Info', 'craftedpath-toolkit'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="cpt-colors-grid">
            <?php foreach ($color_variables as $variable):
                $var_id = isset($variable['id']) ? esc_attr($variable['id']) : '';
                $var_name = isset($variable['name']) ? esc_attr($variable['name']) : '';
                $var_value = isset($variable['value']) ? esc_attr($variable['value']) : '';
                $var_category = isset($variable['category']) ? esc_attr($variable['category']) : 'unknown';
                if (empty($var_id))
                    continue;
                $is_color = preg_match('/^(#[a-fA-F0-9]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\))$/', $var_value);
                ?>
                <div class="cpt-color-item <?php echo $is_color ? 'is-color' : 'not-color'; ?>">
                    <?php if ($show_all): ?>
                        <div class="cpt-color-category"><?php echo $var_category; ?></div><?php endif; ?>
                    <div class="cpt-color-swatch" style="background-color: <?php echo $var_value; ?>"></div>
                    <label for="color_<?php echo $var_id; ?>" class="cpt-color-name"><?php echo $var_name; ?></label>
                    <div class="cpt-color-field-wrapper">
                        <input type="text" name="color_<?php echo $var_id; ?>" id="color_<?php echo $var_id; ?>"
                            value="<?php echo $var_value; ?>" class="color-picker cpt-color-input" <?php echo !$is_color ? 'data-non-color="true"' : ''; ?> />
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <style>
            .bricks-colors-controls {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .action-buttons {
                display: flex;
                gap: 10px;
            }

            .no-variables-found {
                text-align: center;
                padding: 30px;
                background: var(--gray-50);
                border-radius: 8px;
                border: 1px solid var(--gray-200);
            }

            .no-variables-found .action-buttons {
                margin-top: 15px;
                justify-content: center;
            }

            .cpt-colors-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
                margin-top: 20px;
                margin-bottom: 20px;
            }

            .cpt-color-item {
                border: 1px solid var(--gray-200);
                padding: 15px;
                background-color: var(--white);
                border-radius: 8px;
                transition: var(--transition);
                position: relative;
            }

            .cpt-color-category {
                position: absolute;
                top: 0;
                right: 0;
                background: var(--gray-100);
                font-size: 10px;
                padding: 2px 6px;
                border-radius: 0 8px 0 8px;
                color: var(--gray-600);
            }

            .cpt-color-swatch {
                width: 100%;
                height: 60px;
                border: 1px solid var(--gray-200);
                margin-bottom: 10px;
                border-radius: 4px;
            }

            .not-color .cpt-color-swatch {
                background-image: linear-gradient(45deg, #f0f0f0 25%, transparent 25%, transparent 75%, #f0f0f0 75%, #f0f0f0),
                    linear-gradient(45deg, #f0f0f0 25%, transparent 25%, transparent 75%, #f0f0f0 75%, #f0f0f0);
                background-size: 20px 20px;
                background-position: 0 0, 10px 10px;
            }

            .cpt-color-name {
                font-weight: 600;
                margin-bottom: 8px;
                display: block;
                color: var(--gray-700);
            }

            .cpt-color-field-wrapper {
                display: flex;
                align-items: center;
            }

            .cpt-color-input {
                width: 100%;
            }

            /* Color picker adjustments */
            .wp-picker-container {
                width: 100%;
            }

            .wp-picker-container .wp-color-result.button {
                min-height: 30px;
            }

            .wp-picker-container input.wp-color-picker[type="text"] {
                width: 80px !important;
            }
        </style>
        <?php
    }
}