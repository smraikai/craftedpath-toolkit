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
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'faq'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
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
}