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
    }

    public function add_admin_menu_page()
    {
        error_log('[CraftedPath Toolkit] Attempting to add Bricks Colors submenu page.'); // Temporary debug line
        add_submenu_page(
            'craftedpath-toolkit', // Parent slug (main plugin menu slug)
            __('Bricks Colors', 'craftedpath-toolkit'), // Page title
            __('Bricks Colors', 'craftedpath-toolkit'), // Menu title
            'manage_options', // Capability required
            'cpt-bricks-colors', // Menu slug
            array($this, 'render_admin_page') // Callback function to render the page
        );
    }

    private function handle_form_submission()
    {
        if (!isset($_POST['cpt_bricks_colors_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cpt_bricks_colors_nonce'])), 'cpt_bricks_colors_update')) {
            add_settings_error(self::NOTICE_GROUP, 'nonce_failure', __('Security check failed.', 'craftedpath-toolkit'), 'error');
            return;
        }

        if (!current_user_can('manage_options')) {
            add_settings_error(self::NOTICE_GROUP, 'capability_failure', __('You do not have permission to save these settings.', 'craftedpath-toolkit'), 'error');
            return;
        }

        if (!isset($_POST['bricks_color']) || !is_array($_POST['bricks_color'])) {
            // This case might not be strictly necessary if the form always submits the array, even if empty.
            // However, it's a good safeguard.
            add_settings_error(self::NOTICE_GROUP, 'data_missing', __('No color data submitted or data format is incorrect.', 'craftedpath-toolkit'), 'warning');
            return;
        }

        // Sanitize all submitted color values. Using map_deep for arrays.
        $submitted_colors = map_deep($_POST['bricks_color'], 'sanitize_text_field');

        $bricks_variables_option = get_option('bricks_global_variables');
        // Check if the option retrieval was successful and is an array.
        if (false === $bricks_variables_option || !is_array($bricks_variables_option)) {
            add_settings_error(self::NOTICE_GROUP, 'option_read_error', __('Could not read existing Bricks variables to update. The option might be missing or corrupted.', 'craftedpath-toolkit'), 'error');
            return;
        }

        $all_variables = $bricks_variables_option; // Already an array as per previous fix
        $updated_count = 0;

        foreach ($all_variables as $index => $variable_details) {
            // Ensure the variable ID exists in the current variable details and in the submitted data.
            if (isset($variable_details['id']) && array_key_exists($variable_details['id'], $submitted_colors)) {
                $new_value = trim($submitted_colors[$variable_details['id']]);

                // Basic validation for a hex color, rgb, rgba, or CSS var() - can be improved.
                // Allows standard color names too. An empty value is also considered valid (to clear a value if needed).
                if (empty($new_value) || preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\)|var\(--[a-zA-Z0-9-]+\)|[a-zA-Z]+)$/i', $new_value)) {
                    if ($all_variables[$index]['value'] !== $new_value) {
                        $all_variables[$index]['value'] = $new_value;
                        $updated_count++;
                    }
                } else {
                    // Optionally, add a specific error message for invalid color formats for a particular variable.
                    // For now, we just skip updating it.
                    add_settings_error(
                        self::NOTICE_GROUP,
                        'invalid_color_format_' . esc_attr($variable_details['id']),
                        sprintf(__('Invalid color format for %s: %s. Value not updated.', 'craftedpath-toolkit'), esc_html($variable_details['name']), esc_html($new_value)),
                        'warning'
                    );
                }
            }
        }

        if ($updated_count > 0) {
            $update_result = update_option('bricks_global_variables', $all_variables);
            if ($update_result) {
                add_settings_error(self::NOTICE_GROUP, 'settings_saved', sprintf(_n('%d Bricks color variable updated successfully.', '%d Bricks color variables updated successfully.', $updated_count, 'craftedpath-toolkit'), $updated_count), 'updated');
                // Consider adding a note about Bricks' cache if applicable/known.
            } else {
                // Check if the value was actually different before attempting to save
                $current_db_val_after_attempt = get_option('bricks_global_variables');
                if ($current_db_val_after_attempt === $all_variables) {
                    add_settings_error(self::NOTICE_GROUP, 'no_actual_changes_needed', __('No database update was performed as the submitted values were identical to stored values, or already updated.', 'craftedpath-toolkit'), 'info');
                } else {
                    add_settings_error(self::NOTICE_GROUP, 'save_failed', __('Failed to update Bricks color variables in the database.', 'craftedpath-toolkit'), 'error');
                }
            }
        } else {
            add_settings_error(self::NOTICE_GROUP, 'no_valid_changes_submitted', __('No valid color changes were submitted or values were already up to date.', 'craftedpath-toolkit'), 'info');
        }
    }

    public function render_admin_page()
    {
        // Handle form submission if POST request
        if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['cpt_bricks_colors_submit'])) {
            $this->handle_form_submission();
        }

        ?>
        <div class="wrap cpt-bricks-colors-wrap">
            <h1><?php echo esc_html__('Bricks Global Colors', 'craftedpath-toolkit'); ?></h1>

            <?php settings_errors(self::NOTICE_GROUP); // Display admin notices/errors ?>

            <form method="post" action="">
                <?php wp_nonce_field('cpt_bricks_colors_update', 'cpt_bricks_colors_nonce'); ?>
                <?php
                $bricks_variables_option = get_option('bricks_global_variables');
                if (false === $bricks_variables_option) {
                    echo '<p>' . esc_html__('Bricks global variables option (bricks_global_variables) not found.', 'craftedpath-toolkit') . '</p>';
                    echo '</form></div>'; // Close form and wrap
                    return;
                }

                $all_variables = $bricks_variables_option;

                if (false === $all_variables || !is_array($all_variables)) {
                    echo '<p>' . esc_html__('Bricks global variables data is not in the expected array format.', 'craftedpath-toolkit') . '</p>';
                    echo '</form></div>'; // Close form and wrap
                    return;
                }

                $colors_category_id = 'ydzflk';
                $color_variables = [];

                foreach ($all_variables as $variable_details) {
                    if (isset($variable_details['category'], $variable_details['name'], $variable_details['value']) && $variable_details['category'] === $colors_category_id) {
                        $css_var_name = $variable_details['name'];
                        if (substr($css_var_name, 0, 2) !== '--') {
                            $css_var_name = '--' . $css_var_name;
                        }
                        $color_variables[] = [
                            'name' => $css_var_name,
                            'value' => $variable_details['value'],
                            'id' => isset($variable_details['id']) ? $variable_details['id'] : '' // Ensure ID is present
                        ];
                    }
                }

                if (empty($color_variables)) {
                    echo '<p>' . esc_html__('No color variables found in the specified category.', 'craftedpath-toolkit') . '</p>';
                } else {
                    echo '<div class="cpt-colors-grid">';
                    foreach ($color_variables as $color_var) {
                        if (empty($color_var['id']))
                            continue; // Skip if no ID for the input field name
        
                        echo '<div class="cpt-color-item">';
                        echo '<div class="cpt-color-swatch" style="background-color:' . esc_attr($color_var['value']) . ';"></div>';
                        echo '<label for="bricks_color_' . esc_attr($color_var['id']) . '" class="cpt-color-name">' . esc_html($color_var['name']) . '</label>';
                        echo '<input type="text" id="bricks_color_' . esc_attr($color_var['id']) . '" name="bricks_color[' . esc_attr($color_var['id']) . ']" value="' . esc_attr($color_var['value']) . '" class="cpt-color-input regular-text" />';
                        echo '</div>'; // .cpt-color-item
                    }
                    echo '</div>'; // .cpt-colors-grid
                    submit_button(__('Save Color Changes', 'craftedpath-toolkit'), 'primary', 'cpt_bricks_colors_submit');
                }
                ?>
            </form>
        </div>
        <style>
            .cpt-bricks-colors-wrap .cpt-colors-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 20px;
                margin-top: 20px;
                margin-bottom: 20px;
            }

            .cpt-bricks-colors-wrap .cpt-color-item {
                border: 1px solid #ccd0d4;
                padding: 15px;
                background-color: #fff;
                border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            }

            .cpt-bricks-colors-wrap .cpt-color-swatch {
                width: 100%;
                height: 80px;
                border: 1px solid #ddd;
                margin-bottom: 10px;
                border-radius: 2px;
            }

            .cpt-bricks-colors-wrap .cpt-color-name {
                font-weight: bold;
                margin-bottom: 5px;
                display: block;
                /* For label */
                word-break: break-all;
            }

            .cpt-bricks-colors-wrap .cpt-color-input {
                width: 100%;
            }

            .cpt-bricks-colors-wrap .cpt-color-value {
                /* This class is no longer used for display, replaced by input */
                font-size: 0.9em;
                color: #555;
                word-break: break-all;
                margin-top: 5px;
            }
        </style>
        <?php
    }
}