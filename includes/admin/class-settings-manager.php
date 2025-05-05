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
            // Section: Developer Tools
            'bem_generator' => array(
                'name' => 'BEM Class Generator',
                'description' => 'Generates BEM classes in the Bricks editor',
                'class' => 'CPT_Bem_Generator',
                'default' => true,
                'section' => 'Developer Tools'
            ),
            // Section: AI Tools
            'ai_page_generator' => array(
                'name' => 'Page Structure Generator',
                'description' => 'Generates a hierarchical page structure (sitemap) using AI.',
                'class' => 'CPT_AI_Page_Generator',
                'default' => true,
                'section' => 'AI Tools'
            ),
            'ai_menu_generator' => array(
                'name' => 'Menu Generator',
                'description' => 'Generates a navigation menu structure using AI, optionally based on page structure.',
                'class' => 'CPT_AI_Menu_Generator',
                'default' => true,
                'section' => 'AI Tools'
            ),
            'ai_auto_categorize' => array(
                'name' => 'Auto Categorize Posts',
                'description' => 'Adds a button to the post editor to automatically categorize posts using AI.',
                'class' => 'CPT_AI_Auto_Categorize',
                'default' => true,
                'section' => 'AI Tools'
            ),
            'ai_auto_tag' => array(
                'name' => 'Auto Tag Posts',
                'description' => 'Adds a button to the AI Tools sidebar to automatically tag posts using AI.',
                'class' => 'CPT_AI_Auto_Tag',
                'default' => true,
                'section' => 'AI Tools'
            ),
            'ai_alt_text' => array(
                'name' => 'Alt Text Generation',
                'description' => 'Generates alt text for images using AI.',
                'class' => 'CPT_AI_Alt_Text',
                'default' => false,
                'section' => 'AI Tools'
            ),
            'ai_content_generator' => array(
                'name' => 'AI Content Generator Block',
                'description' => 'Adds a block that uses AI to generate content (paragraphs, headings, etc.) based on a prompt.',
                'class' => null, // No specific PHP class needed for block registration itself
                'default' => false, // Default to disabled
                'section' => 'AI Tools'
            ),
            // Section: UI Enhancements
            'admin_quick_search' => array(
                'name' => 'Admin Quick Search',
                'description' => 'Adds a quick search bar (Cmd/Ctrl+K) to find and navigate admin menu items.',
                'class' => 'CPT_Admin_Quick_Search',
                'default' => true,
                'section' => 'UI Enhancements'
            ),
            'admin_refresh_ui' => array(
                'name' => 'Admin Refresh UI',
                'description' => 'Applies experimental styling refresh to the WP Admin area.',
                'class' => 'CPT_Admin_Refresh_UI',
                'default' => false,
                'section' => 'UI Enhancements'
            ),
            // Section: SEO Tools
            'seo_tools' => array(
                'name' => 'SEO Title & Meta',
                'description' => 'Adds SEO title and meta description fields to posts/pages and a global settings page.',
                'class' => null, // No specific class to instantiate for setup
                'default' => true, // Enable by default?
                'section' => 'SEO Tools' // New section
            ),
            // Section: Custom Post Types
            'cpt_testimonials' => array(
                'name' => 'Testimonials',
                'description' => 'Registers a "Testimonials" Custom Post Type.',
                'class' => 'CPT_Testimonials',
                'default' => false,
                'section' => 'Custom Post Types'
            ),
            'cpt_faqs' => array(
                'name' => 'FAQs',
                'description' => 'Registers a "FAQs" Custom Post Type.',
                'class' => 'CPT_FAQs',
                'default' => false,
                'section' => 'Custom Post Types'
            ),
            'cpt_staff' => array(
                'name' => 'Staff',
                'description' => 'Registers a "Staff" Custom Post Type with a Department taxonomy.',
                'class' => 'CPT_Staff',
                'default' => false,
                'section' => 'Custom Post Types'
            ),
            'cpt_events' => array(
                'name' => 'Events',
                'description' => 'Registers an "Events" Custom Post Type with an Event Category taxonomy.',
                'class' => 'CPT_Events',
                'default' => false,
                'section' => 'Custom Post Types'
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
            'dashicons-buddicons-activity', // Use the specified Dashicon class
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

        // Add SEO Settings Submenu
        if ($this->is_feature_enabled('seo_tools')) {
            add_submenu_page(
                'craftedpath-toolkit',          // Parent slug
                __('SEO', 'craftedpath-toolkit'), // Page title
                __('SEO', 'craftedpath-toolkit'), // Menu title
                'manage_options',
                'craftedpath-seo-settings',     // Menu slug
                '\CraftedPath\Toolkit\SEO\render_settings_page' // Callback
            );
        }

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

        // Add "Content" Top-Level menu if any CPT is active and Meta Box exists
        $this->add_content_parent_menu();
    }

    /**
     * Adds the parent "Content" menu item if conditions are met.
     */
    private function add_content_parent_menu()
    {
        // Check if Meta Box is active
        if (!function_exists('rwmb_meta')) {
            return;
        }

        // Check if at least one CPT feature is enabled
        $cpt_features = ['cpt_testimonials', 'cpt_faqs', 'cpt_staff', 'cpt_events'];
        $any_cpt_enabled = false;
        foreach ($cpt_features as $feature_id) {
            if ($this->is_feature_enabled($feature_id)) {
                $any_cpt_enabled = true;
                break;
            }
        }

        if (!$any_cpt_enabled) {
            return;
        }

        // Add the top-level menu page
        add_menu_page(
            __('Content', 'craftedpath-toolkit'),     // Page title
            __('Content', 'craftedpath-toolkit'),     // Menu title
            'edit_posts',                         // Capability (allow editors etc. to see CPTs)
            'cptk-content-menu',                  // Menu slug (used in show_in_menu for CPTs)
            array($this, 'render_content_parent_page'), // Callback function for the parent page
            'dashicons-archive',                  // Icon
            26                                    // Position (after Comments)
        );

        // Taxonomy submenus are now handled via links on the CPT list tables
        /*
        // Manually add taxonomy submenus if their corresponding CPT is active
        if ($this->is_feature_enabled('cpt_faqs')) {
            add_submenu_page(
                'cptk-content-menu',
                __('FAQ Categories', 'craftedpath-toolkit'),
                __('FAQ Categories', 'craftedpath-toolkit'),
                'manage_categories', // Standard capability for categories
                'edit-tags.php?taxonomy=faq_category'
                // No callback needed, WordPress handles edit-tags.php
            );
        }

        if ($this->is_feature_enabled('cpt_staff')) {
            add_submenu_page(
                'cptk-content-menu',
                __('Departments', 'craftedpath-toolkit'),
                __('Departments', 'craftedpath-toolkit'),
                'manage_categories', // Standard capability
                'edit-tags.php?taxonomy=department'
            );
        }

        if ($this->is_feature_enabled('cpt_events')) {
            add_submenu_page(
                'cptk-content-menu',
                __('Event Categories', 'craftedpath-toolkit'),
                __('Event Categories', 'craftedpath-toolkit'),
                'manage_categories', // Standard capability
                'edit-tags.php?taxonomy=event_category'
            );
        }
        */
    }

    /**
     * Render a placeholder page for the "Content" parent menu.
     * WordPress automatically redirects to the first submenu item,
     * so this callback might not be strictly necessary unless all
     * submenu items are hidden due to capabilities.
     */
    public function render_content_parent_page()
    {
        // This page likely won't be directly visible as WP redirects to the first submenu.
        // We can add a placeholder if needed.
        echo '<div class="wrap"><h1>' . esc_html__('Custom Content', 'craftedpath-toolkit') . '</h1><p>' . esc_html__('Select a content type from the submenu.', 'craftedpath-toolkit') . '</p></div>';
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
            'craftedpath_page_cptk_settings_page', // General Settings submenu
            'craftedpath_page_craftedpath-seo-settings' // <<< Added SEO Settings page hook
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

        // Enqueue Floating UI (Popper Core and Middleware)
        wp_enqueue_script(
            'floating-ui-core',
            'https://unpkg.com/@floating-ui/core@1/dist/floating-ui.core.umd.js',
            array(),
            '1', // Version number or null
            true
        );
        wp_enqueue_script(
            'floating-ui-dom',
            'https://unpkg.com/@floating-ui/dom@1/dist/floating-ui.dom.umd.js',
            array('floating-ui-core'),
            '1', // Version number or null
            true
        );

        // Enqueue our custom settings script
        wp_enqueue_script(
            'craftedpath-toolkit-admin-js',
            CPT_PLUGIN_URL . 'includes/admin/js/settings.js',
            array('jquery', 'cpt-toast-script'), // Ensure jquery/toast is loaded first if needed
            CPT_VERSION,
            true
        );

        // Enqueue our new tooltip script, dependent on Floating UI
        wp_enqueue_script(
            'craftedpath-toolkit-tooltip-js',
            CPT_PLUGIN_URL . 'includes/admin/js/tooltips.js',
            array('floating-ui-dom'), // Depends on floating-ui-dom
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
     * @param string $icon HTML string (e.g., SVG or <i> tag with Iconoir class) or a CSS class name (legacy for Dashicons).
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
        submit_button(__('Save Changes', 'craftedpath-toolkit'), 'primary', 'submit-features', false); // Unique name for the button
        $footer_html = ob_get_clean();

        // Render the card - directly call render_features_table method here for now
        echo '<form action="' . esc_url(admin_url('options.php')) . '" method="post">';
        settings_fields('craftedpath_toolkit_settings'); // Match the group used in register_settings for features

        // Start the card markup manually
        ?>
        <div class="craftedpath-card">
            <div class="craftedpath-card-header">
                <h2>
                    <?php
                    // Use Iconoir icon
                    echo '<i class="iconoir-settings" style="vertical-align: text-bottom; margin-right: 5px;"></i>';
                    ?>
                    <?php echo esc_html(__('Available Features', 'craftedpath-toolkit')); ?>
                </h2>
                <div class="cpt-accordion-controls">
                    <button type="button" class="button button-secondary button-small cpt-expand-all">
                        <i class="iconoir-expand"></i> <?php esc_html_e('Expand All', 'craftedpath-toolkit'); ?>
                    </button>
                    <button type="button" class="button button-secondary button-small cpt-collapse-all">
                        <i class="iconoir-collapse"></i> <?php esc_html_e('Collapse All', 'craftedpath-toolkit'); ?>
                    </button>
                </div>
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
        // Group features by section
        $grouped_features = array();
        foreach ($this->features as $feature_id => $feature) {
            $section = isset($feature['section']) ? $feature['section'] : __('Other', 'craftedpath-toolkit');
            $grouped_features[$section][$feature_id] = $feature;
        }

        // Define section order
        $section_order = [
            __('Developer Tools', 'craftedpath-toolkit'),
            __('AI Tools', 'craftedpath-toolkit'),
            __('UI Enhancements', 'craftedpath-toolkit'),
            __('SEO Tools', 'craftedpath-toolkit'),
            __('Custom Post Types', 'craftedpath-toolkit'),
            __('Other', 'craftedpath-toolkit')
        ];

        ?>
        <div class="cpt-features-accordion">
            <?php
            // $first_section = true; // Removed, all open by default now
            foreach ($section_order as $section_name) {
                if (!isset($grouped_features[$section_name])) {
                    continue; // Skip sections with no features
                }

                // Sanitize section name for use in IDs/attributes if needed
                $section_slug = sanitize_title($section_name);
                $is_open = true; // Open ALL sections by default
                ?>
                <div class="cpt-feature-accordion-section <?php echo $is_open ? 'is-open' : ''; ?>">
                    <h3 class="cpt-feature-accordion-header" id="accordion-header-<?php echo esc_attr($section_slug); ?>"
                        aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
                        aria-controls="accordion-content-<?php echo esc_attr($section_slug); ?>">
                        <button type="button" class="cpt-feature-accordion-trigger">
                            <span class="cpt-feature-accordion-title"><?php echo esc_html($section_name); ?></span>
                            <span class="cpt-feature-accordion-icon iconoir-nav-arrow-down"></span> <!-- Use Iconoir class -->
                        </button>
                    </h3>
                    <div class="cpt-feature-accordion-content" role="region"
                        aria-labelledby="accordion-header-<?php echo esc_attr($section_slug); ?>"
                        id="accordion-content-<?php echo esc_attr($section_slug); ?>" <?php echo !$is_open ? 'hidden' : ''; ?>>
                        <div class="cpt-features-list">
                            <?php
                            foreach ($grouped_features[$section_name] as $feature_id => $feature) {
                                $this->render_feature_row($feature_id, $feature);
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php
                unset($grouped_features[$section_name]); // Remove processed section
                // $first_section = false; // Removed
            }

            // Render any remaining sections (like 'Other') not in the defined order
            foreach ($grouped_features as $section_name => $features_in_section) {
                $section_slug = sanitize_title($section_name);
                $is_open = true; // Open ALL sections by default
                ?>
                <div class="cpt-feature-accordion-section <?php echo $is_open ? 'is-open' : ''; ?>">
                    <h3 class="cpt-feature-accordion-header" id="accordion-header-<?php echo esc_attr($section_slug); ?>"
                        aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
                        aria-controls="accordion-content-<?php echo esc_attr($section_slug); ?>">
                        <button type="button" class="cpt-feature-accordion-trigger">
                            <span class="cpt-feature-accordion-title"><?php echo esc_html($section_name); ?></span>
                            <span class="cpt-feature-accordion-icon iconoir-nav-arrow-down"></span> <!-- Use Iconoir class -->
                        </button>
                    </h3>
                    <div class="cpt-feature-accordion-content" role="region"
                        aria-labelledby="accordion-header-<?php echo esc_attr($section_slug); ?>"
                        id="accordion-content-<?php echo esc_attr($section_slug); ?>" <?php echo !$is_open ? 'hidden' : ''; ?>>
                        <div class="cpt-features-list">
                            <?php
                            foreach ($features_in_section as $feature_id => $feature) {
                                $this->render_feature_row($feature_id, $feature);
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
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

        // Check if Meta Box is required and inactive for CPTs
        $is_cpt_feature = isset($feature['section']) && $feature['section'] === 'Custom Post Types';
        $is_metabox_active = function_exists('rwmb_meta');
        $disable_toggle = $is_cpt_feature && !$is_metabox_active;
        $tooltip_content = $disable_toggle ? 'Requires Metabox to be active and installed' : '';

        ?>
        <?php // Use a structure inspired by Material UI ListItem ?>
        <div class="cpt-list-item <?php echo $disable_toggle ? 'is-disabled' : ''; ?>">
            <div class="cpt-feature-info"> <?php // Represents ListItemText ?>
                <span class="cpt-list-item-primary">
                    <?php // Use span for primary text (label) ?>
                    <?php echo esc_html($feature['name']); ?>
                </span>
                <span class="cpt-list-item-secondary">
                    <?php // Use span for secondary text (description) ?>
                    <?php echo esc_html($feature['description']); ?>
                </span>
            </div>
            <div class="craftedpath-toggle-field" <?php echo $disable_toggle ? 'data-tooltip-content="' . esc_attr($tooltip_content) . '"' : ''; ?>> <?php // Represents SecondaryAction ?>
                <?php // Keep existing toggle structure inside ?>
                <label class="craftedpath-toggle">
                    <input type="checkbox" id="feature-<?php echo esc_attr($feature_id); ?>"
                        name="<?php echo esc_attr(self::OPTION_NAME); ?>[features][<?php echo esc_attr($feature_id); ?>]"
                        value="1" <?php checked($enabled, true); ?>         <?php disabled($disable_toggle, true); ?>>
                    <span class="craftedpath-toggle-slider"></span>
                </label>
            </div>
        </div>
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
        $is_metabox_active = function_exists('rwmb_meta'); // Check once
        $old_options = get_option(self::OPTION_NAME, array('features' => array())); // Get old settings
        $flush_needed = false; // Flag to track if flush is needed
        $cpt_feature_keys = ['cpt_testimonials', 'cpt_faqs', 'cpt_staff', 'cpt_events']; // Keys for CPT features

        // Debug the input - Removed for cleaner code, add back if needed
        // error_log('Input received for feature settings: ' . print_r($input, true));

        // Process all registered features
        foreach ($this->features as $feature_id => $feature) {
            // Determine old state
            $old_enabled = isset($old_options['features'][$feature_id])
                ? (bool) $old_options['features'][$feature_id]
                : (bool) $feature['default'];

            // Determine new state from input
            $new_enabled = isset($input['features'][$feature_id]) && $input['features'][$feature_id] === '1';

            // If it's a CPT feature and Meta Box is NOT active, force disable it
            $is_cpt_feature = isset($feature['section']) && $feature['section'] === 'Custom Post Types';
            if ($is_cpt_feature && !$is_metabox_active) {
                $new_enabled = false;
            }

            // Store the sanitized new state
            $sanitized['features'][$feature_id] = $new_enabled;

            // Check if a CPT feature's state changed
            if (in_array($feature_id, $cpt_feature_keys) && $new_enabled !== $old_enabled) {
                $flush_needed = true;
                // error_log("Flush needed because {$feature_id} changed state."); // Debugging
            }

            // Debug each feature's state - Removed for cleaner code
            // error_log("Sanitizing feature {$feature_id}: " . ($new_enabled ? 'enabled' : 'disabled'));
        }

        // Debug the final sanitized settings - Removed for cleaner code
        // error_log('Final sanitized feature settings: ' . print_r($sanitized, true));

        // Flush rewrite rules if a CPT status changed
        if ($flush_needed) {
            // error_log('Flushing rewrite rules due to CPT status change.'); // Debugging
            flush_rewrite_rules();
        }

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