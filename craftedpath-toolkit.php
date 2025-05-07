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
        // Include the AI generator files
        require_once CPT_PLUGIN_DIR . 'includes/features/ai-page-generator/class-cpt-ai-page-generator.php';
        require_once CPT_PLUGIN_DIR . 'includes/features/ai-menu-generator/class-cpt-ai-menu-generator.php';
        require_once CPT_PLUGIN_DIR . 'includes/features/ai-alt-text/class-cpt-ai-alt-text.php';
        // Include the Admin Refresh UI feature
        require_once CPT_PLUGIN_DIR . 'includes/features/admin-refresh-ui/class-admin-refresh-ui.php';
        // Include the Admin Quick Search feature
        require_once CPT_PLUGIN_DIR . 'includes/features/admin-quick-search/class-cpt-admin-quick-search.php';

        require_once CPT_PLUGIN_DIR . 'includes/admin/class-settings-manager.php';
        require_once CPT_PLUGIN_DIR . 'includes/admin/settings-page.php';
        require_once CPT_PLUGIN_DIR . 'includes/admin/views/ui-components.php';

        // Load CPT classes (if feature enabled later)
        require_once CPT_PLUGIN_DIR . 'includes/features/custom-post-types/class-cpt-testimonials.php';
        require_once CPT_PLUGIN_DIR . 'includes/features/custom-post-types/class-cpt-faqs.php';
        require_once CPT_PLUGIN_DIR . 'includes/features/custom-post-types/class-cpt-staff.php';
        require_once CPT_PLUGIN_DIR . 'includes/features/custom-post-types/class-cpt-events.php';

        // Include the API handler
        require_once CPT_PLUGIN_DIR . 'includes/api/class-cptk-ai-api.php';
    }

    private function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'init_settings'));
        add_action('plugins_loaded', array($this, 'load_features'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('init', array($this, 'register_blocks'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

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

        // Enqueue Global Variables CSS (should be loaded first)
        wp_enqueue_style(
            'cpt-variables',
            CPT_PLUGIN_URL . 'assets/css/variables.css',
            array(), // No dependencies for the variables file itself
            CPT_VERSION
        );

        // Enqueue Toast CSS
        wp_enqueue_style(
            'cpt-toast-style',
            CPT_PLUGIN_URL . 'assets/css/toast.css',
            array('cpt-variables'), // Depends on variables
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

        // Load AI Auto Categorize
        if ($settings_manager->is_feature_enabled('ai_auto_categorize')) {
            // Ensure the class file is loaded before checking existence
            $auto_cat_file = CPT_PLUGIN_DIR . 'includes/features/ai-auto-categorize/class-cpt-ai-auto-categorize.php';
            if (file_exists($auto_cat_file)) {
                require_once $auto_cat_file;
                if (class_exists('CPT_AI_Auto_Categorize')) {
                    CPT_AI_Auto_Categorize::instance();
                } else {
                    error_log("CraftedPath Toolkit Error: CPT_AI_Auto_Categorize class not found after requiring file.");
                }
            } else {
                error_log("CraftedPath Toolkit Error: CPT_AI_Auto_Categorize class file not found at: " . $auto_cat_file);
            }
        }

        // Load AI Auto Tag
        if ($settings_manager->is_feature_enabled('ai_auto_tag')) {
            $auto_tag_file = CPT_PLUGIN_DIR . 'includes/features/ai-auto-tag/class-cpt-ai-auto-tag.php';
            if (file_exists($auto_tag_file)) {
                require_once $auto_tag_file;
                if (class_exists('CPT_AI_Auto_Tag')) {
                    CPT_AI_Auto_Tag::instance();
                } else {
                    error_log("CraftedPath Toolkit Error: CPT_AI_Auto_Tag class not found after requiring file.");
                }
            } else {
                error_log("CraftedPath Toolkit Error: CPT_AI_Auto_Tag class file not found at: " . $auto_tag_file);
            }
        }

        // Load AI Alt Text
        if ($settings_manager->is_feature_enabled('ai_alt_text')) {
            if (class_exists('CPT_AI_Alt_Text')) {
                CPT_AI_Alt_Text::instance();
            } else {
                error_log("CraftedPath Toolkit Error: CPT_AI_Alt_Text class not found.");
            }
        }

        // Load Admin Quick Search
        if ($settings_manager->is_feature_enabled('admin_quick_search')) {
            if (class_exists('CPT_Admin_Quick_Search')) {
                CPT_Admin_Quick_Search::instance();
            } else {
                error_log("CraftedPath Toolkit Error: CPT_Admin_Quick_Search class not found.");
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

        // Load Custom Post Types (Only if Meta Box is active)
        if (function_exists('rwmb_meta')) {
            if ($settings_manager->is_feature_enabled('cpt_testimonials')) {
                if (class_exists('CPT_Testimonials')) {
                    new CPT_Testimonials();
                } else {
                    error_log("CraftedPath Toolkit Error: CPT_Testimonials class not found.");
                }
            }
            if ($settings_manager->is_feature_enabled('cpt_faqs')) {
                if (class_exists('CPT_FAQs')) {
                    new CPT_FAQs();
                } else {
                    error_log("CraftedPath Toolkit Error: CPT_FAQs class not found.");
                }
            }
            if ($settings_manager->is_feature_enabled('cpt_staff')) {
                if (class_exists('CPT_Staff')) {
                    new CPT_Staff();
                } else {
                    error_log("CraftedPath Toolkit Error: CPT_Staff class not found.");
                }
            }
            if ($settings_manager->is_feature_enabled('cpt_events')) {
                if (class_exists('CPT_Events')) {
                    new CPT_Events();
                } else {
                    error_log("CraftedPath Toolkit Error: CPT_Events class not found.");
                }
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

    /**
     * Register block types on the init hook.
     */
    public function register_blocks()
    {
        // Get settings manager instance (needed if not already available)
        // If init_settings runs before this on plugins_loaded, it should be set.
        // Alternatively, access it directly: $settings_manager = CPT_Settings_Manager::instance();
        if (empty($this->settings_manager)) {
            $this->settings_manager = CPT_Settings_Manager::instance();
        }
        $settings_manager = $this->settings_manager;

        // Add registration for other blocks here in the future
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_frontend_assets()
    {
        if (empty($this->settings_manager)) {
            $this->settings_manager = CPT_Settings_Manager::instance();
        }

        if ($this->settings_manager && $this->settings_manager->is_feature_enabled('wireframe_mode')) {
            wp_enqueue_style(
                'cpt-wireframe-mode',
                CPT_PLUGIN_URL . 'assets/css/wireframe-mode.css',
                array(), // No dependencies for this override stylesheet
                CPT_VERSION
            );

            wp_enqueue_script(
                'cpt-wireframe-mode',
                CPT_PLUGIN_URL . 'assets/js/wireframe-mode.js',
                array(),
                CPT_VERSION,
                true
            );
        }
    }
}

// Initialize the plugin
function craftedpath_toolkit()
{
    return CraftedPath_Toolkit::instance();
}
craftedpath_toolkit();