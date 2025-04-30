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
        add_action('admin_init', array($this, 'maybe_redirect_after_save'));

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
            array($this, 'render_settings_page'),
            'dashicons-admin-tools',
            100
        );

        // Removed the submenu under Settings to avoid duplicate pages
    }

    /**
     * Register plugin settings
     */
    public function register_settings()
    {
        register_setting(
            'craftedpath_toolkit_settings',
            self::OPTION_NAME,
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array('features' => array()),
            )
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        if ('toplevel_page_craftedpath-toolkit' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'craftedpath-toolkit-admin',
            CPT_PLUGIN_URL . 'includes/admin/css/settings.css',
            array(),
            CPT_VERSION
        );

        wp_enqueue_script(
            'craftedpath-toolkit-admin-js',
            CPT_PLUGIN_URL . 'includes/admin/js/settings.js',
            array('jquery'),
            CPT_VERSION,
            true
        );
    }

    /**
     * Render the settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if settings were saved
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            add_settings_error(
                'craftedpath_toolkit_messages',
                'craftedpath_toolkit_message',
                __('Settings Saved', 'craftedpath-toolkit'),
                'updated'
            );
        }

        // Show error/update messages
        settings_errors('craftedpath_toolkit_messages');
        ?>
        <div class="wrap craftedpath-settings">
            <!-- Header Card -->
            <div class="craftedpath-header-card">
                <div class="craftedpath-header-content">
                    <div class="craftedpath-logo">
                        <img src="https://craftedpath.co/wp-content/uploads/2025/02/logo.webp">
                    </div>
                    <div class="craftedpath-version">v<?php echo CPT_VERSION; ?></div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="craftedpath-content">
                <?php $this->render_features_section(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the features section content
     */
    private function render_features_section()
    {
        ?>
        <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="post">
            <?php settings_fields('craftedpath_toolkit_settings'); ?>

            <div class="craftedpath-card">
                <div class="craftedpath-card-header">
                    <h2>
                        <span class="dashicons dashicons-admin-plugins"></span>
                        Available Features
                    </h2>
                    <p>Enable or disable CraftedPath Toolkit features below.</p>
                </div>
                <div class="craftedpath-card-body">
                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php
                            foreach ($this->features as $feature_id => $feature) {
                                $this->render_feature_row($feature_id, $feature);
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="craftedpath-card-footer">
                    <?php submit_button('Save Changes', 'primary', 'submit', false); ?>
                </div>
            </div>
        </form>
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
            ? $options['features'][$feature_id]
            : $feature['default'];
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
     * Render individual feature toggle field
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
            ? $options['features'][$feature_id]
            : $feature['default'];

        // Debug the current state
        error_log("Feature {$feature_id} enabled state: " . ($enabled ? 'true' : 'false'));
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
     * Sanitize settings before saving
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();
        $sanitized['features'] = array();

        // Debug the input
        error_log('Input received: ' . print_r($input, true));

        // Process all registered features
        foreach ($this->features as $feature_id => $feature) {
            // Check if the feature was submitted in the form
            $is_enabled = isset($input['features'][$feature_id]) && $input['features'][$feature_id] === '1';
            $sanitized['features'][$feature_id] = $is_enabled;

            // Debug each feature's state
            error_log("Processing feature {$feature_id}: " . ($is_enabled ? 'enabled' : 'disabled'));
        }

        // Debug the final sanitized settings
        error_log('Final sanitized settings: ' . print_r($sanitized, true));

        // Set the redirect URL
        $this->redirect_to = add_query_arg(
            array(
                'page' => 'craftedpath-toolkit',
                'settings-updated' => 'true'
            ),
            admin_url('admin.php')
        );

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
        return isset($options['features'][$feature_id])
            ? $options['features'][$feature_id]
            : $this->features[$feature_id]['default'];
    }

    /**
     * Handle redirection after saving settings
     */
    public function maybe_redirect_after_save()
    {
        if (!is_null($this->redirect_to)) {
            wp_safe_redirect($this->redirect_to);
            exit;
        }
    }
}