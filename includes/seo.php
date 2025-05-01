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
    add_action('admin_menu', __NAMESPACE__ . '\\add_settings_page');
    add_action('admin_init', __NAMESPACE__ . '\\register_settings');

    // Add more hooks here later for outputting meta tags etc.
    add_action('wp_head', __NAMESPACE__ . '\\output_meta_tags', 1); // Use priority 1 to run early
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
 * Add the SEO settings page under the main CraftedPath admin menu.
 */
function add_settings_page()
{
    // Add as a submenu under the main 'craftedpath-toolkit' menu
    add_submenu_page(
        'craftedpath-toolkit',                  // Parent slug
        __('SEO Settings', 'craftedpath-toolkit'), // Page title
        __('SEO Settings', 'craftedpath-toolkit'), // Menu title
        'manage_options',                         // Capability required
        'craftedpath-seo-settings',             // Menu slug (keep consistent)
        __NAMESPACE__ . '\\render_settings_page'    // Callback function
    );
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
        null, // No description callback needed for this section
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
        'social_share_image',
        __('Default Social Share Image', 'craftedpath-toolkit'),
        __NAMESPACE__ . '\\render_social_image_field',
        'craftedpath-seo-settings',
        'craftedpath_seo_general_section'
    );
}

/**
 * Render the SEO settings page container.
 */
function render_settings_page()
{
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('craftedpath_seo_options');
            do_settings_sections('craftedpath-seo-settings');
            submit_button(__('Save Settings', 'craftedpath-toolkit'));
            ?>
        </form>
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
 * Render the Social Share Image field.
 * Needs WP Media Uploader JS.
 */
function render_social_image_field()
{
    // We'll add the media uploader logic later with JavaScript.
    $options = get_option('craftedpath_seo_settings', []);
    $image_id = $options['social_share_image_id'] ?? 0;
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
    ?>
    <div class="craftedpath-image-uploader">
        <input type="hidden" name="craftedpath_seo_settings[social_share_image_id]"
            value="<?php echo esc_attr($image_id); ?>" class="image-id">
        <button type="button"
            class="button upload-button"><?php esc_html_e('Upload/Select Image', 'craftedpath-toolkit'); ?></button>
        <button type="button" class="button remove-button"
            style="<?php echo $image_id ? '' : 'display:none;'; ?>"><?php esc_html_e('Remove Image', 'craftedpath-toolkit'); ?></button>
        <div class="image-preview" style="margin-top: 10px;">
            <?php if ($image_url): ?>
                <img src="<?php echo esc_url($image_url); ?>" style="max-width: 200px; height: auto;" />
            <?php endif; ?>
        </div>
    </div>
    <p class="description">
        <?php esc_html_e('Default image used for social sharing (e.g., Facebook, Twitter).', 'craftedpath-toolkit'); ?>
    </p>
    <?php
}

/**
 * Sanitize SEO settings.
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
        // Allow only specific dividers or default to '|'
        $output['meta_divider'] = in_array($input['meta_divider'], $allowed_dividers, true) ? $input['meta_divider'] : '|';
    }
    if (isset($input['social_share_image_id'])) {
        $output['social_share_image_id'] = absint($input['social_share_image_id']);
    }


    return $output;
}

/**
 * Enqueue scripts for the media uploader on the settings page.
 */
function enqueue_admin_scripts($hook_suffix)
{
    // Only load on our settings page.
    if ('settings_page_craftedpath-seo-settings' !== $hook_suffix) {
        return;
    }

    wp_enqueue_media(); // Enqueue WP media assets

    // Enqueue the admin settings script
    wp_enqueue_script(
        'craftedpath-seo-settings-js',
        plugin_dir_url(dirname(__FILE__, 2)) . 'assets/js/admin-seo-settings.js',
        ['jquery', 'wp-mediaelement'], // Dependencies
        CPT_VERSION, // Use plugin version constant
        true // Load in footer
    );
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_scripts');

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

    // Output Title Tag (WP handles the <title> tag itself via theme support, but we can filter it)
    // Instead, let's focus on description and potentially Open Graph tags later.
    // If you need to force the title, you might need to remove default WP title actions/filters.

    // Output Meta Description
    if (!empty($seo_description)) {
        echo '<meta name="description" content="' . esc_attr($seo_description) . '" />' . "\n";
    }

    // --- Open Graph Tags (Basic) ---
    $og_title = $final_title; // Use the same final title for OG
    $og_description = $seo_description;
    $og_url = get_permalink($post_id);
    $og_type = is_front_page() ? 'website' : 'article';
    $og_image_id = $options['social_share_image_id'] ?? 0;
    $og_image_url = $og_image_id ? wp_get_attachment_image_url($og_image_id, 'large') : ''; // Use large size for social

    // Allow themes/plugins to override OG data via filters if needed
    $og_title = apply_filters('craftedpath_og_title', $og_title, $post_id);
    $og_description = apply_filters('craftedpath_og_description', $og_description, $post_id);
    $og_url = apply_filters('craftedpath_og_url', $og_url, $post_id);
    $og_image_url = apply_filters('craftedpath_og_image', $og_image_url, $post_id);
    $og_type = apply_filters('craftedpath_og_type', $og_type, $post_id);

    echo '<meta property="og:title" content="' . esc_attr($og_title) . '" />' . "\n";
    echo '<meta property="og:type" content="' . esc_attr($og_type) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url($og_url) . '" />' . "\n";
    if (!empty($og_description)) {
        echo '<meta property="og:description" content="' . esc_attr($og_description) . '" />' . "\n";
    }
    if (!empty($og_image_url)) {
        echo '<meta property="og:image" content="' . esc_url($og_image_url) . '" />' . "\n";
        // Output image dimensions if possible (improves sharing)
        if ($og_image_id) {
            $image_meta = wp_get_attachment_metadata($og_image_id);
            if ($image_meta && isset($image_meta['sizes']['large'])) {
                echo '<meta property="og:image:width" content="' . esc_attr($image_meta['sizes']['large']['width']) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr($image_meta['sizes']['large']['height']) . '" />' . "\n";
            } elseif ($image_meta && isset($image_meta['width'])) {
                echo '<meta property="og:image:width" content="' . esc_attr($image_meta['width']) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr($image_meta['height']) . '" />' . "\n";
            }
        }
    }
    echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";

    // --- Twitter Card Tags (Basic) ---
    // You might want a setting for card type (summary, summary_large_image)
    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n"; // Assume large image for now
    echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '" />' . "\n"; // Use OG title
    if (!empty($og_description)) {
        echo '<meta name="twitter:description" content="' . esc_attr($og_description) . '" />' . "\n"; // Use OG description
    }
    if (!empty($og_image_url)) {
        echo '<meta name="twitter:image" content="' . esc_url($og_image_url) . '" />' . "\n"; // Use OG image
    }
    // Optional: Add Twitter site/creator handle via settings if desired
    // echo '<meta name="twitter:site" content="@YourTwitterHandle" />' . "\n";
    // echo '<meta name="twitter:creator" content="@AuthorTwitterHandle" />' . "\n";

}

// TODO: Add output_meta_tags function
// TODO: Add enqueue_admin_scripts action and create JS file
// TODO: Create Gutenberg integration 