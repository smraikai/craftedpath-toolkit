<?php
/**
 * Handles the plugin general settings page (OpenAI, etc.).
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Renders the main content for the General Settings page using shared components.
 */
function cptk_render_settings_page()
{
    if (!class_exists('CPT_Settings_Manager')) {
        echo '<div class="error"><p>' . esc_html__('CPT_Settings_Manager class not found. Cannot render settings page.', 'craftedpath-toolkit') . '</p></div>';
        return;
    }
    $settings_manager = CPT_Settings_Manager::instance();

    // Check if settings were saved
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true' && isset($_GET['page']) && $_GET['page'] === 'cptk_settings_page') {
        // Create a toast notification for settings saved
        cptk_create_toast_trigger('General settings saved successfully.', 'success');
    }

    ?>
    <div class="wrap craftedpath-settings">
        <?php cptk_render_header_card(); // Use standalone function instead of calling via instance ?>

        <!-- Content Area -->
        <div class="craftedpath-content">
            <form method="post" action="options.php">
                <?php
                // Output necessary hidden fields (nonce, action, option_page)
                settings_fields('cptk_settings');

                // Prepare footer content (Submit button)
                ob_start();
                submit_button(__('Save General Settings', 'craftedpath-toolkit'), 'primary', 'submit_general_settings', false); // Use the unique name
                $footer_html = ob_get_clean();

                // Render the card using the component, now inside the form
                cptk_render_card(
                    __('General Settings', 'craftedpath-toolkit'),
                    '<i class="iconoir-settings" style="vertical-align: text-bottom; margin-right: 5px;"></i>', // Iconoir icon
                    'cptk_render_settings_form_content', // Callback renders do_settings_sections
                    $footer_html // Pass the footer content (submit button)
                );
                ?>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Renders the actual form content (sections and fields) for the settings card.
 * This is used as the callback for $settings_manager->render_card().
 */
function cptk_render_settings_form_content()
{
    // Show validation errors/update messages
    settings_errors('cptk_settings_messages');

    // Output settings sections and fields for the 'cptk_settings_page' slug
    // This function prints the sections and fields added via add_settings_section and add_settings_field
    do_settings_sections('cptk_settings_page');
}


// --- Settings Registration (Remains the same) --- 

// Function to register settings
function cptk_register_settings()
{
    // Register the main settings group for *this* page
    register_setting(
        'cptk_settings',                // Option group name (used in settings_fields)
        'cptk_options',                 // Option name in wp_options table
        'cptk_sanitize_options'         // Sanitization callback
    );

    // Add settings section for OpenAI
    add_settings_section(
        'cptk_openai_section',
        __('OpenAI Configuration', 'craftedpath-toolkit'),
        'cptk_openai_section_callback',
        'cptk_settings_page'            // Page slug where this section appears
    );

    // Add API Key field
    add_settings_field(
        'cptk_openai_api_key',          // Field ID
        __('OpenAI API Key', 'craftedpath-toolkit'), // Label
        'cptk_openai_api_key_render',   // Render callback
        'cptk_settings_page',           // Page slug
        'cptk_openai_section'           // Section ID
    );

    // Add Model Selection field
    add_settings_field(
        'cptk_openai_model',            // Field ID
        __('OpenAI Model', 'craftedpath-toolkit'),  // Label
        'cptk_openai_model_render',     // Render callback
        'cptk_settings_page',           // Page slug
        'cptk_openai_section'           // Section ID
    );
}
add_action('admin_init', 'cptk_register_settings');

// Section callback (can be empty or add descriptive text)
function cptk_openai_section_callback()
{
    echo '<p>' . esc_html__('Configure your OpenAI API Key and select the default model for AI features.', 'craftedpath-toolkit') . '</p>';
}

// Render API Key field
function cptk_openai_api_key_render()
{
    $options = get_option('cptk_options');
    $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
    ?>
    <input type='password' name='cptk_options[openai_api_key]' value='<?php echo esc_attr($api_key); ?>'
        class='regular-text' placeholder='sk-...'>
    <p class="description"><?php esc_html_e('Enter your OpenAI API key.', 'craftedpath-toolkit'); ?></p>
    <?php
}

// Render Model Selection field
function cptk_openai_model_render()
{
    $options = get_option('cptk_options');
    // Default model is gpt-4o
    $selected_model = isset($options['openai_model']) ? $options['openai_model'] : 'gpt-4o';
    // Update the list of models to only include the specified ones
    $models = apply_filters('cptk_openai_models', [
        'gpt-4o' => 'GPT-4o',
        'gpt-4o-mini' => 'GPT-4o Mini', // Mapping for o4-mini
        'gpt-4.1' => 'GPT-4.1 (Unknown/Placeholder)', // Mapping for gpt-4.1
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo' // Mapping for o3
    ]);
    ?>
    <select name='cptk_options[openai_model]' id='cptk_openai_model'>
        <?php foreach ($models as $value => $label): ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_model, $value); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">
        <?php esc_html_e('Select the default OpenAI model to use for generative features.', 'craftedpath-toolkit'); ?>
    </p>
    <?php
}

// Sanitize options before saving
function cptk_sanitize_options($input)
{
    // error_log('Sanitizing CPTK Options: ' . print_r($input, true)); // Debugging
    $sanitized_input = [];
    $options = get_option('cptk_options'); // Get existing options for comparison if needed

    // Sanitize API Key
    if (isset($input['openai_api_key'])) {
        // Basic sanitization. More validation (like format check) could be added.
        $sanitized_input['openai_api_key'] = sanitize_text_field(trim($input['openai_api_key']));
    }

    // Sanitize Model Selection
    if (isset($input['openai_model'])) {
        // Update the list of allowed models to only include the specified ones
        $allowed_models = apply_filters('cptk_openai_models', [
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4.1' => 'GPT-4.1 (Unknown/Placeholder)',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
        ]);
        $submitted_model = sanitize_text_field($input['openai_model']);
        // Ensure the submitted model is in our allowed list
        if (array_key_exists($submitted_model, $allowed_models)) {
            $sanitized_input['openai_model'] = $submitted_model;
        } else {
            // If invalid model submitted, fall back to default (gpt-4o)
            $sanitized_input['openai_model'] = isset($options['openai_model']) && array_key_exists($options['openai_model'], $allowed_models)
                ? $options['openai_model']
                : 'gpt-4o'; // Fallback to default gpt-4o
            add_settings_error(
                'cptk_settings_messages',
                'cptk_invalid_model',
                __('Invalid model selected. Setting was not updated.', 'craftedpath-toolkit'),
                'error'
            );
        }
    }

    // Add redirect specifically for this settings page save action
    // We check if the specific submit button for this form was pressed
    if (isset($_POST['submit_general_settings'])) {
        add_filter('wp_redirect', function ($location) {
            return add_query_arg(
                array(
                    'page' => 'cptk_settings_page', // Redirect back to this settings page
                    'settings-updated' => 'true'
                ),
                admin_url('admin.php') // Ensure it's an admin URL
            );
        }, 10, 1);
    }

    return $sanitized_input;
}