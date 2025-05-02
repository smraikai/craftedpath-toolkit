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
    // Filter the document title
    add_filter('document_title_parts', __NAMESPACE__ . '\\filter_document_title_parts');

    // Add columns to post/page tables
    add_filter('manage_post_posts_columns', __NAMESPACE__ . '\\add_seo_status_column');
    add_filter('manage_page_posts_columns', __NAMESPACE__ . '\\add_seo_status_column');

    // Populate columns for post/page tables
    add_action('manage_post_posts_custom_column', __NAMESPACE__ . '\\display_seo_status_column', 10, 2);
    add_action('manage_page_posts_custom_column', __NAMESPACE__ . '\\display_seo_status_column', 10, 2);

    // Add columns to event CPT table
    add_filter('manage_event_posts_columns', __NAMESPACE__ . '\\add_seo_status_column');
    // Populate columns for event CPT table
    add_action('manage_event_posts_custom_column', __NAMESPACE__ . '\\display_seo_status_column', 10, 2);
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

        // Register the new noindex meta field
        register_post_meta(
            $post_type,
            '_craftedpath_seo_noindex', // Meta key for the noindex setting
            array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'boolean', // Store as boolean (true/false)
                'default' => false, // Default to false (allow indexing)
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
            <form action="options.php" method="post" id="craftedpath-seo-settings-form">
                <?php settings_fields('craftedpath_seo_options'); // Group name for SEO settings ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6"> <?php // Tailwind-inspired grid layout ?>

                    <?php // Column 1: General Settings ?>
                    <div class="lg:col-span-1 space-y-6"> <?php // Takes 1/3 width on large screens ?>
                        <?php
                        // General Settings Card
                        ob_start();
                        ?>
                        <table class="form-table">
                            <?php
                            // Only Site Name and Divider remain here
                            ?>
                            <tr>
                                <th scope="row">
                                    <label for="cptk-site-name"><span
                                            class="cpt-feature-accordion-title"><?php esc_html_e('Site Name', 'craftedpath-toolkit'); ?></span></label>
                                </th>
                                <td>
                                    <?php render_site_name_field(); // Keep ID for label ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="cptk-meta-divider"><span
                                            class="cpt-feature-accordion-title"><?php esc_html_e('Meta Title Divider', 'craftedpath-toolkit'); ?></span></label>
                                </th>
                                <td>
                                    <?php render_meta_divider_field(); // Keep ID for label ?>
                                </td>
                            </tr>
                        </table>
                        <?php
                        $general_content_html = ob_get_clean();

                        // Prepare footer content (Submit button)
                        ob_start();
                        submit_button(__('Save Settings', 'craftedpath-toolkit'), 'primary', 'submit-seo-main', false);
                        $footer_html = ob_get_clean();

                        cptk_render_card(
                            __('General Settings', 'craftedpath-toolkit'),
                            '<i class="iconoir-settings" style="vertical-align: text-bottom; margin-right: 5px;"></i>',
                            function () use ($general_content_html) {
                                echo $general_content_html; // Use the captured HTML
                            },
                            $footer_html // Add submit button to this card's footer
                        );
                        ?>
                    </div> <?php // End Column 1 ?>

                    <?php // Column 2: Social Share Image Layout Settings & Preview ?>
                    <div class="lg:col-span-2 space-y-6"> <?php // Takes 2/3 width on large screens ?>
                        <?php
                        // Social Share Image Card
                        ob_start();
                        // Pass nonce for AJAX preview
                        wp_nonce_field('update_social_image_preview', 'craftedpath_update_social_preview_nonce');
                        render_social_share_settings(); // Render the settings content
                        $social_settings_html = ob_get_clean();

                        cptk_render_card(
                            __('Social Share Image', 'craftedpath-toolkit'), // Simplified title
                            '<i class="iconoir-media-image" style="vertical-align: text-bottom; margin-right: 5px;"></i>',
                            function () use ($social_settings_html) {
                                echo $social_settings_html;
                            },
                            null // No separate submit button for this card
                        );
                        ?>
                    </div> <?php // End Column 2 ?>

                </div> <?php // End Grid ?>
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
    <input type="text" id="cptk-site-name" name="craftedpath_seo_settings[site_name]"
        value="<?php echo esc_attr($site_name); ?>" class="regular-text" />
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
    <select id="cptk-meta-divider" name="craftedpath_seo_settings[meta_divider]">
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
        style="display: flex; align-items: center; gap: 15px; flex-wrap: nowrap;">
        <input type="hidden" name="craftedpath_seo_settings[social_share_logo_id]" value="<?php echo esc_attr($logo_id); ?>"
            class="image-id">

        <div class="image-preview"
            style="border: 1px solid #ccd0d4; padding: 5px; background: #f0f0f1; height: 80px; width: 80px; box-sizing: border-box; display: flex; align-items: center; justify-content: center; text-align: center; flex-shrink: 0;">
            <?php if ($logo_url): ?>
                <img src="<?php echo esc_url($logo_url); ?>"
                    style="max-width: 100%; max-height: 100%; height: auto; display: block;" />
            <?php else: ?>
                <span class="dashicons dashicons-format-image" style="font-size: 30px; color: #a0a5aa;"></span>
            <?php endif; ?>
        </div>

        <div class="uploader-controls" style="display: flex; flex-direction: column; gap: 5px; align-items: flex-start;">
            <button type="button" class="button upload-button">
                <?php echo $logo_id ? esc_html__('Change Logo', 'craftedpath-toolkit') : esc_html__('Select Logo', 'craftedpath-toolkit'); ?>
            </button>
            <button type="button" class="button remove-button" style="<?php echo $logo_id ? '' : 'display:none;'; ?>">
                <?php esc_html_e('Remove Logo', 'craftedpath-toolkit'); ?>
            </button>
        </div>
    </div>
    <?php
}

/**
 * Render social share settings field - Refactored Layout.
 */
function render_social_share_settings()
{
    $options = get_option('craftedpath_seo_settings', []);
    $site_name = $options['site_name'] ?? get_bloginfo('name');
    $custom_bg_color = $options['social_image_bg_color'] ?? '#ffffff';
    $bg_opacity = isset($options['social_image_bg_opacity']) ? $options['social_image_bg_opacity'] : '100';
    $bg_image_id = isset($options['social_image_bg_image_id']) ? $options['social_image_bg_image_id'] : 0;
    $bg_image_url = $bg_image_id ? wp_get_attachment_image_url($bg_image_id, 'medium') : ''; // Use 'medium' for bg preview

    // Get preview URL
    $stored_hash = $options['social_share_base_image_hash'] ?? null;
    $preview_result = \CraftedPath\Toolkit\SEO\SocialImage\generate_image($options, null, 'preview', $stored_hash);
    $preview_url = is_string($preview_result) ? $preview_result : null;
    if (!$preview_url) {
        $preview_url = plugin_dir_url(dirname(__FILE__, 3)) . 'assets/images/default-social-share.jpg';
    }
    ?>
    <?php // Use flexbox for two-column layout (controls left, preview right) ?>
    <div class="social-share-settings-wrapper" style="display: flex; align-items: flex-start; flex-wrap: wrap; gap: 30px;">

        <?php // Left Column: Controls - 50% width ?>
        <div class="social-controls-section space-y-6" style="flex: 0 0 calc(50% - 15px); box-sizing: border-box;">

            <?php // Logo Section ?>
            <div class="logo-settings-group space-y-2" style="margin-bottom: 30px;">
                <h3><span class="cpt-feature-accordion-title"><?php esc_html_e('Logo', 'craftedpath-toolkit'); ?></span>
                </h3>
                <?php render_social_share_logo_field(); // The function itself contains the uploader ?>
            </div>

            <?php // Background Section ?>
            <div class="background-settings-group space-y-4">
                <h3><span
                        class="cpt-feature-accordion-title"><?php esc_html_e('Background', 'craftedpath-toolkit'); ?></span>
                </h3>

                <?php // Background Image Field ?>
                <div class="setting-group">
                    <div class="craftedpath-image-uploader social-bg-uploader"
                        style="display: flex; align-items: center; gap: 15px; flex-wrap: nowrap;">
                        <input type="hidden" name="craftedpath_seo_settings[social_image_bg_image_id]"
                            value="<?php echo esc_attr($bg_image_id); ?>" class="image-id">
                        <div class="image-preview"
                            style="border: 1px solid #ccd0d4; padding: 5px; background: #f0f0f1; height: 80px; width: 120px; box-sizing: border-box; display: flex; align-items: center; justify-content: center; text-align: center; flex-shrink: 0;">
                            <?php if ($bg_image_url): ?>
                                <img src="<?php echo esc_url($bg_image_url); ?>"
                                    style="max-width: 100%; max-height: 100%; height: auto; display: block;" />
                            <?php else: ?>
                                <span class="dashicons dashicons-format-image" style="font-size: 30px; color: #a0a5aa;"></span>
                            <?php endif; ?>
                        </div>
                        <div class="uploader-controls"
                            style="display: flex; flex-direction: column; gap: 5px; align-items: flex-start;">
                            <button type="button" class="button upload-button">
                                <?php echo $bg_image_id ? esc_html__('Change Image', 'craftedpath-toolkit') : esc_html__('Select Image', 'craftedpath-toolkit'); ?>
                            </button>
                            <button type="button" class="button remove-button"
                                style="<?php echo $bg_image_id ? '' : 'display:none;'; ?>">
                                <?php esc_html_e('Remove Image', 'craftedpath-toolkit'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <?php // Color Overlay Section (no card) ?>
                <div class="setting-group" style="margin-top: 20px;"> <?php // Add spacing before this section ?>
                    <label for="social-bg-color"><span
                            class="cpt-feature-accordion-title"><?php esc_html_e('Color Overlay', 'craftedpath-toolkit'); ?></span></label>
                    <?php // Use styled span in label ?>
                    <div style="margin-top: 8px;"> <?php // Add space below label ?>
                        <input type="text" name="craftedpath_seo_settings[social_image_bg_color]" id="social-bg-color"
                            value="<?php echo esc_attr($custom_bg_color); ?>" class="color-picker"
                            data-default-color="#ffffff" style="max-width: 150px;" />
                    </div>
                </div>

                <?php // Color Opacity Section (no card) ?>
                <div class="setting-group" style="margin-top: 20px;"> <?php // Add spacing before this section ?>
                    <label for="social-bg-opacity"><span
                            class="cpt-feature-accordion-title"><?php esc_html_e('Color Opacity', 'craftedpath-toolkit'); ?></span></label>
                    <?php // Use styled span in label ?>
                    <div style="margin-top: 8px; display: flex; align-items: center; gap: 10px; max-width: 300px;">
                        <?php // Add space below label, keep flex styles ?>
                        <input type="range" name="craftedpath_seo_settings[social_image_bg_opacity]" id="social-bg-opacity"
                            min="0" max="100" step="1" value="<?php echo esc_attr($bg_opacity); ?>" style="flex-grow: 1;" />
                        <span id="social-bg-opacity-value"
                            style="font-weight: 500; min-width: 40px; text-align: right;"><?php echo esc_html($bg_opacity); ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <?php // Right Column: Preview - 50% width ?>
        <div class="social-preview-section" style="flex: 0 0 calc(50% - 15px); box-sizing: border-box;">
            <h3><span class="cpt-feature-accordion-title"><?php esc_html_e('Preview', 'craftedpath-toolkit'); ?></span></h3>
            <div class="social-card-mockup"
                style="max-width: 100%; border: 1px solid #ddd; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-radius: 4px; overflow: hidden; background-color: #fff;">
                <div class="preview-image" style="line-height: 0; position: relative;">
                    <?php // Loader placeholder - centered using top/left/transform ?>
                    <div class="preview-loader"
                        style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: none; z-index: 10;">
                        <span class="spinner is-active"></span>
                    </div> <?php // Updated centering style ?>
                    <img src="<?php echo esc_url($preview_url); ?>" id="social-preview-image"
                        style="width: 100%; height: auto; display: block; border-bottom: 1px solid #ddd;"
                        alt="<?php esc_attr_e('Social Share Preview', 'craftedpath-toolkit'); ?>" />
                </div>
                <div class="mockup-text-content" style="padding: 10px 12px; background-color: #f9f9f9;">
                    <div class="mockup-url"
                        style="font-size: 11px; color: #60676e; margin-bottom: 3px; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?php echo esc_html(str_replace(['http://', 'https://'], '', get_bloginfo('url'))); ?>
                    </div>
                    <div class="mockup-title"
                        style="font-size: 14px; font-weight: 500; color: #1d2129; margin-bottom: 4px; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?php echo esc_html($site_name); // Use site name from options for preview consistency ?>
                    </div>
                    <div class="mockup-description"
                        style="font-size: 12px; color: #60676e; line-height: 1.4; max-height: 3.8em; overflow: hidden;">
                        <?php esc_html_e('Example description text showing how content might appear when shared.', 'craftedpath-toolkit'); // Shortened example text ?>
                    </div>
                </div>
            </div>
        </div>

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
    // Check if we are on a post edit screen or the settings page
    $screen = get_current_screen();
    $is_post_edit = ($hook_suffix == 'post.php' || $hook_suffix == 'post-new.php');
    $is_settings_page = ($screen && $screen->id === 'toplevel_page_craftedpath-toolkit'); // Check for main settings page ID
    $is_seo_sub_page = ($screen && strpos($screen->id, 'craftedpath-seo-settings') !== false); // Check for SEO subpage ID

    // Check if we are on the posts or pages list table
    $is_post_list = ($hook_suffix == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'post');
    $is_page_list = ($hook_suffix == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'page');
    // If it's edit.php without post_type, it's likely posts
    $is_default_post_list = ($hook_suffix == 'edit.php' && !isset($_GET['post_type']));

    // Enqueue CSS for the SEO status dots on post/page list tables
    if ($is_post_list || $is_page_list || $is_default_post_list) {
        $css = '
        /* General styles for the SEO column content */
        .column-seo_status span {
            display: inline-flex; /* Align icon and text nicely */
            align-items: center;
        }
        .column-seo_status i {
            font-size: 1.2em; /* Adjust icon size */
            margin-right: 4px;
            vertical-align: text-bottom;
            line-height: 1;
        }
        .column-seo_status .seo-status-set {
            color: #16A34A; /* Tailwind green-600 */
            font-weight: 500; /* Slightly bolder */
        }
        .column-seo_status .seo-status-unset {
            color: #999; /* Gray color for unset status */
            font-style: italic;
        }
        .column-seo_status .seo-status-noindex {
            color: #E65100; /* Orange/Warning color for noindex */
            font-size: 0.9em;
            margin-left: 5px;
            font-weight: 500;
            font-style: normal; /* Override italic if unset */
        }
        ';
        wp_add_inline_style('wp-admin', $css); // Add inline style to core admin CSS
    }

    // Only enqueue the main script for edit screens and the specific settings page
    if ($is_post_edit || $is_settings_page || $is_seo_sub_page) {
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
    $seo_noindex = get_post_meta($post_id, '_craftedpath_seo_noindex', true);

    // Determine the final title
    $final_title = $seo_title; // Use custom title if set
    if (empty($final_title)) {
        $post_title = get_the_title($post_id);
        $final_title = $post_title . $divider . $site_name;
    }

    // Start Comment
    echo "\n<!-- CraftedPath Toolkit SEO Meta Tags Start -->\n";

    // Handle Robots Meta Tag
    if ($seo_noindex) { // Value is true
        // Remove default WordPress robots tag filter before outputting ours
        remove_filter('wp_robots', 'wp_robots_max_image_preview_large');
        echo '<meta name="robots" content="noindex, follow" />' . "\n";
    } // If false, let WordPress handle the default output (usually max-image-preview:large)

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

    // End Comment
    echo "<!-- CraftedPath Toolkit SEO Meta Tags End -->\n";
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

/**
 * Filters the document title parts based on SEO settings.
 *
 * @param array $title_parts The parts of the document title.
 * @return array Modified title parts.
 */
function filter_document_title_parts($title_parts)
{
    // Only modify on singular pages/posts
    if (!is_singular()) {
        return $title_parts;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return $title_parts;
    }

    // Get SEO settings
    $options = get_option('craftedpath_seo_settings', []);
    $site_name = !empty($options['site_name']) ? $options['site_name'] : get_bloginfo('name');
    $divider = !empty($options['meta_divider']) ? ' ' . trim($options['meta_divider']) . ' ' : ' | ';

    // Get post-specific meta
    $seo_title = get_post_meta($post_id, '_craftedpath_seo_title', true);

    // Determine the final title (same logic as output_meta_tags)
    $final_title = $seo_title; // Use custom title if set
    if (empty($final_title)) {
        $post_title = get_the_title($post_id);
        // Check if it's the front page, and use Site Name only if no specific title set
        if (is_front_page() && empty($post_title)) {
            $final_title = $site_name;
        } elseif ($post_title) {
            $final_title = $post_title . $divider . $site_name;
        } else {
            // Fallback if somehow no title exists (should rarely happen)
            $final_title = $site_name;
        }
    }

    // If we have a final title determined by our logic, use it
    if (!empty($final_title)) {
        $title_parts['title'] = $final_title;

        // Remove other parts to avoid duplication (e.g., site name, tagline)
        // Check if they exist before unsetting
        if (isset($title_parts['site'])) {
            unset($title_parts['site']);
        }
        if (isset($title_parts['tagline'])) {
            unset($title_parts['tagline']);
        }
    }

    return $title_parts;
}

// === SEO Admin Columns ===

/**
 * Add the SEO Status column to the posts/pages list table.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function add_seo_status_column($columns)
{
    // Add the column before the 'date' column if possible
    $new_columns = array();
    foreach ($columns as $key => $title) {
        if ($key == 'date') {
            $new_columns['seo_status'] = __('SEO', 'craftedpath-toolkit');
        }
        $new_columns[$key] = $title;
    }
    // If 'date' column wasn't found, add it at the end
    if (!isset($new_columns['seo_status'])) {
        $new_columns['seo_status'] = __('SEO', 'craftedpath-toolkit');
    }
    return $new_columns;
}

/**
 * Display the content for the SEO Status column.
 *
 * @param string $column_name The name of the column.
 * @param int    $post_id     The ID of the current post.
 */
function display_seo_status_column($column_name, $post_id)
{
    if ($column_name == 'seo_status') {
        $seo_title = get_post_meta($post_id, '_craftedpath_seo_title', true);
        $seo_description = get_post_meta($post_id, '_craftedpath_seo_description', true);
        $noindex_status = get_post_meta($post_id, '_craftedpath_seo_noindex', true);

        // Check if both title and description are non-empty
        $is_set = !empty(trim($seo_title)) && !empty(trim($seo_description));

        $status_text = $is_set ? __('Set', 'craftedpath-toolkit') : __('Unset', 'craftedpath-toolkit');
        $status_css_class = $is_set ? 'seo-status-set' : 'seo-status-unset'; // Add class for set status too if needed later
        $icon_class = $is_set ? 'iconoir-check-circle' : 'iconoir-circle';

        $output = '<span class="' . esc_attr($status_css_class) . '">';
        $output .= '<i class="' . esc_attr($icon_class) . '"></i>'; // Iconoir class
        $output .= esc_html($status_text);
        if ($noindex_status) {
            $output .= ' <span class="seo-status-noindex">(' . esc_html__('noindex', 'craftedpath-toolkit') . ')</span>';
        }
        $output .= '</span>';

        echo $output;
    }
}

// === /SEO Admin Columns ===

// TODO: Add output_meta_tags function
// TODO: Add enqueue_admin_scripts action and create JS file
// TODO: Create Gutenberg integration 