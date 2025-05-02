<?php
/**
 * Custom Post Type: FAQs
 */
if (!defined('WPINC'))
    die;

class CPT_FAQs
{
    public function __construct()
    {
        // Don't load if Meta Box is not active (keeping CPTs consistent)
        if (!function_exists('rwmb_meta')) {
            return;
        }
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        // No Meta Box fields needed by default for FAQ - title = question, editor = answer

        // Add link to taxonomy on FAQ list page
        add_filter('views_edit-faq', array($this, 'add_category_link_to_views'));

        // Fix admin menu highlighting for the taxonomy page
        add_filter('parent_file', array($this, 'fix_faq_category_menu'));
        add_filter('submenu_file', array($this, 'fix_faq_category_submenu'));
    }

    public function register_post_type()
    {
        $labels = array(
            'name' => _x('FAQs', 'Post type general name', 'craftedpath-toolkit'),
            'singular_name' => _x('FAQ', 'Post type singular name', 'craftedpath-toolkit'),
            'menu_name' => _x('FAQs', 'Admin Menu text', 'craftedpath-toolkit'),
            'name_admin_bar' => _x('FAQ', 'Add New on Toolbar', 'craftedpath-toolkit'),
            // Add other labels
        );
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'cptk-content-menu',
            'query_var' => true,
            'rewrite' => array('slug' => 'faq'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_icon' => 'dashicons-editor-help',
            'supports' => array('title', 'editor', 'custom-fields'), // Title is Question, Editor is Answer
            'show_in_rest' => true,
        );
        register_post_type('faq', $args);
    }

    public function register_taxonomy()
    {
        $labels = array(
            'name' => _x('FAQ Categories', 'taxonomy general name', 'craftedpath-toolkit'),
            'singular_name' => _x('FAQ Category', 'taxonomy singular name', 'craftedpath-toolkit'),
            'search_items' => __('Search FAQ Categories', 'craftedpath-toolkit'),
            'all_items' => __('All FAQ Categories', 'craftedpath-toolkit'),
            'parent_item' => __('Parent FAQ Category', 'craftedpath-toolkit'),
            'parent_item_colon' => __('Parent FAQ Category:', 'craftedpath-toolkit'),
            'edit_item' => __('Edit FAQ Category', 'craftedpath-toolkit'),
            'update_item' => __('Update FAQ Category', 'craftedpath-toolkit'),
            'add_new_item' => __('Add New FAQ Category', 'craftedpath-toolkit'),
            'new_item_name' => __('New FAQ Category Name', 'craftedpath-toolkit'),
            'menu_name' => __('Categories', 'craftedpath-toolkit'),
        );
        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'faq-category'),
            'show_in_rest' => true, // Important for Gutenberg
        );
        register_taxonomy('faq_category', array('faq'), $args); // Associate with 'faq' post type
    }

    /**
     * Add a link to the FAQ Category management page on the FAQ list table.
     *
     * @param array $views Existing views (All, Published, etc.)
     * @return array Modified views array.
     */
    public function add_category_link_to_views($views)
    {
        $taxonomy_slug = 'faq_category';
        $taxonomy_object = get_taxonomy($taxonomy_slug);

        if (!$taxonomy_object || !current_user_can($taxonomy_object->cap->manage_terms)) {
            return $views;
        }

        $url = admin_url('edit-tags.php?taxonomy=' . $taxonomy_slug);
        $views['faq_category'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            esc_html($taxonomy_object->labels->name) // Use the registered taxonomy name
        );

        return $views;
    }

    /**
     * Corrects the parent menu highlighting for FAQ Categories page.
     *
     * @param string $parent_file The current parent file.
     * @return string Corrected parent file.
     */
    public function fix_faq_category_menu($parent_file)
    {
        global $current_screen;
        if ($current_screen->taxonomy == 'faq_category') {
            $parent_file = 'cptk-content-menu'; // Slug of our custom parent menu
        }
        return $parent_file;
    }

    /**
     * Corrects the submenu highlighting for FAQ Categories page.
     *
     * @param string $submenu_file The current submenu file.
     * @return string Corrected submenu file.
     */
    public function fix_faq_category_submenu($submenu_file)
    {
        global $current_screen;
        if ($current_screen->taxonomy == 'faq_category') {
            $submenu_file = 'edit-tags.php?taxonomy=faq_category';
        }
        return $submenu_file;
    }
}