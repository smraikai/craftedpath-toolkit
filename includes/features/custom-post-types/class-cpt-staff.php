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
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'staff'),
            'capability_type' => 'post',
            'has_archive' => true, // Consider setting to false if no archive page needed
            'hierarchical' => false,
            'menu_position' => null,
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