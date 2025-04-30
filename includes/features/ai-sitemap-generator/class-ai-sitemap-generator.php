<?php
/**
 * Plugin Name:       AI Sitemap & Menu Generator
 * Plugin URI:        #
 * Description:       Uses an LLM to generate sitemaps and menus, then creates the corresponding pages and menu items in WordPress.
 * Version:           1.0.0
 * Author:            AI Assistant & User
 * Author URI:        #
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-sitemap-menu-generator
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 5.8
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define constants
define('AISMG_VERSION', '1.0.0');
define('AISMG_PATH', plugin_dir_path(__FILE__));
define('AISMG_URL', plugin_dir_url(__FILE__));
define('AISMG_PLUGIN_BASE', plugin_basename(__FILE__));

// Include necessary files
require_once AISMG_PATH . 'includes/settings-page.php';
require_once AISMG_PATH . 'includes/ajax-handlers.php';
require_once AISMG_PATH . 'includes/admin-page.php'; // For the main UI

/**
 * Enqueue admin scripts and styles.
 */
function aismg_enqueue_admin_scripts($hook)
{
    // Get the base slug for our menu
    $base_slug = 'ai-sitemap-menu-generator';

    // Define our page hooks
    $page_hooks = [
        'toplevel_page_' . $base_slug, // Main page hook (now correctly targets the default Sitemap page)
        $base_slug . '_page_aismg-menu',
        $base_slug . '_page_aismg-settings',
    ];

    // Only load on our plugin's pages
    if (!in_array($hook, $page_hooks)) {
        return;
    }

    // Add a general admin style for consistent plugin appearance
    wp_enqueue_style(
        'aismg-admin-common-css',
        AISMG_URL . 'assets/css/admin-common.css',
        [],
        AISMG_VERSION
    );

    wp_enqueue_style(
        'aismg-admin-css', // Keep specific styles if needed, or merge into common
        AISMG_URL . 'assets/css/admin-style.css',
        [],
        AISMG_VERSION
    );

    wp_enqueue_script(
        'aismg-admin-js',
        AISMG_URL . 'assets/js/admin-script.js',
        ['jquery'], // Dependency
        AISMG_VERSION,
        true // Load in footer
    );

    // Localize script for AJAX
    wp_localize_script('aismg-admin-js', 'aismg_ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aismg_ajax_nonce'),
        'sitemap_generating_text' => __('Generating sitemap...', 'ai-sitemap-menu-generator'),
        'sitemap_creating_text' => __('Creating pages...', 'ai-sitemap-menu-generator'),
        'menu_generating_text' => __('Generating menu...', 'ai-sitemap-menu-generator'),
        'menu_creating_text' => __('Creating menu items...', 'ai-sitemap-menu-generator'),
        'error_text' => __('An error occurred.', 'ai-sitemap-menu-generator'),
    ]);
}
add_action('admin_enqueue_scripts', 'aismg_enqueue_admin_scripts');


/**
 * Add plugin page to the Tools menu.
 */
function aismg_add_admin_menu()
{
    // Add Top-level Menu Page
    add_menu_page(
        __('AI Site Generator', 'ai-sitemap-menu-generator'),    // Page title
        __('AI Site Gen', 'ai-sitemap-menu-generator'),           // Menu title (shorter)
        'manage_options',                                          // Capability
        'ai-sitemap-menu-generator',                               // Menu slug (parent slug)
        'aismg_render_sitemap_page',                               // Function to display the default page content
        'dashicons-sos',                                           // Icon (Site Icon)
        75                                                         // Position (lower down)
    );

    // Add Sitemap Submenu Page (Slug matches parent to act as the default page)
    add_submenu_page(
        'ai-sitemap-menu-generator',                               // Parent slug
        __('Sitemap Page Generator', 'ai-sitemap-menu-generator'), // Page title (used for browser title bar)
        __('Sitemap Page Generator', 'ai-sitemap-menu-generator'), // Menu title (won't show, but keep consistent)
        'manage_options',                                          // Capability
        'ai-sitemap-menu-generator',                               // Menu slug **MUST MATCH PARENT SLUG** to be the default page
        'aismg_render_sitemap_page'                                // Function to display page content
    );

    // Add Menu Submenu Page
    add_submenu_page(
        'ai-sitemap-menu-generator',                               // Parent slug
        __('Menu Generator', 'ai-sitemap-menu-generator'),     // Page title
        __('Menu Generator', 'ai-sitemap-menu-generator'),     // Menu title
        'manage_options',                                          // Capability
        'aismg-menu',                                              // Menu slug
        'aismg_render_menu_page'                                   // Function to display page content
    );

    // Add Settings Submenu Page
    add_submenu_page(
        'ai-sitemap-menu-generator',                               // Parent slug
        __('Settings', 'ai-sitemap-menu-generator'),             // Page title
        __('Settings', 'ai-sitemap-menu-generator'),             // Menu title
        'manage_options',                                          // Capability
        'aismg-settings',                                          // Menu slug
        'aismg_render_settings_page'                               // Function to display page content
    );

    // No longer need to remove the submenu page as we made the first submenu slug match the parent
    // remove_submenu_page('ai-sitemap-menu-generator', 'ai-sitemap-menu-generator'); 
}
add_action('admin_menu', 'aismg_add_admin_menu');


/**
 * Register plugin settings.
 */
function aismg_register_settings()
{
    // Make sure settings are registered to the correct page slug
    $settings_page_slug = 'aismg-settings';

    register_setting(
        'aismg_settings_group', // Option group
        'aismg_openai_api_key',  // Option name
        [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]
    );
    register_setting(
        'aismg_settings_group', // Option group
        'aismg_llm_model',  // Option name for model selection
        [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-3.5-turbo', // Sensible default
        ]
    );

    // Add settings section
    add_settings_section(
        'aismg_settings_section_main', // ID
        __('API Settings', 'ai-sitemap-menu-generator'), // Title
        null, // Callback (optional description)
        $settings_page_slug // Page slug where section appears
    );

    // Add API Key field
    add_settings_field(
        'aismg_openai_api_key', // ID
        __('OpenAI API Key', 'ai-sitemap-menu-generator'), // Title
        'aismg_settings_field_api_key_html', // Callback function to render HTML
        $settings_page_slug, // Page slug
        'aismg_settings_section_main' // Section ID
    );

    // Add Model Selection field
    add_settings_field(
        'aismg_llm_model', // ID
        __('LLM Model', 'ai-sitemap-menu-generator'), // Title
        'aismg_settings_field_llm_model_html', // Callback function to render HTML
        $settings_page_slug, // Page slug
        'aismg_settings_section_main' // Section ID
    );
}
add_action('admin_init', 'aismg_register_settings');


/**
 * Activation hook actions.
 */
function aismg_activate()
{
    // Create assets directories if they don't exist
    if (!file_exists(AISMG_PATH . 'assets/js')) {
        mkdir(AISMG_PATH . 'assets/js', 0755, true);
    }
    if (!file_exists(AISMG_PATH . 'assets/css')) {
        mkdir(AISMG_PATH . 'assets/css', 0755, true);
    }
    // Ensure JS file exists
    if (!file_exists(AISMG_PATH . 'assets/js/admin-script.js')) {
        file_put_contents(AISMG_PATH . 'assets/js/admin-script.js', '// AI Sitemap & Menu Generator Admin JS' . PHP_EOL);
    }
    // Ensure CSS file exists
    if (!file_exists(AISMG_PATH . 'assets/css/admin-style.css')) {
        file_put_contents(AISMG_PATH . 'assets/css/admin-style.css', '/* AI Sitemap & Menu Generator Admin CSS */' . PHP_EOL);
    }
    error_log('AI Sitemap & Menu Generator Activated');
}
register_activation_hook(__FILE__, 'aismg_activate');

/**
 * Deactivation hook actions.
 */
function aismg_deactivate()
{
    error_log('AI Sitemap & Menu Generator Deactivated');
}
register_deactivation_hook(__FILE__, 'aismg_deactivate');

?>