<?php
/**
 * Plugin Name:       CraftedPath Toolkit
 * Plugin URI:        #
 * Description:       A collection of tools and enhancements for WordPress development.
 * Version:           1.0.0
 * Author:            Your Name/Company
 * Author URI:        #
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       craftedpath-toolkit
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define constants
define('CPT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CPT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CPT_VERSION', '1.0.0');

// Main plugin class
final class CraftedPath_Toolkit
{
    private static $instance = null;
    private $settings_manager;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->includes();
        $this->init_hooks();
    }

    private function includes()
    {
        require_once CPT_PLUGIN_DIR . 'includes/features/bem-generator/class-bem-generator.php';
        // Include the AI generator files
        // require_once CPT_PLUGIN_DIR . 'includes/features/ai-sitemap-generator/class-cpt-ai-sitemap-generator.php'; // Deprecated
        require_once CPT_PLUGIN_DIR . 'includes/features/ai-page-generator/class-cpt-ai-page-generator.php';
        require_once CPT_PLUGIN_DIR . 'includes/features/ai-menu-generator/class-cpt-ai-menu-generator.php';

        require_once CPT_PLUGIN_DIR . 'includes/admin/class-settings-manager.php';
        require_once CPT_PLUGIN_DIR . 'includes/admin/settings-page.php';
        require_once CPT_PLUGIN_DIR . 'includes/admin/views/ui-components.php';
    }

    private function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'init_settings'));
        add_action('plugins_loaded', array($this, 'load_features'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue scripts and styles for the plugin.
     */
    public function enqueue_assets()
    {
        // Enqueue Toast CSS
        wp_enqueue_style(
            'cpt-toast-style',
            CPT_PLUGIN_URL . 'assets/css/toast.css',
            array(),
            CPT_VERSION
        );

        // Enqueue Toast JS
        wp_enqueue_script(
            'cpt-toast-script',
            CPT_PLUGIN_URL . 'assets/js/toast.js',
            array('jquery'), // Assuming dependency on jQuery for simplicity, can be changed later
            CPT_VERSION,
            true // Load in footer
        );
    }

    public function init_settings()
    {
        // Use singleton pattern for settings manager
        $this->settings_manager = CPT_Settings_Manager::instance();
    }

    public function load_features()
    {
        // Use the singleton instance
        $settings_manager = CPT_Settings_Manager::instance();

        if ($settings_manager->is_feature_enabled('bem_generator')) {
            // Ensure class exists before instantiation
            if (class_exists('CPT_Bem_Generator')) {
                new CPT_Bem_Generator(); // Reverted: Use new, not instance()
            } else {
                error_log("CraftedPath Toolkit Error: CPT_Bem_Generator class not found.");
            }
        }

        // Load AI Page Generator
        if ($settings_manager->is_feature_enabled('ai_page_generator')) { // Adjusted feature key
            if (class_exists('CPT_AI_Page_Generator')) {
                CPT_AI_Page_Generator::instance();
            } else {
                error_log("CraftedPath Toolkit Error: CPT_AI_Page_Generator class not found.");
            }
        }

        // Load AI Menu Generator
        if ($settings_manager->is_feature_enabled('ai_menu_generator')) { // Adjusted feature key
            if (class_exists('CPT_AI_Menu_Generator')) {
                CPT_AI_Menu_Generator::instance();
            } else {
                error_log("CraftedPath Toolkit Error: CPT_AI_Menu_Generator class not found.");
            }
        }

        /* Deprecated Sitemap Generator loading
        if ($settings_manager->is_feature_enabled('ai_sitemap_generator')) {
            // Make sure the class exists before instantiating
            if (class_exists('CPT_AI_Sitemap_Generator')) {
                CPT_AI_Sitemap_Generator::instance();
            }
        }
        */
    }
}

// Initialize the plugin
function craftedpath_toolkit()
{
    return CraftedPath_Toolkit::instance();
}
craftedpath_toolkit();