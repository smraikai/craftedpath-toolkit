<?php
/**
 * Disable Comments Feature for CraftedPath Toolkit
 *
 * @package CraftedPath_Toolkit
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CPT_Disable_Comments Class
 */
class CPT_Disable_Comments
{
    /**
     * Singleton instance
     * @var CPT_Disable_Comments|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Frontend comment disabling
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);
        add_filter('comments_array', array($this, 'empty_comments_array'), 10, 2);

        // Hide existing comments
        add_filter('the_comments', '__return_empty_array');

        // Remove comment form
        add_filter('comment_form_defaults', array($this, 'disable_comment_form'));

        // Remove comment support from post types
        add_action('init', array($this, 'remove_comment_support'), 100);

        // Admin-side changes
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'admin_menu'), 9999);
        add_action('wp_dashboard_setup', array($this, 'dashboard_widgets'));

        // Disable comment REST API endpoints
        add_filter('rest_endpoints', array($this, 'disable_comments_rest_api'));

        // Remove comment-related widgets
        add_action('widgets_init', array($this, 'disable_comment_widgets'), 20);

        // Add admin notice
        add_action('admin_notices', array($this, 'admin_notice'));

        // Close comments on all existing posts
        add_action('admin_init', array($this, 'close_comments_on_existing_posts'));

        // Enqueue CSS to hide comment UI elements
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Return empty comments array
     */
    public function empty_comments_array($comments, $post_id)
    {
        return array();
    }

    /**
     * Disable the comment form
     */
    public function disable_comment_form($defaults)
    {
        $defaults['comment_notes_before'] = __('Comments are disabled.', 'craftedpath-toolkit');
        $defaults['title_reply'] = '';
        $defaults['cancel_reply_link'] = '';
        return $defaults;
    }

    /**
     * Remove comment support from post types
     */
    public function remove_comment_support()
    {
        // Get all post types that support comments
        $post_types = get_post_types(array('public' => true), 'names');

        foreach ($post_types as $post_type) {
            if (post_type_supports($post_type, 'comments')) {
                remove_post_type_support($post_type, 'comments');
                remove_post_type_support($post_type, 'trackbacks');
            }
        }
    }

    /**
     * Admin init actions
     */
    public function admin_init()
    {
        // Remove comment metaboxes
        remove_meta_box('commentstatusdiv', 'post', 'normal');
        remove_meta_box('commentsdiv', 'post', 'normal');
        remove_meta_box('commentstatusdiv', 'page', 'normal');
        remove_meta_box('commentsdiv', 'page', 'normal');

        // Also do this for all custom post types
        $post_types = get_post_types(array('public' => true), 'names');
        foreach ($post_types as $post_type) {
            remove_meta_box('commentstatusdiv', $post_type, 'normal');
            remove_meta_box('commentsdiv', $post_type, 'normal');
        }

        // Remove comment-related admin CSS
        wp_dequeue_style('admin-comments');

        // Filters for admin area
        add_filter('wp_count_comments', array($this, 'filter_wp_count_comments'));
    }

    /**
     * Filter wp_count_comments to show zero comments
     */
    public function filter_wp_count_comments($count)
    {
        return (object) array(
            'approved' => 0,
            'moderated' => 0,
            'spam' => 0,
            'trash' => 0,
            'post-trashed' => 0,
            'total_comments' => 0,
            'all' => 0
        );
    }

    /**
     * Modify admin menu to remove comment-related items
     */
    public function admin_menu()
    {
        // Remove the comments menu
        remove_menu_page('edit-comments.php');

        // Remove comments from other admin menus
        if (function_exists('remove_submenu_page')) {
            // Comments from Posts
            remove_submenu_page('edit.php', 'edit-comments.php');
        }

        // Remove comments from admin bar (top menu)
        remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
    }

    /**
     * Remove comment-related dashboard widgets
     */
    public function dashboard_widgets()
    {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    }

    /**
     * Disable comments REST API endpoints
     */
    public function disable_comments_rest_api($endpoints)
    {
        // Remove comment endpoints
        if (isset($endpoints['/wp/v2/comments'])) {
            unset($endpoints['/wp/v2/comments']);
        }
        if (isset($endpoints['/wp/v2/comments/(?P<id>[\d]+)'])) {
            unset($endpoints['/wp/v2/comments/(?P<id>[\d]+)']);
        }

        return $endpoints;
    }

    /**
     * Disable comment widgets
     */
    public function disable_comment_widgets()
    {
        // Unregister comment widgets
        unregister_widget('WP_Widget_Recent_Comments');
    }

    /**
     * Display admin notice about disabled comments
     */
    public function admin_notice()
    {
        // Only show on specific pages
        $screen = get_current_screen();
        if ($screen && in_array($screen->base, array('post', 'page', 'edit'))) {
            echo '<div class="notice notice-info is-dismissible"><p>';
            echo esc_html__('Comments are currently disabled by CraftedPath Toolkit.', 'craftedpath-toolkit');
            echo '</p></div>';
        }
    }

    /**
     * Close comments on all existing posts
     */
    public function close_comments_on_existing_posts()
    {
        // We only do this once, when feature is activated
        $status = get_option('cptk_disable_comments_processed', false);

        if (!$status) {
            global $wpdb;

            // Update all existing posts to disable comments
            $wpdb->query("UPDATE {$wpdb->posts} SET comment_status = 'closed', ping_status = 'closed'");

            // Set flag so we don't run this again
            update_option('cptk_disable_comments_processed', true);
        }
    }

    /**
     * Enqueue styles to hide comment UI elements
     */
    public function enqueue_styles()
    {
        wp_enqueue_style(
            'cpt-disable-comments',
            CPT_PLUGIN_URL . 'includes/features/disable-comments/css/disable-comments.css',
            array(),
            CPT_VERSION
        );
    }
}