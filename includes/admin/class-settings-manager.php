<?php
/**
 * Settings Manager for CraftedPath Toolkit
 *
 * @package CraftedPath_Toolkit
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Settings Manager class.
 */
class CPT_Settings_Manager
{
    /**
     * Singleton instance
     * @var CPT_Settings_Manager|null
     */
    private static $instance = null;

    /**
     * Option name in wp_options table
     */
    const OPTION_NAME = 'craftedpath_toolkit_settings';

    /**
     * Holds the plugin features
     *
     * @var array
     */
    private $features = array();

    /**
     * URL to redirect to after saving settings
     * 
     * @var string|null
     */
    private $redirect_to = null;

    /**
     * Get singleton instance
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the settings manager
     */
    public function __construct()
    {
        // Prevent direct instantiation (enforce singleton)
        if (self::$instance) {
            return;
        }
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Register default features
        $this->register_features();
    }

    /**
     * Register available features
     */
    private function register_features()
    {
        $this->features = array(
            'bem_generator' => array(
                'name' => 'BEM Class Generator',
                'description' => 'Generates BEM classes in the Bricks editor',
                'class' => 'CPT_Bem_Generator',
                'default' => true
            ),
            'ai_page_generator' => array(
                'name' => 'AI Page Structure Generator',
                'description' => 'Generates a hierarchical page structure (sitemap) using AI.',
                'class' => 'CPT_AI_Page_Generator',
                'default' => true // Enable by default?
            ),
            'ai_menu_generator' => array(
                'name' => 'AI Menu Generator',
                'description' => 'Generates a navigation menu structure using AI, optionally based on page structure.',
                'class' => 'CPT_AI_Menu_Generator',
                'default' => true // Enable by default?
            ),
            'admin_refresh_ui' => array(
                'name' => 'Admin Refresh UI',
                'description' => 'Applies experimental styling refresh to the WP Admin area.',
                'class' => 'CPT_Admin_Refresh_UI',
                'default' => false // Disabled by default as it's experimental
            ),
            // Add more features here as they are developed
        );
    }

    /**
     * Add settings page to WordPress admin
     */
    public function add_settings_page()
    {
        // Add main menu item
        add_menu_page(
            'CraftedPath Toolkit',
            'CraftedPath',
            'manage_options',
            'craftedpath-toolkit',
            array($this, 'render_settings_page'), // Callback for main page (Features)
            'dashicons-admin-tools',
            100
        );

        // Add Feature Settings Submenu (this acts as the main page now)
        add_submenu_page(
            'craftedpath-toolkit',          // Parent slug
            'Toolkit Features',             // Page title
            'Features',                     // Menu title
            'manage_options',
            'craftedpath-toolkit',          // Slug (same as parent to be the default)
            array($this, 'render_settings_page') // Callback
        );

        // Conditionally add AI Page Generator page
        if (class_exists('CPT_AI_Page_Generator')) {
            add_submenu_page(
                'craftedpath-toolkit',
                'AI Page Generator',
                'Page Generator',
                'manage_options',
                'cpt-aipg-pages',           // New slug for page generator UI
                array(CPT_AI_Page_Generator::instance(), 'render_page_generator_page')
            );
        }

        // Conditionally add AI Menu Generator page
        if (class_exists('CPT_AI_Menu_Generator')) {
            add_submenu_page(
                'craftedpath-toolkit',
                'AI Menu Generator',
                'Menu Generator',
                'manage_options',
                'cpt-aimg-menu',              // New slug for menu generator UI
                array(CPT_AI_Menu_Generator::instance(), 'render_menu_page')
            );
        }

        // Add General Toolkit Settings Submenu (OpenAI API Key, etc.)
        add_submenu_page(
            'craftedpath-toolkit',                  // Parent slug
            __('Toolkit Settings', 'craftedpath-toolkit'), // Page title
            __('Settings', 'craftedpath-toolkit'),        // Menu title
            'manage_options',                         // Capability required
            'cptk_settings_page',                     // Menu slug (from settings-page.php)
            'cptk_render_settings_page',              // Callback function (from settings-page.php)
            90                                        // Position (optional, place it after others)
        );
    }

    /**
     * Register plugin settings (Features only for now)
     */
    public function register_settings()
    {
        register_setting(
            'craftedpath_toolkit_settings', // Option group for features
            self::OPTION_NAME,             // Option name for features
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array('features' => array()),
            )
        );
        // Note: General settings (API keys etc.) are registered in settings-page.php
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        // Determine the current screen's base and ID
        $current_screen = get_current_screen();
        if (!$current_screen) {
            return;
        }
        $screen_id = $current_screen->id;

        // Define hooks for all toolkit pages based on screen ID patterns
        $toolkit_page_ids = [
            'toplevel_page_craftedpath-toolkit',       // Main Features page
            'craftedpath_page_cpt-aipg-pages',     // Added new Page Gen slug
            'craftedpath_page_cpt-aimg-menu',      // Added new Menu Gen slug
            'craftedpath_page_cptk_settings_page'   // General Settings submenu
        ];

        // Check if the current screen ID matches any toolkit page
        if (!in_array($screen_id, $toolkit_page_ids)) {
            // error_log("CPT Styles/Scripts not loaded for screen: " . $screen_id);
            return; // Exit if not a toolkit page
        }

        // Enqueue common styles and scripts on all toolkit pages
        wp_enqueue_style(
            'craftedpath-toolkit-admin',
            CPT_PLUGIN_URL . 'includes/admin/css/settings.css',
            array(),
            CPT_VERSION
        );
        wp_enqueue_script(
            'craftedpath-toolkit-admin-js',
            CPT_PLUGIN_URL . 'includes/admin/js/settings.js',
            array('jquery', 'cpt-toast-script'),
            CPT_VERSION,
            true
        );
    }

    // --- Reusable Rendering Components --- 

    /**
     * Renders the standard header card.
     * Made public to be callable from settings-page.php
     */
    public function render_header_card()
    {
        // Use the standalone component function
        cptk_render_header_card();
    }

    /**
     * Renders a standard card component.
     * Made public to be callable from settings-page.php
     *
     * @param string $title The card title.
     * @param string $icon Dashicon class for the title icon (e.g., 'dashicons-admin-plugins').
     * @param callable $content_callback A function/method that echoes the card body content.
     * @param string $footer_content Optional HTML string for the card footer.
     */
    public function render_card($title, $icon, $content_callback, $footer_content = '')
    {
        // Use the standalone component function
        cptk_render_card($title, $icon, $content_callback, $footer_content);
    }

    // --- Page Rendering Methods --- 

    /**
     * Render the main settings page (Features)
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Create toast trigger if settings were just saved (check both POST and GET)
        if (isset($_POST['submit-features'])) {
            // Form was just submitted
            cptk_create_toast_trigger('Settings saved successfully.', 'success');
        } else if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            // Redirected back after settings save
            cptk_create_toast_trigger('Settings saved successfully.', 'success');
        }
        ?>
        <div class="wrap craftedpath-settings">
            <?php $this->render_header_card(); // Use the reusable header ?>

            <!-- Content Area -->
            <div class="craftedpath-content">
                <?php $this->render_features_section(); // Render the features section (now uses render_card) ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the features section content using the reusable card component.
     */
    private function render_features_section()
    {
        // Prepare footer content (Submit button)
        ob_start();
        submit_button('Save Changes', 'primary', 'submit-features', false); // Unique name for the button
        $footer_html = ob_get_clean();

        // Render the card - directly call render_features_table method here for now
        echo '<form action="' . esc_url(admin_url('options.php')) . '" method="post">';
        settings_fields('craftedpath_toolkit_settings'); // Match the group used in register_settings for features

        // Start the card markup manually 
        ?>
        <div class="craftedpath-card">
            <div class="craftedpath-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php echo esc_html(__('Available Features', 'craftedpath-toolkit')); ?>
                </h2>
            </div>
            <div class="craftedpath-card-body">
                <?php $this->render_features_table(); ?>
            </div>
            <?php if (!empty($footer_html)): ?>
                <div class="craftedpath-card-footer">
                    <?php echo $footer_html; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php

        echo '</form>';
    }

    /**
     * Renders the table content for the features card.
     * This is used as the callback for render_card.
     */
    private function render_features_table()
    {
        // Description moved to the card title area or could be a separate element if needed
        // echo '<p>' . esc_html__('Enable or disable CraftedPath Toolkit features below.', 'craftedpath-toolkit') . '</p>';
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <?php
                foreach ($this->features as $feature_id => $feature) {
                    $this->render_feature_row($feature_id, $feature);
                }
                ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render a feature row
     */
    private function render_feature_row($feature_id, $feature)
    {
        $options = get_option(self::OPTION_NAME, array());

        // Ensure we have a features array
        if (!isset($options['features'])) {
            $options['features'] = array();
        }

        $enabled = isset($options['features'][$feature_id])
            ? (bool) $options['features'][$feature_id] // Cast to bool
            : (bool) $feature['default']; // Cast to bool
        ?>
        <tr>
            <th scope="row">
                <label for="feature-<?php echo esc_attr($feature_id); ?>">
                    <?php echo esc_html($feature['name']); ?>
                </label>
            </th>
            <td>
                <div class="craftedpath-toggle-field">
                    <label class="craftedpath-toggle">
                        <input type="checkbox" id="feature-<?php echo esc_attr($feature_id); ?>"
                            name="<?php echo esc_attr(self::OPTION_NAME); ?>[features][<?php echo esc_attr($feature_id); ?>]"
                            value="1" <?php checked($enabled, true); ?>>
                        <span class="craftedpath-toggle-slider"></span>
                    </label>
                    <span class="craftedpath-feature-description">
                        <?php echo esc_html($feature['description']); ?>
                    </span>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Render individual feature toggle field (Likely redundant now with render_feature_row)
     * Consider removing if not used elsewhere.
     */
    public function render_feature_field($args)
    {
        $feature_id = $args['feature_id'];
        $feature = $args['feature'];
        $options = get_option(self::OPTION_NAME, array());

        // Ensure we have a features array
        if (!isset($options['features'])) {
            $options['features'] = array();
        }

        $enabled = isset($options['features'][$feature_id])
            ? (bool) $options['features'][$feature_id] // Cast to bool
            : (bool) $feature['default']; // Cast to bool

        // Debug the current state - Removed for cleaner code, add back if needed
        // error_log("Feature {$feature_id} enabled state: " . ($enabled ? 'true' : 'false'));
        ?>
        <label>
            <input type="checkbox"
                name="<?php echo esc_attr(self::OPTION_NAME); ?>[features][<?php echo esc_attr($feature_id); ?>]" value="1"
                <?php checked($enabled, true); ?>>
            <?php echo esc_html($feature['description']); ?>
        </label>
        <?php
    }

    /**
     * Sanitize settings before saving (Only features handled here)
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();
        $sanitized['features'] = array();

        // Debug the input - Removed for cleaner code, add back if needed
        // error_log('Input received for feature settings: ' . print_r($input, true));

        // Process all registered features
        foreach ($this->features as $feature_id => $feature) {
            // Checkbox value is '1' if checked, not present otherwise.
            $is_enabled = isset($input['features'][$feature_id]) && $input['features'][$feature_id] === '1';
            $sanitized['features'][$feature_id] = $is_enabled;

            // Debug each feature's state - Removed for cleaner code
            // error_log("Sanitizing feature {$feature_id}: " . ($is_enabled ? 'enabled' : 'disabled'));
        }

        // Debug the final sanitized settings - Removed for cleaner code
        // error_log('Final sanitized feature settings: ' . print_r($sanitized, true));

        return $sanitized;
    }

    /**
     * Check if a feature is enabled
     */
    public function is_feature_enabled($feature_id)
    {
        if (!isset($this->features[$feature_id])) {
            return false;
        }

        $options = get_option(self::OPTION_NAME, array());
        // Ensure features key exists
        if (!isset($options['features'])) {
            $options['features'] = array();
        }
        // Check the specific feature, fallback to default
        return isset($options['features'][$feature_id])
            ? (bool) $options['features'][$feature_id] // Cast to bool
            : (bool) $this->features[$feature_id]['default']; // Cast to bool
    }
}