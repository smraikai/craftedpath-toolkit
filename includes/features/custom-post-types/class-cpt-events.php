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
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'event'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
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