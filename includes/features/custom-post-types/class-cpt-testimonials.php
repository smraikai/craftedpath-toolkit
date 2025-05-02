<?php
/**
 * Custom Post Type: Testimonials
 */
if (!defined('WPINC'))
    die;

class CPT_Testimonials
{
    public function __construct()
    {
        // Don't load if Meta Box is not active
        if (!function_exists('rwmb_meta')) {
            return;
        }
        add_action('init', array($this, 'register_post_type'));
        add_filter('rwmb_meta_boxes', array($this, 'register_meta_boxes'));
    }

    public function register_post_type()
    {
        $labels = array(
            'name' => _x('Testimonials', 'Post type general name', 'craftedpath-toolkit'),
            'singular_name' => _x('Testimonial', 'Post type singular name', 'craftedpath-toolkit'),
            'menu_name' => _x('Testimonials', 'Admin Menu text', 'craftedpath-toolkit'),
            'name_admin_bar' => _x('Testimonial', 'Add New on Toolbar', 'craftedpath-toolkit'),
            // Add other labels as needed
        );
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'cptk-content-menu',
            'query_var' => true,
            'rewrite' => array('slug' => 'testimonial'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_icon' => 'dashicons-testimonial',
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'show_in_rest' => true, // For Gutenberg support
        );
        register_post_type('testimonial', $args);
    }

    public function register_meta_boxes($meta_boxes)
    {
        // Check if Meta Box is active
        if (!function_exists('rwmb_meta')) {
            return $meta_boxes;
        }

        $prefix = 'cptk_testimonial_';

        $meta_boxes[] = array(
            'id' => $prefix . 'details',
            'title' => __('Testimonial Details', 'craftedpath-toolkit'),
            'post_types' => array('testimonial'),
            'context' => 'normal',
            'priority' => 'high',
            'fields' => array(
                array(
                    'name' => __('Author/Client Name', 'craftedpath-toolkit'),
                    'id' => $prefix . 'author',
                    'type' => 'text',
                ),
                array(
                    'name' => __('Company/Position', 'craftedpath-toolkit'),
                    'id' => $prefix . 'position',
                    'type' => 'text',
                ),
                array(
                    'name' => __('Rating (1-5)', 'craftedpath-toolkit'),
                    'id' => $prefix . 'rating',
                    'type' => 'number',
                    'min' => 1,
                    'max' => 5,
                    'step' => 1,
                ),
                // Add more fields as needed (e.g., website URL, date)
            ),
        );

        return $meta_boxes;
    }
}