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
        require_once CPT_PLUGIN_DIR . 'includes/admin/class-settings-manager.php';
    }

    private function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'init_settings'));
        add_action('plugins_loaded', array($this, 'load_features'));
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
        new CPT_Bem_Generator();
    }
    }
}

// Initialize the plugin
function craftedpath_toolkit()
{
    return CraftedPath_Toolkit::instance();
}
craftedpath_toolkit();