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
        // Include the Admin Refresh UI feature
        require_once CPT_PLUGIN_DIR . 'includes/features/admin-refresh-ui/class-admin-refresh-ui.php';

        require_once CPT_PLUGIN_DIR . 'includes/admin/class-settings-manager.php';
        require_once CPT_PLUGIN_DIR . 'includes/admin/settings-page.php';
        require_once CPT_PLUGIN_DIR . 'includes/admin/views/ui-components.php';

        // SEO functionality is now loaded conditionally in load_features()
        // require_once CPT_PLUGIN_DIR . 'includes/seo.php';
    }

    private function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'init_settings'));
        add_action('plugins_loaded', array($this, 'load_features'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Initialize SEO hooks moved to load_features()
        /*
        if (file_exists(CPT_PLUGIN_DIR . 'includes/seo.php') && function_exists('\CraftedPath\Toolkit\SEO\setup')) {
            \CraftedPath\Toolkit\SEO\setup();
        }
        */
    }

    /**
     * Enqueue scripts and styles for the plugin.
     */
    public function enqueue_assets($hook_suffix)
    {
        // --- General Admin Assets ---

        // Enqueue Toast CSS
        wp_enqueue_style(
            'cpt-toast-style',
            CPT_PLUGIN_URL . 'assets/css/toast.css',
            array(),
            CPT_VERSION
        );

        // Enqueue Iconoir CSS from CDN
        wp_enqueue_style(
            'iconoir-css',
            'https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css',
            array(), // No dependencies
            null // No specific version needed when using @main
            // 'all' // Media type (optional)
        );

        // Enqueue Toast JS
        wp_enqueue_script(
            'cpt-toast-script',
            CPT_PLUGIN_URL . 'assets/js/toast.js',
            array('jquery'), // Assuming dependency on jQuery for simplicity, can be changed later
            CPT_VERSION,
            true // Load in footer
        );

        // --- Editor Assets ---
        // Get screen information
        $screen = get_current_screen();

        // Check if we are on a block editor screen
        if ($screen && $screen->is_block_editor()) {
            // --- Conditionally Enqueue SEO Panel Assets ---
            $settings_manager = CPT_Settings_Manager::instance(); // Get settings manager instance
            if ($settings_manager->is_feature_enabled('seo_tools')) {
                // Enqueue the SEO panel script
                $script_asset_path = CPT_PLUGIN_DIR . "build/index.asset.php";
                if (file_exists($script_asset_path)) {
                    $script_asset = require($script_asset_path);
                    wp_enqueue_script(
                        'craftedpath-seo-panel-script',
                        CPT_PLUGIN_URL . 'build/index.js',
                        $script_asset['dependencies'],
                        $script_asset['version'],
                        true // Load in footer
                    );

                    // Localize script with SEO settings
                    $seo_options = get_option('craftedpath_seo_settings', []);
                    $site_name = !empty($seo_options['site_name']) ? $seo_options['site_name'] : get_bloginfo('name');
                    $divider = $seo_options['meta_divider'] ?? '|';

                    wp_localize_script(
                        'craftedpath-seo-panel-script',
                        'cptSeoData',
                        array(
                            'siteName' => $site_name,
                            'divider' => $divider,
                        )
                    );
                }
            }
            // --- End SEO Panel Assets ---

            // Potentially enqueue other editor-specific styles/scripts here if needed
            // wp_enqueue_style(...);
        }
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

        // Load Admin Refresh UI
        if ($settings_manager->is_feature_enabled('admin_refresh_ui')) {
            if (class_exists('CPT_Admin_Refresh_UI')) {
                CPT_Admin_Refresh_UI::instance();
            } else {
                error_log("CraftedPath Toolkit Error: CPT_Admin_Refresh_UI class not found.");
            }
        }

        // Load SEO Tools
        if ($settings_manager->is_feature_enabled('seo_tools')) {
            $seo_file = CPT_PLUGIN_DIR . 'includes/features/seo/seo.php';
            if (file_exists($seo_file)) {
                require_once $seo_file;
                if (function_exists('\CraftedPath\Toolkit\SEO\setup')) {
                    \CraftedPath\Toolkit\SEO\setup();
                }
            } else {
                error_log("CraftedPath Toolkit Error: includes/features/seo/seo.php file not found.");
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