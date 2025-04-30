<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Renders the HTML for the OpenAI API Key settings field.
 */
function aismg_settings_field_api_key_html()
{
    $api_key = get_option('aismg_openai_api_key', '');
    ?>
    <input type="password" name="aismg_openai_api_key" value="<?php echo esc_attr($api_key); ?>" size="50">
    <p class="description"><?php esc_html_e('Enter your OpenAI API key.', 'ai-sitemap-menu-generator'); ?></p>
    <?php
}

/**
 * Renders the HTML for the LLM Model selection settings field.
 */
function aismg_settings_field_llm_model_html()
{
    $current_model = get_option('aismg_llm_model', 'gpt-3.5-turbo');
    // Add more models as needed or make this dynamic
    $available_models = [
        'gpt-4o' => 'GPT-4o',
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
    ];
    ?>
    <select name="aismg_llm_model">
        <?php foreach ($available_models as $model_id => $model_name): ?>
            <option value="<?php echo esc_attr($model_id); ?>" <?php selected($current_model, $model_id); ?>>
                <?php echo esc_html($model_name); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">
        <?php esc_html_e('Select the LLM model to use for generation. Ensure your API key has access to the selected model.', 'ai-sitemap-menu-generator'); ?>
    </p>
    <?php
}

// Note: The function to render the actual settings page form (`aismg_render_settings_page`)
// will be part of `includes/admin-page.php` as it combines settings and the main UI.

?>