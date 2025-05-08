<?php
/**
 * Bricks Colors Feature for CraftedPath Toolkit
 *
 * @package CraftedPath_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPT_Bricks_Colors
{
    private static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Menu registration now handled by the settings manager
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook)
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'craftedpath_page_cpt-bricks-colors') {
            return;
        }

        // WordPress color picker styles are a dependency
        wp_enqueue_style('wp-color-picker');

        // Enqueue our custom styles, dependent on common toolkit styles and wp-color-picker
        wp_enqueue_style(
            'cpt-bricks-colors-style',
            CPT_PLUGIN_URL . 'includes/features/bricks-colors/css/bricks-colors.css',
            array('craftedpath-toolkit-admin', 'wp-color-picker'), // Depends on common styles
            CPT_VERSION
        );

        // WordPress color picker script (jQuery is a dependency of wp-color-picker)
        wp_enqueue_script('wp-color-picker');

        // Enqueue our custom script, dependent on jQuery, wp-color-picker, and common toolkit JS
        wp_enqueue_script(
            'cpt-bricks-colors-script',
            CPT_PLUGIN_URL . 'includes/features/bricks-colors/js/bricks-colors.js',
            array('jquery', 'wp-color-picker', 'craftedpath-toolkit-admin-js'),
            CPT_VERSION,
            true
        );
    }

    public function render_bricks_colors_page()
    {
        ?>
        <div class="wrap craftedpath-settings">
            <?php cptk_render_header_card(); ?>
            <div class="craftedpath-content">
                <form method="post" action="" class="cpt-bricks-colors-form">
                    <?php
                    // Set up the submit button in the footer
                    ob_start();
                    submit_button(__('Save Color Changes', 'craftedpath-toolkit'), 'primary', 'cpt_bricks_colors_submit', false);
                    $footer_html = ob_get_clean();

                    // Render the card with form content
                    cptk_render_card(
                        __('Bricks Color Variables', 'craftedpath-toolkit'),
                        '<i class="iconoir-color-filter" style="vertical-align: text-bottom; margin-right: 5px;"></i>',
                        array($this, 'render_bricks_colors_content'),
                        $footer_html
                    );
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_bricks_colors_content()
    {
        // Handle form submission
        if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['cpt_bricks_colors_submit'])) {
            $this->handle_form_submission();
        }

        // Get Bricks global variables and categories
        $bricks_variables_option = get_option('bricks_global_variables');
        $categories_option = get_option('bricks_global_variables_categories');

        if (false === $bricks_variables_option || false === $categories_option) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Bricks global variables or categories not found.', 'craftedpath-toolkit') . '</p></div>';
            return;
        }
        $all_variables = $bricks_variables_option;
        $all_categories = $categories_option;
        if (!is_array($all_variables) || !is_array($all_categories)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Bricks global variables or categories are not in the expected array format.', 'craftedpath-toolkit') . '</p></div>';
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
        if (!$color_category_id) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('No color category found in Bricks categories.', 'craftedpath-toolkit') . '</p></div>';
            return;
        }

        // Filter to get color variables
        $color_variables = array_filter($all_variables, function ($var) use ($color_category_id) {
            return (isset($var['category']) && $var['category'] === $color_category_id);
        });

        if (empty($color_variables)) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('No color variables found in Bricks settings.', 'craftedpath-toolkit') . '</p></div>';
            return;
        }

        // Display admin notices/errors
        settings_errors('cpt_bricks_colors_notices');
        ?>
        <p class="description" style="margin-top: 0; margin-bottom: 20px;">
            <?php echo esc_html__('These colors are synchronized with Bricks global color variables. Changes made here will immediately affect your Bricks templates.', 'craftedpath-toolkit'); ?>
        </p>

        <?php wp_nonce_field('save_bricks_colors', 'cpt_bricks_colors_nonce'); ?>
        <div class="cpt-bricks-colors-container">
            <div class="cpt-bricks-colors-grid">
                <?php foreach ($color_variables as $variable):
                    $var_id = isset($variable['id']) ? esc_attr($variable['id']) : '';
                    $var_name = isset($variable['name']) ? esc_attr($variable['name']) : '';
                    $var_value = isset($variable['value']) ? esc_attr($variable['value']) : '';
                    if (empty($var_id))
                        continue;
                    ?>
                    <div class="cpt-bricks-color-item">
                        <div class="cpt-bricks-color-swatch" style="background-color: <?php echo $var_value; ?>"></div>
                        <label for="color_<?php echo $var_id; ?>" class="cpt-bricks-color-name"><?php echo $var_name; ?></label>
                        <div class="cpt-bricks-color-field-wrapper">
                            <input type="text" name="color_<?php echo $var_id; ?>" id="color_<?php echo $var_id; ?>"
                                value="<?php echo $var_value; ?>" class="cpt-bricks-color-input" />
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function handle_form_submission()
    {
        if (!isset($_POST['cpt_bricks_colors_nonce']) || !wp_verify_nonce($_POST['cpt_bricks_colors_nonce'], 'save_bricks_colors')) {
            add_settings_error('cpt_bricks_colors_notices', 'invalid_nonce', __('Security verification failed. Please try again.', 'craftedpath-toolkit'), 'error');
            return;
        }
        $bricks_variables_option = get_option('bricks_global_variables');
        $categories_option = get_option('bricks_global_variables_categories');
        if (false === $bricks_variables_option || false === $categories_option) {
            add_settings_error('cpt_bricks_colors_notices', 'no_variables', __('Could not retrieve Bricks global variables or categories.', 'craftedpath-toolkit'), 'error');
            return;
        }
        $all_variables = $bricks_variables_option;
        $all_categories = $categories_option;
        $color_category_id = null;
        foreach ($all_categories as $cat) {
            if (isset($cat['name']) && strtolower($cat['name']) === 'colors') {
                $color_category_id = isset($cat['id']) ? $cat['id'] : null;
                break;
            }
        }
        if (!$color_category_id) {
            add_settings_error('cpt_bricks_colors_notices', 'no_color_category', __('No color category found in Bricks categories.', 'craftedpath-toolkit'), 'error');
            return;
        }
        $updated = false;
        $updated_count = 0;
        foreach ($all_variables as $key => $variable) {
            if (!isset($variable['category']) || $variable['category'] !== $color_category_id) {
                continue;
            }
            $variable_id = isset($variable['id']) ? $variable['id'] : '';
            if (empty($variable_id) || !isset($_POST['color_' . $variable_id])) {
                continue;
            }
            $new_color_value = sanitize_text_field($_POST['color_' . $variable_id]);
            if ($variable['value'] === $new_color_value) {
                continue;
            }
            if (preg_match('/^(#[a-f0-9]{3,8}|rgb\(.*\)|rgba\(.*\)|var\(.*\)|hsla?\(.*\))$/i', $new_color_value) || empty($new_color_value)) {
                $all_variables[$key]['value'] = $new_color_value;
                $updated = true;
                $updated_count++;
            } else {
                add_settings_error('cpt_bricks_colors_notices', 'invalid_color_' . $variable_id, sprintf(__('Invalid color format for %s. Please use a valid CSS color format.', 'craftedpath-toolkit'), esc_html($variable['name'])), 'error');
            }
        }
        if ($updated) {
            update_option('bricks_global_variables', $all_variables);
            add_settings_error('cpt_bricks_colors_notices', 'settings_updated', sprintf(_n('%d variable updated successfully.', '%d variables updated successfully.', $updated_count, 'craftedpath-toolkit'), $updated_count), 'success');
        } else {
            add_settings_error('cpt_bricks_colors_notices', 'no_changes', __('No changes were made to the variables.', 'craftedpath-toolkit'), 'info');
        }
    }
}