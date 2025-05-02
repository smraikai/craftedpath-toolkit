<?php
/**
 * Custom Post Type: Staff
 */
if (!defined('WPINC'))
    die;

class CPT_Staff
{
    public function __construct()
    {
        // Don't load if Meta Box is not active
        if (!function_exists('rwmb_meta')) {
            return;
        }
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_filter('rwmb_meta_boxes', array($this, 'register_meta_boxes'));

        // Add link to taxonomy on Staff list page
        add_filter('views_edit-staff', array($this, 'add_department_link_to_views'));

        // Fix admin menu highlighting for the taxonomy page
        add_filter('parent_file', array($this, 'fix_department_menu'));
        add_filter('submenu_file', array($this, 'fix_department_submenu'));
    }

    public function register_post_type()
    {
        $labels = array(
            'name' => _x('Staff', 'Post type general name', 'craftedpath-toolkit'),
            'singular_name' => _x('Staff Member', 'Post type singular name', 'craftedpath-toolkit'),
            'menu_name' => _x('Staff', 'Admin Menu text', 'craftedpath-toolkit'),
            'name_admin_bar' => _x('Staff Member', 'Add New on Toolbar', 'craftedpath-toolkit'),
            // Add other labels
        );
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'cptk-content-menu',
            'query_var' => true,
            'rewrite' => array('slug' => 'staff'),
            'capability_type' => 'post',
            'has_archive' => true, // Consider setting to false if no archive page needed
            'hierarchical' => false,
            'menu_icon' => 'dashicons-groups',
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields', 'excerpt'), // Add excerpt
            'show_in_rest' => true,
        );
        register_post_type('staff', $args);
    }

    public function register_taxonomy()
    {
        $labels = array(
            'name' => _x('Departments', 'taxonomy general name', 'craftedpath-toolkit'),
            'singular_name' => _x('Department', 'taxonomy singular name', 'craftedpath-toolkit'),
            'menu_name' => __('Departments', 'craftedpath-toolkit'),
            // Add other labels
        );
        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'department'),
            'show_in_rest' => true,
        );
        register_taxonomy('department', array('staff'), $args);
    }

    /**
     * Add a link to the Department management page on the Staff list table.
     *
     * @param array $views Existing views (All, Published, etc.)
     * @return array Modified views array.
     */
    public function add_department_link_to_views($views)
    {
        $taxonomy_slug = 'department';
        $taxonomy_object = get_taxonomy($taxonomy_slug);

        if (!$taxonomy_object || !current_user_can($taxonomy_object->cap->manage_terms)) {
            return $views;
        }

        $url = admin_url('edit-tags.php?taxonomy=' . $taxonomy_slug);
        $views['department'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            esc_html($taxonomy_object->labels->name) // Use the registered taxonomy name
        );

        return $views;
    }

    /**
     * Corrects the parent menu highlighting for Departments page.
     *
     * @param string $parent_file The current parent file.
     * @return string Corrected parent file.
     */
    public function fix_department_menu($parent_file)
    {
        global $current_screen;
        if ($current_screen->taxonomy == 'department') {
            $parent_file = 'cptk-content-menu'; // Slug of our custom parent menu
        }
        return $parent_file;
    }

    /**
     * Corrects the submenu highlighting for Departments page.
     *
     * @param string $submenu_file The current submenu file.
     * @return string Corrected submenu file.
     */
    public function fix_department_submenu($submenu_file)
    {
        global $current_screen;
        if ($current_screen->taxonomy == 'department') {
            $submenu_file = 'edit-tags.php?taxonomy=department';
        }
        return $submenu_file;
    }

    public function register_meta_boxes($meta_boxes)
    {
        if (!function_exists('rwmb_meta')) {
            return $meta_boxes;
        }

        $prefix = 'cptk_staff_';

        $meta_boxes[] = array(
            'id' => $prefix . 'details',
            'title' => __('Staff Details', 'craftedpath-toolkit'),
            'post_types' => array('staff'),
            'context' => 'normal',
            'priority' => 'high',
            'fields' => array(
                array(
                    'name' => __('Job Title / Position', 'craftedpath-toolkit'),
                    'id' => $prefix . 'position',
                    'type' => 'text',
                ),
                array(
                    'name' => __('Email Address', 'craftedpath-toolkit'),
                    'id' => $prefix . 'email',
                    'type' => 'email',
                ),
                array(
                    'name' => __('Phone Number', 'craftedpath-toolkit'),
                    'id' => $prefix . 'phone',
                    'type' => 'text',
                ),
                // Add more fields like social media links, etc.
            ),
        );

        return $meta_boxes;
    }
}