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
    <div class="craftedpath-image-uploader social-logo-uploader">
        <input type="hidden" name="craftedpath_seo_settings[social_share_logo_id]" value="<?php echo esc_attr($logo_id); ?>"
            class="image-id">
        <button type="button"
            class="button upload-button"><?php esc_html_e('Upload/Select Logo', 'craftedpath-toolkit'); ?></button>
        <button type="button" class="button remove-button"
            style="<?php echo $logo_id ? '' : 'display:none;'; ?>"><?php esc_html_e('Remove Logo', 'craftedpath-toolkit'); ?></button>
        <div class="image-preview"
            style="margin-top: 10px; background: #f0f0f1; padding: 10px; min-height: 50px; max-width: 200px; display: inline-block; vertical-align: top;">
            <?php if ($logo_url): ?>
                <img src="<?php echo esc_url($logo_url); ?>" style="max-width: 100%; height: auto; display: block;" />
            <?php else: ?>
                <span class="description"><?php esc_html_e('No logo selected.', 'craftedpath-toolkit'); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <p class="description">
        <?php esc_html_e('Upload or select the logo to use for social share images. If empty, the Site Logo from the Customizer will be used.', 'craftedpath-toolkit'); ?>
    </p>
    <?php
}

/**
 * Render social share settings field.
 */
function render_social_share_settings()
{
    $options = get_option('craftedpath_seo_settings', []);
    $bg_color = isset($options['social_image_bg_color']) ? $options['social_image_bg_color'] : '#ffffff';
    $bg_opacity = isset($options['social_image_bg_opacity']) ? $options['social_image_bg_opacity'] : '100';
    $bg_image_id = isset($options['social_image_bg_image_id']) ? $options['social_image_bg_image_id'] : 0;
    $text_color = isset($options['social_image_text_color']) ? $options['social_image_text_color'] : 'white';

    // Get background image URL if exists
    $bg_image_url = $bg_image_id ? wp_get_attachment_image_url($bg_image_id, 'full') : '';

    // Generate a preview image
    $preview_url = generate_social_share_image_preview();
    ?>
    <div class="social-share-settings">
        <div class="auto-generate-preview" style="margin-bottom: 20px;">
            <h3><?php esc_html_e('Preview', 'craftedpath-toolkit'); ?></h3>
            <p class="description">
                <?php esc_html_e('This is how your social share images will look:', 'craftedpath-toolkit'); ?>
            </p>
            <div class="preview-image" style="margin-top: 10px; max-width: 600px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <img src="<?php echo esc_url($preview_url); ?>" style="width: 100%; height: auto;" />
            </div>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Background Image', 'craftedpath-toolkit'); ?></th>
                <td>
                    <div class="craftedpath-image-uploader social-bg-uploader">
                        <input type="hidden" name="craftedpath_seo_settings[social_image_bg_image_id]"
                            value="<?php echo esc_attr($bg_image_id); ?>" class="image-id">
                        <button type="button" class="button upload-button">
                            <?php esc_html_e('Upload/Select Image', 'craftedpath-toolkit'); ?>
                        </button>
                        <button type="button" class="button remove-button"
                            style="<?php echo $bg_image_id ? '' : 'display:none;'; ?>">
                            <?php esc_html_e('Remove Image', 'craftedpath-toolkit'); ?>
                        </button>
                        <div class="image-preview"
                            style="margin-top: 10px; background: #f0f0f1; padding: 10px; min-height: 50px; max-width: 200px; display: inline-block; vertical-align: top;">
                            <?php if ($bg_image_url): ?>
                                <img src="<?php echo esc_url($bg_image_url); ?>"
                                    style="max-width: 100%; height: auto; display: block;" />
                            <?php else: ?>
                                <span
                                    class="description"><?php esc_html_e('No image selected.', 'craftedpath-toolkit'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Upload or select a background image for your social share images.', 'craftedpath-toolkit'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Background Color', 'craftedpath-toolkit'); ?></th>
                <td>
                    <select name="craftedpath_seo_settings[social_image_bg_color]" id="social-bg-color">
                        <option value="#ffffff" <?php selected($bg_color, '#ffffff'); ?>>
                            <?php esc_html_e('White', 'craftedpath-toolkit'); ?>
                        </option>
                        <option value="#000000" <?php selected($bg_color, '#000000'); ?>>
                            <?php esc_html_e('Black', 'craftedpath-toolkit'); ?>
                        </option>
                        <option value="custom" <?php selected($bg_color, 'custom'); ?>>
                            <?php esc_html_e('Custom', 'craftedpath-toolkit'); ?>
                        </option>
                    </select>
                    <input type="text" name="craftedpath_seo_settings[social_image_custom_bg_color]"
                        id="social-custom-bg-color"
                        value="<?php echo esc_attr(isset($options['social_image_custom_bg_color']) ? $options['social_image_custom_bg_color'] : '#f55f4b'); ?>"
                        class="color-picker" style="display: none;" />
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
            <tr>
                <th scope="row"><?php esc_html_e('Text Color', 'craftedpath-toolkit'); ?></th>
                <td>
                    <select name="craftedpath_seo_settings[social_image_text_color]">
                        <option value="white" <?php selected($text_color, 'white'); ?>>
                            <?php esc_html_e('White', 'craftedpath-toolkit'); ?>
                        </option>
                        <option value="black" <?php selected($text_color, 'black'); ?>>
                            <?php esc_html_e('Black', 'craftedpath-toolkit'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Select the text color for your social share images.', 'craftedpath-toolkit'); ?>
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

    // Sanitize social image layout settings
    $allowed_styles = ['style1', 'style2', 'style3'];
    $output['social_image_style'] = isset($input['social_image_style']) && in_array($input['social_image_style'], $allowed_styles, true) ? $input['social_image_style'] : 'style1';

    // Sanitize background color
    if (isset($input['social_image_bg_color'])) {
        $output['social_image_bg_color'] = sanitize_text_field($input['social_image_bg_color']);
    }

    // Sanitize custom background color
    if (isset($input['social_image_custom_bg_color'])) {
        $output['social_image_custom_bg_color'] = sanitize_hex_color($input['social_image_custom_bg_color']);
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

    // Sanitize text color
    $allowed_colors = ['white', 'black'];
    $output['social_image_text_color'] = isset($input['social_image_text_color']) && in_array($input['social_image_text_color'], $allowed_colors, true) ? $input['social_image_text_color'] : 'white';

    // Generate and store the social share image
    if (isset($output['social_share_logo_id']) && $output['social_share_logo_id']) {
        $logo_url = wp_get_attachment_image_url($output['social_share_logo_id'], 'full');
        if ($logo_url) {
            // Get SEO upload directory
            $seo_dir = get_seo_upload_dir();
            if (!$seo_dir) {
                error_log('Failed to get SEO upload directory');
                return $output;
            }

            // Use fixed filename
            $filename = 'social_share.jpg';
            $file_path = $seo_dir['path'] . '/' . $filename;

            // Create image using GD
            $width = 1200;
            $height = 630;
            $padding = 80;
            $image = imagecreatetruecolor($width, $height);
            if (!$image) {
                error_log('Failed to create image resource');
                return $output;
            }

            // Get colors
            $bg_color = $output['social_image_bg_color'] === 'custom' ? $output['social_image_custom_bg_color'] : $output['social_image_bg_color'];
            $text_color = get_color_value($output['social_image_text_color']);
            list($bg_r, $bg_g, $bg_b) = sscanf($bg_color, "#%02x%02x%02x");
            list($text_r, $text_g, $text_b) = sscanf($text_color, "#%02x%02x%02x");

            // Set background
            $bg = imagecolorallocate($image, $bg_r, $bg_g, $bg_b);
            if ($bg === false) {
                error_log('Failed to allocate background color');
                imagedestroy($image);
                return false;
            }
            imagefill($image, 0, 0, $bg);
            $text_color_gd = imagecolorallocate($image, $text_r, $text_g, $text_b);
            if ($text_color_gd === false) {
                error_log('Failed to allocate text color');
                imagedestroy($image);
                return false;
            }

            // Load background image if available
            if (!empty($output['social_image_bg_image_id'])) {
                $bg_image_url = wp_get_attachment_image_url($output['social_image_bg_image_id'], 'full');
                if ($bg_image_url) {
                    $bg_image_data = @file_get_contents($bg_image_url);
                    if ($bg_image_data) {
                        $bg_image = @imagecreatefromstring($bg_image_data);
                        if ($bg_image) {
                            // Calculate dimensions for cover sizing
                            $canvas_ratio = $width / $height;
                            $img_ratio = imagesx($bg_image) / imagesy($bg_image);

                            if ($img_ratio > $canvas_ratio) {
                                // Image is wider than canvas
                                $draw_height = $height;
                                $draw_width = $draw_height * $img_ratio;
                                $draw_x = ($width - $draw_width) / 2;
                                $draw_y = 0;
                            } else {
                                // Image is taller than canvas
                                $draw_width = $width;
                                $draw_height = $draw_width / $img_ratio;
                                $draw_x = 0;
                                $draw_y = ($height - $draw_height) / 2;
                            }

                            // Draw background image with cover sizing
                            imagecopyresampled($image, $bg_image, $draw_x, $draw_y, 0, 0, $draw_width, $draw_height, imagesx($bg_image), imagesy($bg_image));
                            imagedestroy($bg_image);
                        } else {
                            error_log('Failed to create background image from string');
                        }
                    } else {
                        error_log('Failed to get background image data from URL: ' . $bg_image_url);
                    }
                }
            }

            // Apply color overlay with opacity
            if (!empty($output['social_image_bg_opacity'])) {
                $opacity = intval($output['social_image_bg_opacity']);
                if ($opacity > 0) {
                    $alpha = round(($opacity / 100) * 127); // Convert 0-100 to 0-127
                    $overlay = imagecolorallocatealpha($image, $bg_r, $bg_g, $bg_b, $alpha);
                    if ($overlay === false) {
                        error_log('Failed to allocate overlay color');
                    } else {
                        imagefilledrectangle($image, 0, 0, $width, $height, $overlay);
                    }
                }
            }

            // Load logo
            $logo_data = @file_get_contents($logo_url);
            if ($logo_data) {
                $logo_img = @imagecreatefromstring($logo_data);
                if ($logo_img) {
                    switch ($output['social_image_style']) {
                        case 'style2': // Split Layout
                            $logo_area_width = $width * 0.4;
                            $text_area_x = $logo_area_width + $padding;
                            $text_area_width = $width - $text_area_x - $padding;

                            // Draw logo
                            $max_logo_w = $logo_area_width - ($padding * 1.5);
                            $max_logo_h = $height - ($padding * 2);
                            $ratio = min($max_logo_w / imagesx($logo_img), $max_logo_h / imagesy($logo_img));
                            $new_w = imagesx($logo_img) * $ratio;
                            $new_h = imagesy($logo_img) * $ratio;
                            $logo_x = ($logo_area_width - $new_w) / 2;
                            $logo_y = ($height - $new_h) / 2;
                            imagecopyresampled($image, $logo_img, $logo_x, $logo_y, 0, 0, $new_w, $new_h, imagesx($logo_img), imagesy($logo_img));

                            // Draw site name
                            $font_path = get_font_path();
                            $site_name = $output['site_name'] ?: get_bloginfo('name');
                            $font_size = 20;
                            $text_box = imagettfbbox($font_size, 0, $font_path, $site_name);
                            $site_name_width = abs($text_box[4] - $text_box[0]);
                            imagettftext($image, $font_size, 0, $width - $padding - $site_name_width, $height - $padding + 10, $text_color_gd, $font_path, $site_name);
                            break;

                        case 'style3': // Logo Overlay
                            // Draw logo
                            $max_w = $width - ($padding * 3);
                            $max_h = $height - ($padding * 3);
                            $ratio = min($max_w / imagesx($logo_img), $max_h / imagesy($logo_img));
                            $new_w = imagesx($logo_img) * $ratio;
                            $new_h = imagesy($logo_img) * $ratio;
                            $logo_x = ($width - $new_w) / 2;
                            $logo_y = ($height - $new_h) / 2;
                            imagecopyresampled($image, $logo_img, $logo_x, $logo_y, 0, 0, $new_w, $new_h, imagesx($logo_img), imagesy($logo_img));

                            // Add overlay
                            $overlay = imagecolorallocatealpha($image, $text_r, $text_g, $text_b, 90);
                            if ($overlay === false) {
                                error_log('Failed to allocate overlay color');
                            } else {
                                imagefilledrectangle($image, 0, 0, $width, $height, $overlay);
                            }
                            break;

                        default: // style1 - Logo Focus
                            // Draw logo
                            $max_logo_w = $width * 0.6;
                            $max_logo_h = $height * 0.6;
                            $ratio = min($max_logo_w / imagesx($logo_img), $max_logo_h / imagesy($logo_img));
                            $new_w = imagesx($logo_img) * $ratio;
                            $new_h = imagesy($logo_img) * $ratio;
                            $logo_x = ($width - $new_w) / 2;
                            $logo_y = ($height - $new_h) / 2 - 30;
                            imagecopyresampled($image, $logo_img, $logo_x, $logo_y, 0, 0, $new_w, $new_h, imagesx($logo_img), imagesy($logo_img));

                            // Draw site name
                            $font_path = get_font_path();
                            $site_name = $output['site_name'] ?: get_bloginfo('name');
                            $font_size = 24;
                            $text_box = imagettfbbox($font_size, 0, $font_path, $site_name);
                            $site_name_width = abs($text_box[4] - $text_box[0]);
                            imagettftext($image, $font_size, 0, ($width - $site_name_width) / 2, $logo_y + $new_h + 40, $text_color_gd, $font_path, $site_name);
                            break;
                    }
                    imagedestroy($logo_img);
                } else {
                    error_log('Failed to create logo image from string');
                }
            } else {
                error_log('Failed to get logo data from URL: ' . $logo_url);
            }

            // Save image
            if (!imagejpeg($image, $file_path, 90)) {
                error_log('Failed to save image to: ' . $file_path);
                imagedestroy($image);
                return $output;
            }
            imagedestroy($image);

            // Store the image URL in settings
            $image_url = $seo_dir['url'] . '/' . $filename;
            if (is_ssl()) {
                $image_url = str_replace('http://', 'https://', $image_url);
            }
            $output['social_share_base_image'] = $image_url;
        }
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

    // Enqueue the admin settings script - simplified
    // Update path to be relative to this file now
    $script_path_relative = 'js/admin-seo-settings.js';
    $script_url = plugin_dir_url(__FILE__) . $script_path_relative;

    wp_enqueue_script(
        'craftedpath-seo-settings-js',
        $script_url,
        array('wp-color-picker'), // Add wp-color-picker as a dependency
        defined('CPT_VERSION') ? CPT_VERSION : '1.0',
        true // Load in footer
    );

    // Pass settings to JavaScript
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
    if (!$post_id) {
        $post_id = get_queried_object_id();
    }

    // Check for post-specific social image first
    $post_image_id = get_post_meta($post_id, '_craftedpath_social_image_id', true);
    if ($post_image_id) {
        $image_url = wp_get_attachment_image_url($post_image_id, 'full');
        if ($image_url) {
            return $image_url;
        }
    }

    // Get global settings
    $options = get_option('craftedpath_seo_settings', []);

    // If we have a stored base image, use it
    if (!empty($options['social_share_base_image'])) {
        return $options['social_share_base_image'];
    }

    // If no custom image is set, use the default
    return plugin_dir_url(dirname(__FILE__, 3)) . 'assets/images/default-social-share.jpg';
}

/**
 * Generate a social share image for a post.
 * 
 * @param int $post_id Post ID.
 * @return string Generated image URL.
 */
function generate_social_share_image($post_id)
{
    // Get post data
    $post = get_post($post_id);
    $title = $post_id ? get_the_title($post_id) : get_bloginfo('name');
    $site_name = get_bloginfo('name');

    // Get settings
    $options = get_option('craftedpath_seo_settings', []);
    $style = isset($options['social_image_style']) ? $options['social_image_style'] : 'style1';
    $bg_color_key = isset($options['social_image_bg_color']) ? $options['social_image_bg_color'] : 'primary';
    $text_color_key = isset($options['social_image_text_color']) ? $options['social_image_text_color'] : 'white';

    // Get color values
    $bg_color = get_color_value($bg_color_key);
    $text_color = get_color_value($text_color_key);

    // Get font path (always Open Sans now)
    $font_path = get_font_path();

    // Get site logo - Prioritize plugin setting, fallback to theme mod
    $plugin_logo_id = isset($options['social_share_logo_id']) ? absint($options['social_share_logo_id']) : 0;
    $logo_id = $plugin_logo_id ?: get_theme_mod('custom_logo'); // Use plugin logo if set, else theme logo
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';

    $logo_img_resource = null;
    if ($logo_url) {
        // Try fetching with https first, then http if needed
        $logo_data = @file_get_contents($logo_url);
        if (!$logo_data && strpos($logo_url, 'https://') === 0) {
            $logo_data = @file_get_contents(str_replace('https://', 'http://', $logo_url));
        }
        if ($logo_data) {
            $logo_img_resource = @imagecreatefromstring($logo_data);
        }
    }

    // Generate unique filename based on post ID and settings
    $filename_base = 'social-image-' . ($post_id ?: 'home') . '-' . md5(json_encode($options));
    $filename = $filename_base . '.jpg';
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;

    // Check if image already exists and return it
    if (file_exists($file_path)) {
        $image_url = $upload_dir['url'] . '/' . $filename;
        if (is_ssl()) {
            $image_url = str_replace('http://', 'https://', $image_url);
        }
        // Destroy logo resource if created
        if ($logo_img_resource)
            imagedestroy($logo_img_resource);
        return $image_url;
    }

    // Create image using GD
    $width = 1200;
    $height = 630;
    $padding = 80;
    $image = imagecreatetruecolor($width, $height);

    // Get colors
    $bg_color = $options['social_image_bg_color'] === 'custom' ? $options['social_image_custom_bg_color'] : $options['social_image_bg_color'];
    $text_color = get_color_value($options['social_image_text_color']);
    list($bg_r, $bg_g, $bg_b) = sscanf($bg_color, "#%02x%02x%02x");
    list($text_r, $text_g, $text_b) = sscanf($text_color, "#%02x%02x%02x");

    // Set background
    $bg = imagecolorallocate($image, $bg_r, $bg_g, $bg_b);
    if ($bg === false) {
        error_log('Failed to allocate background color');
        imagedestroy($image);
        return false;
    }
    imagefill($image, 0, 0, $bg);
    $text_color_gd = imagecolorallocate($image, $text_r, $text_g, $text_b);
    if ($text_color_gd === false) {
        error_log('Failed to allocate text color');
        imagedestroy($image);
        return false;
    }

    // Load background image if available
    if (!empty($options['social_image_bg_image_id'])) {
        $bg_image_url = wp_get_attachment_image_url($options['social_image_bg_image_id'], 'full');
        if ($bg_image_url) {
            $bg_image_data = @file_get_contents($bg_image_url);
            if ($bg_image_data) {
                $bg_image = @imagecreatefromstring($bg_image_data);
                if ($bg_image) {
                    // Calculate dimensions for cover sizing
                    $canvas_ratio = $width / $height;
                    $img_ratio = imagesx($bg_image) / imagesy($bg_image);

                    if ($img_ratio > $canvas_ratio) {
                        // Image is wider than canvas
                        $draw_height = $height;
                        $draw_width = $draw_height * $img_ratio;
                        $draw_x = ($width - $draw_width) / 2;
                        $draw_y = 0;
                    } else {
                        // Image is taller than canvas
                        $draw_width = $width;
                        $draw_height = $draw_width / $img_ratio;
                        $draw_x = 0;
                        $draw_y = ($height - $draw_height) / 2;
                    }

                    // Draw background image with cover sizing
                    imagecopyresampled($image, $bg_image, $draw_x, $draw_y, 0, 0, $draw_width, $draw_height, imagesx($bg_image), imagesy($bg_image));
                    imagedestroy($bg_image);
                }
            }
        }
    }

    // Apply color overlay with opacity
    if (!empty($options['social_image_bg_opacity'])) {
        $opacity = intval($options['social_image_bg_opacity']);
        if ($opacity > 0) {
            $alpha = round(($opacity / 100) * 127); // Convert 0-100 to 0-127
            $overlay = imagecolorallocatealpha($image, $bg_r, $bg_g, $bg_b, $alpha);
            imagefilledrectangle($image, 0, 0, $width, $height, $overlay);
        }
    }

    // Load logo
    $logo_data = @file_get_contents($logo_url);
    if ($logo_data) {
        $logo_img = @imagecreatefromstring($logo_data);
        if ($logo_img) {
            switch ($options['social_image_style']) {
                case 'style2': // Split Layout
                    $logo_area_width = $width * 0.4;
                    $text_area_x = $logo_area_width + $padding;
                    $text_area_width = $width - $text_area_x - $padding;

                    // Draw logo
                    $max_logo_w = $logo_area_width - ($padding * 1.5);
                    $max_logo_h = $height - ($padding * 2);
                    $ratio = min($max_logo_w / imagesx($logo_img), $max_logo_h / imagesy($logo_img));
                    $new_w = imagesx($logo_img) * $ratio;
                    $new_h = imagesy($logo_img) * $ratio;
                    $logo_x = ($logo_area_width - $new_w) / 2;
                    $logo_y = ($height - $new_h) / 2;
                    imagecopyresampled($image, $logo_img, $logo_x, $logo_y, 0, 0, $new_w, $new_h, imagesx($logo_img), imagesy($logo_img));

                    // Draw site name
                    $font_path = get_font_path();
                    $site_name = $options['site_name'] ?: get_bloginfo('name');
                    $font_size = 20;
                    $text_box = imagettfbbox($font_size, 0, $font_path, $site_name);
                    $site_name_width = abs($text_box[4] - $text_box[0]);
                    imagettftext($image, $font_size, 0, $width - $padding - $site_name_width, $height - $padding + 10, $text_color_gd, $font_path, $site_name);
                    break;

                case 'style3': // Logo Overlay
                    // Draw logo
                    $max_w = $width - ($padding * 3);
                    $max_h = $height - ($padding * 3);
                    $ratio = min($max_w / imagesx($logo_img), $max_h / imagesy($logo_img));
                    $new_w = imagesx($logo_img) * $ratio;
                    $new_h = imagesy($logo_img) * $ratio;
                    $logo_x = ($width - $new_w) / 2;
                    $logo_y = ($height - $new_h) / 2;
                    imagecopyresampled($image, $logo_img, $logo_x, $logo_y, 0, 0, $new_w, $new_h, imagesx($logo_img), imagesy($logo_img));

                    // Add overlay
                    $overlay = imagecolorallocatealpha($image, $text_r, $text_g, $text_b, 90);
                    imagefilledrectangle($image, 0, 0, $width, $height, $overlay);
                    break;

                default: // style1 - Logo Focus
                    // Draw logo
                    $max_logo_w = $width * 0.6;
                    $max_logo_h = $height * 0.6;
                    $ratio = min($max_logo_w / imagesx($logo_img), $max_logo_h / imagesy($logo_img));
                    $new_w = imagesx($logo_img) * $ratio;
                    $new_h = imagesy($logo_img) * $ratio;
                    $logo_x = ($width - $new_w) / 2;
                    $logo_y = ($height - $new_h) / 2 - 30;
                    imagecopyresampled($image, $logo_img, $logo_x, $logo_y, 0, 0, $new_w, $new_h, imagesx($logo_img), imagesy($logo_img));

                    // Draw site name
                    $font_path = get_font_path();
                    $site_name = $options['site_name'] ?: get_bloginfo('name');
                    $font_size = 24;
                    $text_box = imagettfbbox($font_size, 0, $font_path, $site_name);
                    $site_name_width = abs($text_box[4] - $text_box[0]);
                    imagettftext($image, $font_size, 0, ($width - $site_name_width) / 2, $logo_y + $new_h + 40, $text_color_gd, $font_path, $site_name);
                    break;
            }
            imagedestroy($logo_img);
        }
    }

    // Save image
    imagejpeg($image, $file_path, 90);
    imagedestroy($image);

    // Get URL for the generated image
    $image_url = $upload_dir['url'] . '/' . $filename;

    // Ensure correct protocol
    if (is_ssl()) {
        $image_url = str_replace('http://', 'https://', $image_url);
    }

    return $image_url;
}

/**
 * Get color value from preset key.
 * 
 * @param string $key Color key (primary, black, white, alt, hover).
 * @return string Hex color value.
 */
function get_color_value($key)
{
    switch ($key) {
        case 'primary':
            return '#f55f4b'; // --primary
        case 'black':
            return '#1f2937'; // --dark
        case 'white':
            return '#ffffff'; // --white
        case 'alt':
            return '#6b7280'; // --secondary
        case 'hover':
            return '#e14b37'; // --primary-hover
        default:
            return '#f55f4b'; // Default to primary
    }
}

/**
 * Get the path to the default font file.
 * 
 * @return string Font file path.
 */
function get_font_path()
{
    return plugin_dir_path(dirname(__FILE__, 3)) . 'assets/fonts/OpenSans-Bold.ttf';
}

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
function wrap_text($fontSize, $angle, $fontFile, $text, $maxWidth)
{
    $words = explode(' ', $text);
    $lines = [];
    $currentLine = '';

    foreach ($words as $word) {
        $testLine = $currentLine . ($currentLine ? ' ' : '') . $word;
        $testBox = imagettfbbox($fontSize, $angle, $fontFile, $testLine);
        $testWidth = abs($testBox[4] - $testBox[0]);

        if ($testWidth <= $maxWidth) {
            $currentLine = $testLine;
        } else {
            if ($currentLine) {
                $lines[] = $currentLine;
            }
            $currentLine = $word;
            // Handle case where a single word is too long
            $wordBox = imagettfbbox($fontSize, $angle, $fontFile, $word);
            if (abs($wordBox[4] - $wordBox[0]) > $maxWidth) {
                // Simple truncation for very long words (can be improved)
                while (abs($wordBox[4] - $wordBox[0]) > $maxWidth) {
                    $word = mb_substr($word, 0, -1);
                    $wordBox = imagettfbbox($fontSize, $angle, $fontFile, $word . '…');
                }
                $currentLine = $word . '…';
            }
        }
    }
    $lines[] = $currentLine;

    return $lines;
}

/**
 * AJAX handler for updating social image preview.
 */
function handle_social_image_preview()
{
    check_ajax_referer('update_social_image_preview', 'nonce');

    $style = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : 'style1';
    $bg_color = isset($_POST['bg_color']) ? sanitize_text_field($_POST['bg_color']) : 'primary';
    $text_color = isset($_POST['text_color']) ? sanitize_text_field($_POST['text_color']) : 'white';
    $logo_id = isset($_POST['logo_id']) ? absint($_POST['logo_id']) : 0;

    // Update temporary settings for preview generation
    $options = get_option('craftedpath_seo_preview_settings', []); // Get temporary if exists
    if (!is_array($options))
        $options = []; // Ensure it's an array
    $options['social_image_style'] = $style;
    $options['social_image_bg_color'] = $bg_color;
    $options['social_image_text_color'] = $text_color;
    $options['social_share_logo_id'] = $logo_id;
    update_option('craftedpath_seo_preview_settings', $options, false);

    // Generate new preview using temporary settings
    $preview_url = generate_social_share_image_preview();

    // Clean up temporary option
    delete_option('craftedpath_seo_preview_settings');

    wp_send_json_success(['preview_url' => $preview_url]);
}

/**
 * Generate a preview of the social share image.
 * This function is similar to generate_social_share_image but uses
 * the current settings instead of post-specific settings.
 * 
 * @return string Image URL.
 */
function generate_social_share_image_preview()
{
    // Get current settings
    $options = get_option('craftedpath_seo_settings', []);

    // If we have a stored base image, use it
    if (!empty($options['social_share_base_image'])) {
        return $options['social_share_base_image'];
    }

    // If no custom image is set, use the default
    return plugin_dir_url(dirname(__FILE__, 3)) . 'assets/images/default-social-share.jpg';
}

// TODO: Add output_meta_tags function
// TODO: Add enqueue_admin_scripts action and create JS file
// TODO: Create Gutenberg integration 