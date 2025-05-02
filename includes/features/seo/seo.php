<?php
/**
 * SEO Functionality for CraftedPath Toolkit.
 *
 * @package CraftedPath\Toolkit
 */

namespace CraftedPath\Toolkit\SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include the social image generator.
require_once __DIR__ . '/social-image-generator.php';

/**
 * Setup hooks for SEO functionality.
 */
function setup()
{
    add_action('init', __NAMESPACE__ . '\\register_meta_fields');
    add_action('admin_init', __NAMESPACE__ . '\\register_settings');
    add_action('wp_head', __NAMESPACE__ . '\\output_meta_tags', 1); // Use priority 1 to run early
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_scripts');
    add_action('wp_ajax_update_social_image_preview', __NAMESPACE__ . '\\handle_social_image_preview');
}

/**
 * Register meta fields for SEO title and description.
 */
function register_meta_fields()
{
    $post_types = get_post_types(array('public' => true), 'names');

    foreach ($post_types as $post_type) {
        register_post_meta(
            $post_type,
            '_craftedpath_seo_title',
            array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                }
            )
        );

        register_post_meta(
            $post_type,
            '_craftedpath_seo_description',
            array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                }
            )
        );
    }
}

/**
 * Register settings for the SEO options page.
 */
function register_settings()
{
    register_setting('craftedpath_seo_options', 'craftedpath_seo_settings', __NAMESPACE__ . '\\sanitize_settings');

    add_settings_section(
        'craftedpath_seo_general_section',
        __('General SEO Settings', 'craftedpath-toolkit'),
        null,
        'craftedpath-seo-settings'
    );

    add_settings_field(
        'site_name',
        __('Site Name', 'craftedpath-toolkit'),
        __NAMESPACE__ . '\\render_site_name_field',
        'craftedpath-seo-settings',
        'craftedpath_seo_general_section'
    );

    add_settings_field(
        'meta_divider',
        __('Meta Title Divider', 'craftedpath-toolkit'),
        __NAMESPACE__ . '\\render_meta_divider_field',
        'craftedpath-seo-settings',
        'craftedpath_seo_general_section'
    );

    add_settings_field(
        'social_share_logo',
        __('Social Share Logo', 'craftedpath-toolkit'),
        __NAMESPACE__ . '\\render_social_share_logo_field',
        'craftedpath-seo-settings',
        'craftedpath_seo_general_section'
    );

    add_settings_field(
        'social_share_settings',
        __('Social Share Image Layout', 'craftedpath-toolkit'),
        __NAMESPACE__ . '\\render_social_share_settings',
        'craftedpath-seo-settings',
        'craftedpath_seo_general_section'
    );
}

/**
 * Callback function to render the actual form content for the settings card.
 */
function render_seo_settings_form_content()
{
    settings_fields('craftedpath_seo_options'); // Group name for SEO settings
    do_settings_sections('craftedpath-seo-settings'); // Page slug used in add_settings_field
}

/**
 * Render the SEO settings page container using UI components.
 */
function render_settings_page()
{
    // Check if settings were just updated (for toast)
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        cptk_create_toast_trigger(__('SEO settings saved successfully.', 'craftedpath-toolkit'), 'success');
    }
    ?>
    <div class="wrap craftedpath-settings">
        <?php cptk_render_header_card(); ?>

        <div class="craftedpath-content">
            <form action="options.php" method="post">
                <?php
                // Prepare footer content (Submit button)
                ob_start();
                submit_button(__('Save SEO Settings', 'craftedpath-toolkit'), 'primary', 'submit-seo', false);
                $footer_html = ob_get_clean();

                // Use Iconoir icon
                $icon_html = '<i class="iconoir-input-search" style="vertical-align: text-bottom; margin-right: 5px;"></i>';

                // Render the card
                cptk_render_card(
                    __('Search Engine Optimization', 'craftedpath-toolkit'),
                    $icon_html, // Pass the Iconoir HTML
                    __NAMESPACE__ . '\\render_seo_settings_form_content',
                    $footer_html
                );
                ?>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Render the Site Name field.
 */
function render_site_name_field()
{
    $options = get_option('craftedpath_seo_settings', []);
    $site_name = $options['site_name'] ?? get_bloginfo('name');
    ?>
    <input type="text" name="craftedpath_seo_settings[site_name]" value="<?php echo esc_attr($site_name); ?>"
        class="regular-text" />
    <p class="description">
        <?php esc_html_e('Defaults to the WordPress Site Title if left empty.', 'craftedpath-toolkit'); ?>
    </p>
    <?php
}

/**
 * Render the Meta Divider field.
 */
function render_meta_divider_field()
{
    $options = get_option('craftedpath_seo_settings', []);
    $divider = $options['meta_divider'] ?? '|';
    $dividers = ['|', '-', '—', '•', '·', '»', '>'];
    ?>
    <select name="craftedpath_seo_settings[meta_divider]">
        <?php foreach ($dividers as $div): ?>
            <option value="<?php echo esc_attr($div); ?>" <?php selected($divider, $div); ?>>
                <?php echo esc_html($div); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">
        <?php esc_html_e('Choose the symbol used to separate elements in the meta title.', 'craftedpath-toolkit'); ?>
    </p>
    <?php
}

/**
 * Render the Social Share Logo field.
 */
function render_social_share_logo_field()
{
    $options = get_option('craftedpath_seo_settings', []);
    $logo_id = isset($options['social_share_logo_id']) ? absint($options['social_share_logo_id']) : 0;
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
    ?>
    <div class="craftedpath-image-uploader social-logo-uploader"
        style="display: flex; flex-wrap: wrap; align-items: flex-start; gap: 15px;">
        <input type="hidden" name="craftedpath_seo_settings[social_share_logo_id]" value="<?php echo esc_attr($logo_id); ?>"
            class="image-id">

        <div class="image-preview"
            style="border: 1px solid #ccd0d4; padding: 5px; background: #f0f0f1; min-height: 100px; width: 150px; box-sizing: border-box; display: flex; align-items: center; justify-content: center; text-align: center;">
            <?php if ($logo_url): ?>
                <img src="<?php echo esc_url($logo_url); ?>"
                    style="max-width: 100%; max-height: 150px; height: auto; display: block;" />
            <?php else: ?>
                <span class="description"
                    style="margin: 0;"><?php esc_html_e('No logo selected.', 'craftedpath-toolkit'); ?></span>
            <?php endif; ?>
        </div>

        <div class="uploader-buttons" style="display: flex; flex-direction: column; gap: 5px;">
            <button type="button" class="button upload-button">
                <?php echo $logo_id ? esc_html__('Change Logo', 'craftedpath-toolkit') : esc_html__('Upload/Select Logo', 'craftedpath-toolkit'); ?>
            </button>
            <button type="button" class="button remove-button" style="<?php echo $logo_id ? '' : 'display:none;'; ?>">
                <?php esc_html_e('Remove Logo', 'craftedpath-toolkit'); ?>
            </button>
        </div>
    </div>
    <p class="description" style="margin-top: 10px;">
        <?php esc_html_e('Upload or select the logo to use for social share images. If empty, the Site Logo from the Customizer will be used (if available). Recommended size: At least 300x300 pixels.', 'craftedpath-toolkit'); ?>
    </p>
    <?php
}

/**
 * Render social share settings field.
 */
function render_social_share_settings()
{
    $options = get_option('craftedpath_seo_settings', []);
    $site_name = $options['site_name'] ?? get_bloginfo('name');
    $custom_bg_color = $options['social_image_bg_color'] ?? '#ffffff';
    $bg_opacity = isset($options['social_image_bg_opacity']) ? $options['social_image_bg_opacity'] : '100';
    $bg_image_id = isset($options['social_image_bg_image_id']) ? $options['social_image_bg_image_id'] : 0;

    // Get background image URL if exists
    $bg_image_url = $bg_image_id ? wp_get_attachment_image_url($bg_image_id, 'full') : '';

    // Generate a preview image URL using the current saved settings
    $stored_hash = $options['social_share_base_image_hash'] ?? null;
    $preview_result = \CraftedPath\Toolkit\SEO\SocialImage\generate_image($options, null, 'preview', $stored_hash);
    $preview_url = is_string($preview_result) ? $preview_result : null;

    // Fallback if generation fails or returns unexpected result
    if (!$preview_url) {
        $preview_url = plugin_dir_url(dirname(__FILE__, 3)) . 'assets/images/default-social-share.jpg';
    }
    ?>
    <div class="social-share-settings">
        <?php // Add nonce field for AJAX preview updates ?>
        <?php wp_nonce_field('update_social_image_preview', 'craftedpath_update_social_preview_nonce'); ?>

        <div class="auto-generate-preview" style="margin-bottom: 25px;">
            <h3 style="margin-bottom: 10px; margin-top: 0px;"><?php esc_html_e('Preview', 'craftedpath-toolkit'); ?></h3>
            <div class="social-card-mockup"
                style="max-width: 520px; border: 1px solid #ddd; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-radius: 4px; overflow: hidden;">
                <div class="preview-image" style="line-height: 0;">
                    <img src="<?php echo esc_url($preview_url); ?>"
                        style="width: 100%; height: auto; display: block; border-bottom: 1px solid #ddd;" />
                </div>
                <div class="mockup-text-content" style="padding: 10px 12px; background-color: #f9f9f9;">
                    <div class="mockup-url"
                        style="font-size: 11px; color: #60676e; margin-bottom: 3px; text-transform: uppercase;">
                        <?php echo esc_html(preg_replace('(^https?://)', '', get_bloginfo('url'))); ?>
                    </div>
                    <div class="mockup-title"
                        style="font-size: 14px; font-weight: 500; color: #1d2129; margin-bottom: 4px; line-height: 1.3;">
                        <?php echo esc_html($site_name); ?>
                    </div>
                    <div class="mockup-description" style="font-size: 12px; color: #60676e; line-height: 1.4;">
                        <?php esc_html_e('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.', 'craftedpath-toolkit'); ?>
                    </div>
                </div>
            </div>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Background Image', 'craftedpath-toolkit'); ?></th>
                <td>
                    <div class="craftedpath-image-uploader social-bg-uploader"
                        style="display: flex; flex-wrap: wrap; align-items: flex-start; gap: 15px;">
                        <input type="hidden" name="craftedpath_seo_settings[social_image_bg_image_id]"
                            value="<?php echo esc_attr($bg_image_id); ?>" class="image-id">

                        <div class="image-preview"
                            style="border: 1px solid #ccd0d4; padding: 5px; background: #f0f0f1; min-height: 100px; width: 150px; box-sizing: border-box; display: flex; align-items: center; justify-content: center; text-align: center;">
                            <?php if ($bg_image_url): ?>
                                <img src="<?php echo esc_url($bg_image_url); ?>"
                                    style="max-width: 100%; height: auto; display: block;" />
                            <?php else: ?>
                                <span class="description"
                                    style="margin: 0;"><?php esc_html_e('No image selected.', 'craftedpath-toolkit'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="uploader-buttons" style="display: flex; flex-direction: column; gap: 5px;">
                            <button type="button" class="button upload-button">
                                <?php echo $bg_image_id ? esc_html__('Change Image', 'craftedpath-toolkit') : esc_html__('Upload/Select Image', 'craftedpath-toolkit'); ?>
                            </button>
                            <button type="button" class="button remove-button"
                                style="<?php echo $bg_image_id ? '' : 'display:none;'; ?>">
                                <?php esc_html_e('Remove Image', 'craftedpath-toolkit'); ?>
                            </button>
                        </div>
                    </div>
                    <p class="description" style="margin-top: 10px;">
                        <?php esc_html_e('Upload or select a background image for your social share images.', 'craftedpath-toolkit'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Background Color', 'craftedpath-toolkit'); ?></th>
                <td>
                    <input type="text" name="craftedpath_seo_settings[social_image_bg_color]" id="social-bg-color"
                        value="<?php echo esc_attr($custom_bg_color); ?>" class="color-picker"
                        data-default-color="#ffffff" />
                    <p class="description">
                        <?php esc_html_e('Select the background color overlay for your social share images.', 'craftedpath-toolkit'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Color Opacity', 'craftedpath-toolkit'); ?></th>
                <td>
                    <input type="range" name="craftedpath_seo_settings[social_image_bg_opacity]" id="social-bg-opacity"
                        min="0" max="100" step="1" value="<?php echo esc_attr($bg_opacity); ?>" />
                    <span id="social-bg-opacity-value"><?php echo esc_html($bg_opacity); ?>%</span>
                    <p class="description">
                        <?php esc_html_e('Adjust the opacity of the background color overlay.', 'craftedpath-toolkit'); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

/**
 * Get the upload directory for SEO images.
 * 
 * @return array Array with 'path' and 'url' keys.
 */
/* // MOVED to social-image-generator.php
function get_seo_upload_dir()
{
    $upload_dir = wp_upload_dir();
    $seo_dir = 'seo';

    // Create the directory if it doesn't exist
    $seo_path = $upload_dir['basedir'] . '/' . $seo_dir;
    if (!file_exists($seo_path)) {
        if (!wp_mkdir_p($seo_path)) {
            error_log('Failed to create SEO upload directory: ' . $seo_path);
            return false;
        }
    }

    // Check if directory is writable
    if (!is_writable($seo_path)) {
        error_log('SEO upload directory is not writable: ' . $seo_path);
        return false;
    }

    return array(
        'path' => $seo_path,
        'url' => $upload_dir['baseurl'] . '/' . $seo_dir
    );
}
*/

/**
 * Sanitize and save the SEO settings.
 *
 * @param array $input The input array.
 * @return array Sanitized array.
 */
function sanitize_settings($input)
{
    $output = get_option('craftedpath_seo_settings', []); // Get existing options to merge

    if (isset($input['site_name'])) {
        $output['site_name'] = sanitize_text_field($input['site_name']);
    }
    if (isset($input['meta_divider'])) {
        $allowed_dividers = ['|', '-', '—', '•', '·', '»', '>'];
        $output['meta_divider'] = in_array($input['meta_divider'], $allowed_dividers, true) ? $input['meta_divider'] : '|';
    }

    // Sanitize social share logo
    if (isset($input['social_share_logo_id'])) {
        $output['social_share_logo_id'] = absint($input['social_share_logo_id']);
    }

    // Sanitize background color (now directly from color picker)
    if (isset($input['social_image_bg_color'])) {
        $output['social_image_bg_color'] = sanitize_hex_color($input['social_image_bg_color']);
    }

    // Sanitize background opacity
    if (isset($input['social_image_bg_opacity'])) {
        $opacity = intval($input['social_image_bg_opacity']);
        $output['social_image_bg_opacity'] = max(0, min(100, $opacity)); // Ensure between 0-100
    }

    // Sanitize background image
    if (isset($input['social_image_bg_image_id'])) {
        $output['social_image_bg_image_id'] = absint($input['social_image_bg_image_id']);
    }

    // Generate and store the social share image URL and hash
    $stored_hash = $output['social_share_base_image_hash'] ?? null;
    $generation_result = \CraftedPath\Toolkit\SEO\SocialImage\generate_image($output, null, 'base', $stored_hash);

    if (is_array($generation_result) && isset($generation_result['url']) && isset($generation_result['hash'])) {
        $output['social_share_base_image'] = $generation_result['url'];
        $output['social_share_base_image_hash'] = $generation_result['hash'];
    } elseif ($generation_result === false) {
        // Handle generation failure - log an error, maybe remove the setting?
        unset($output['social_share_base_image']);
        unset($output['social_share_base_image_hash']);
        error_log("CraftedPath SEO: Failed to generate base social share image during settings save.");
    } else {
        // If it returned something else (e.g., just URL, meaning cache hit but structure mismatch? unlikely)
        // Or if hash comparison failed internally but file exists?
        // For safety, let's just remove the stored values if the result isn't the expected array.
        unset($output['social_share_base_image']);
        unset($output['social_share_base_image_hash']);
        error_log("CraftedPath SEO: Unexpected result from generate_image during settings save.");
    }

    return $output;
}

/**
 * Enqueue scripts for the media uploader on the settings page.
 */
function enqueue_admin_scripts($hook_suffix)
{
    // Define the target hook for our settings page - Use the correct one found via logging
    $target_hook = 'craftedpath_page_craftedpath-seo-settings';

    // Only load on our settings page.
    if ($target_hook !== $hook_suffix) {
        return;
    }

    // Enqueue WP media assets (still needed for wp.media object)
    wp_enqueue_media();

    // Enqueue WordPress color picker
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    // Enqueue the main admin settings script (even if empty for now)
    $main_script_path_relative = 'js/admin-seo-settings.js';
    $main_script_url = plugin_dir_url(__FILE__) . $main_script_path_relative;
    wp_enqueue_script(
        'craftedpath-seo-settings-js',
        $main_script_url,
        array('jquery'), // Dependency on jQuery only for the main script
        defined('CPT_VERSION') ? CPT_VERSION : '1.0',
        true // Load in footer
    );

    // Enqueue the social share settings script
    $social_script_path_relative = 'js/admin-social-settings.js';
    $social_script_url = plugin_dir_url(__FILE__) . $social_script_path_relative;
    wp_enqueue_script(
        'craftedpath-seo-social-settings-js', // New handle
        $social_script_url,
        array('jquery', 'wp-color-picker', 'wp-mediaelement'), // Add dependencies 
        defined('CPT_VERSION') ? CPT_VERSION : '1.0',
        true // Load in footer
    );

    // Pass settings to JavaScript (can be accessed by both scripts if needed)
    // Attaching to the main script handle for consistency
    wp_localize_script('craftedpath-seo-settings-js', 'cptkSettings', array(
        'tagline' => get_bloginfo('description')
    ));
}

/**
 * Output SEO meta tags in the <head>.
 */
function output_meta_tags()
{
    // Only output on singular pages/posts
    if (!is_singular()) {
        return;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return;
    }

    // Get SEO settings
    $options = get_option('craftedpath_seo_settings', []);
    $site_name = !empty($options['site_name']) ? $options['site_name'] : get_bloginfo('name');
    $divider = !empty($options['meta_divider']) ? ' ' . trim($options['meta_divider']) . ' ' : ' | ';

    // Get post-specific meta
    $seo_title = get_post_meta($post_id, '_craftedpath_seo_title', true);
    $seo_description = get_post_meta($post_id, '_craftedpath_seo_description', true);

    // Determine the final title
    $final_title = $seo_title; // Use custom title if set
    if (empty($final_title)) {
        $post_title = get_the_title($post_id);
        $final_title = $post_title . $divider . $site_name;
    }

    // Output Meta Description
    if (!empty($seo_description)) {
        echo '<meta name="description" content="' . esc_attr($seo_description) . '" />' . "\n";
    }

    // Get social image URL
    $og_image_url = get_social_share_image_url($post_id);

    // --- Open Graph Tags ---
    $og_title = apply_filters('craftedpath_og_title', $final_title, $post_id);
    $og_description = apply_filters('craftedpath_og_description', $seo_description, $post_id);
    $og_url = apply_filters('craftedpath_og_url', get_permalink($post_id), $post_id);
    $og_type = is_front_page() ? 'website' : 'article';
    $og_type = apply_filters('craftedpath_og_type', $og_type, $post_id);

    echo '<meta property="og:title" content="' . esc_attr($og_title) . '" />' . "\n";
    echo '<meta property="og:type" content="' . esc_attr($og_type) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url($og_url) . '" />' . "\n";
    if (!empty($og_description)) {
        echo '<meta property="og:description" content="' . esc_attr($og_description) . '" />' . "\n";
    }
    if (!empty($og_image_url)) {
        echo '<meta property="og:image" content="' . esc_url($og_image_url) . '" />' . "\n";
        // Add image dimensions if we can get them
        list($width, $height) = @getimagesize($og_image_url);
        if ($width && $height) {
            echo '<meta property="og:image:width" content="' . esc_attr($width) . '" />' . "\n";
            echo '<meta property="og:image:height" content="' . esc_attr($height) . '" />' . "\n";
        }
    }
    echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";

    // --- Twitter Card Tags ---
    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '" />' . "\n";
    if (!empty($og_description)) {
        echo '<meta name="twitter:description" content="' . esc_attr($og_description) . '" />' . "\n";
    }
    if (!empty($og_image_url)) {
        echo '<meta name="twitter:image" content="' . esc_url($og_image_url) . '" />' . "\n";
    }
}

/**
 * Get the social share image URL for a post.
 * 
 * @param int $post_id Post ID.
 * @return string Image URL.
 */
function get_social_share_image_url($post_id = null)
{
    // Note: Per-post social images are not yet implemented in the generator.
    // This function currently only returns the globally generated base image.
    if (!$post_id) {
        $post_id = get_queried_object_id();
    }

    // Check for post-specific social image first (FUTURE FEATURE)
    /*
    $post_image_id = get_post_meta($post_id, '_craftedpath_social_image_id', true);
    if ($post_image_id) {
        $image_url = wp_get_attachment_image_url($post_image_id, 'full');
        if ($image_url) {
            return $image_url;
        }
    }
    */

    // Get global settings
    $options = get_option('craftedpath_seo_settings', []);

    // If we have a stored base image URL generated during save, use it
    if (!empty($options['social_share_base_image'])) {
        return $options['social_share_base_image'];
    }

    // If no base image URL is stored (e.g., initial save failed or before first save),
    // try generating it on the fly (less ideal)
    // $generated_url = \CraftedPath\Toolkit\SEO\SocialImage\generate_image($options, null, 'base');
    // if ($generated_url) {
    //    return $generated_url;
    // }

    // Absolute fallback to default image
    return plugin_dir_url(dirname(__FILE__, 3)) . 'assets/images/default-social-share.jpg';
}

/**
 * Generate a social share image for a post.
 * 
 * @param int $post_id Post ID.
 * @return string Generated image URL.
 */
/* // MOVED and refactored into social-image-generator.php
function generate_social_share_image($post_id)
{
    // ... function content removed ...
}
*/

/**
 * Get color value from preset key.
 * 
 * @param string $key Color key (primary, black, white, alt, hover).
 * @return string Hex color value.
 */
/* // MOVED to social-image-generator.php
function get_color_value($key)
{
    // ... function content removed ...
}
*/

/**
 * Get the path to the default font file.
 * 
 * @return string Font file path.
 */
/* // MOVED to social-image-generator.php
function get_font_path()
{
    // ... function content removed ...
}
*/

/**
 * Wrap text to fit within a given width.
 * 
 * @param float $fontSize The font size.
 * @param float $angle The angle.
 * @param string $fontFile The font file path.
 * @param string $text The text string.
 * @param float $maxWidth The maximum width.
 * @return array Lines of text.
 */
/* // MOVED to social-image-generator.php
function wrap_text($fontSize, $angle, $fontFile, $text, $maxWidth)
{
    // ... function content removed ...
}
*/

/**
 * AJAX handler for updating social image preview.
 */
function handle_social_image_preview()
{
    check_ajax_referer('update_social_image_preview', 'nonce');

    // Sanitize incoming post data
    $settings = [];
    $settings['social_image_bg_color'] = isset($_POST['bg_color']) ? sanitize_hex_color($_POST['bg_color']) : '#ffffff';
    $settings['social_image_bg_opacity'] = isset($_POST['bg_opacity']) ? absint($_POST['bg_opacity']) : 100;
    $settings['social_share_logo_id'] = isset($_POST['logo_id']) ? absint($_POST['logo_id']) : 0;
    $settings['social_image_bg_image_id'] = isset($_POST['bg_image_id']) ? absint($_POST['bg_image_id']) : 0;
    $settings['site_name'] = isset($_POST['site_name']) ? sanitize_text_field($_POST['site_name']) : get_bloginfo('name');

    // Generate new preview using the submitted settings
    $preview_url = \CraftedPath\Toolkit\SEO\SocialImage\generate_image($settings, null, 'preview');

    // Fallback if generation fails
    if (!$preview_url) {
        error_log("CraftedPath SEO AJAX: Failed to generate preview image.");
        $preview_url = plugin_dir_url(dirname(__FILE__, 3)) . 'assets/images/default-social-share.jpg';
        wp_send_json_error(['message' => 'Failed to generate preview.', 'fallback_url' => $preview_url]);
    }

    wp_send_json_success(['preview_url' => $preview_url]);
}

/**
 * Generate a preview of the social share image.
 * This function now directly calls the main generator with 'preview' suffix.
 * 
 * @return string Image URL.
 */
function generate_social_share_image_preview()
{
    // Get current settings for the preview base
    $options = get_option('craftedpath_seo_settings', []);

    // Generate preview image using current settings
    $preview_url = \CraftedPath\Toolkit\SEO\SocialImage\generate_image($options, null, 'preview');

    // Fallback if generation fails
    if (!$preview_url) {
        $preview_url = plugin_dir_url(dirname(__FILE__, 3)) . 'assets/images/default-social-share.jpg';
    }

    return $preview_url;
}

// TODO: Add output_meta_tags function
// TODO: Add enqueue_admin_scripts action and create JS file
// TODO: Create Gutenberg integration 