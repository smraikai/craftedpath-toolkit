<?php
/**
 * Custom Post Type: Events
 */
if (!defined('WPINC'))
    die;

class CPT_Events
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

        // Add link to taxonomy on Event list page
        add_filter('views_edit-event', array($this, 'add_category_link_to_views'));

        // Fix admin menu highlighting for the taxonomy page
        add_filter('parent_file', array($this, 'fix_event_category_menu'));
        add_filter('submenu_file', array($this, 'fix_event_category_submenu'));
    }

    public function register_post_type()
    {
        $labels = array(
            'name' => _x('Events', 'Post type general name', 'craftedpath-toolkit'),
            'singular_name' => _x('Event', 'Post type singular name', 'craftedpath-toolkit'),
            'menu_name' => _x('Events', 'Admin Menu text', 'craftedpath-toolkit'),
            'name_admin_bar' => _x('Event', 'Add New on Toolbar', 'craftedpath-toolkit'),
            // Add other labels
        );
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'cptk-content-menu',
            'query_var' => true,
            'rewrite' => array('slug' => 'event'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields', 'excerpt'),
            'show_in_rest' => true,
        );
        register_post_type('event', $args);
    }

    public function register_taxonomy()
    {
        $labels = array(
            'name' => _x('Event Categories', 'taxonomy general name', 'craftedpath-toolkit'),
            'singular_name' => _x('Event Category', 'taxonomy singular name', 'craftedpath-toolkit'),
            'menu_name' => __('Categories', 'craftedpath-toolkit'),
            // Add other labels
        );
        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'event-category'),
            'show_in_rest' => true,
        );
        register_taxonomy('event_category', array('event'), $args);
    }

    /**
     * Add a link to the Event Category management page on the Event list table.
     *
     * @param array $views Existing views (All, Published, etc.)
     * @return array Modified views array.
     */
    public function add_category_link_to_views($views)
    {
        $taxonomy_slug = 'event_category';
        $taxonomy_object = get_taxonomy($taxonomy_slug);

        if (!$taxonomy_object || !current_user_can($taxonomy_object->cap->manage_terms)) {
            return $views;
        }

        $url = admin_url('edit-tags.php?taxonomy=' . $taxonomy_slug);
        $views['event_category'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            esc_html($taxonomy_object->labels->name) // Use the registered taxonomy name (e.g., "Event Categories")
        );

        return $views;
    }

    /**
     * Corrects the parent menu highlighting for Event Categories page.
     *
     * @param string $parent_file The current parent file.
     * @return string Corrected parent file.
     */
    public function fix_event_category_menu($parent_file)
    {
        global $current_screen;
        if ($current_screen->taxonomy == 'event_category') {
            $parent_file = 'cptk-content-menu'; // Slug of our custom parent menu
        }
        return $parent_file;
    }

    /**
     * Corrects the submenu highlighting for Event Categories page.
     *
     * @param string $submenu_file The current submenu file.
     * @return string Corrected submenu file.
     */
    public function fix_event_category_submenu($submenu_file)
    {
        global $current_screen;
        if ($current_screen->taxonomy == 'event_category') {
            $submenu_file = 'edit-tags.php?taxonomy=event_category';
        }
        return $submenu_file;
    }

    public function register_meta_boxes($meta_boxes)
    {
        if (!function_exists('rwmb_meta')) {
            return $meta_boxes;
        }

        $prefix = 'cptk_event_';

        $meta_boxes[] = array(
            'id' => $prefix . 'details',
            'title' => __('Event Details', 'craftedpath-toolkit'),
            'post_types' => array('event'),
            'context' => 'normal',
            'priority' => 'high',
            'fields' => array(
                array(
                    'name' => __('Start Date & Time', 'craftedpath-toolkit'),
                    'id' => $prefix . 'start_datetime',
                    'type' => 'datetime',
                    'js_options' => [
                        'stepMinute' => 15,
                        'showTimepicker' => true,
                    ],
                ),
                array(
                    'name' => __('End Date & Time', 'craftedpath-toolkit'),
                    'id' => $prefix . 'end_datetime',
                    'type' => 'datetime',
                    'js_options' => [
                        'stepMinute' => 15,
                        'showTimepicker' => true,
                    ],
                ),
                array(
                    'name' => __('Location Name', 'craftedpath-toolkit'),
                    'id' => $prefix . 'location_name',
                    'type' => 'text',
                ),
                array(
                    'name' => __('Location Address', 'craftedpath-toolkit'),
                    'id' => $prefix . 'location_address',
                    'type' => 'textarea',
                    'rows' => 3,
                ),
                array(
                    'name' => __('Website / Registration Link', 'craftedpath-toolkit'),
                    'id' => $prefix . 'url',
                    'type' => 'url',
                ),
            ),
        );

        return $meta_boxes;
    }
}