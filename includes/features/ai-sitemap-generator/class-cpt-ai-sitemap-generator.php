<?php
/**
 * AI Sitemap Generator Feature for CraftedPath Toolkit - [DEPRECATED - Functionality moved]
 *
 * This class is pending removal or refactoring. Functionality has been split into:
 * - CPT_AI_Page_Generator
 * - CPT_AI_Menu_Generator
 *
 * @package CraftedPath_Toolkit
 * @deprecated
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CPT_AI_Sitemap_Generator Class [DEPRECATED]
 * @deprecated Use CPT_AI_Page_Generator and CPT_AI_Menu_Generator instead.
 */
class CPT_AI_Sitemap_Generator
{

    /**
     * Singleton instance
     * @var CPT_AI_Sitemap_Generator|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     * @deprecated
     */
    public static function instance()
    {
        // Optionally trigger a deprecated notice
        _deprecated_function(__METHOD__, '1.X.X', 'CPT_AI_Page_Generator::instance() or CPT_AI_Menu_Generator::instance()');

        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     * @deprecated
     */
    private function __construct()
    {
        // Maybe add a hook to display an admin notice about the deprecation?
        // add_action( 'admin_notices', array( $this, 'show_deprecation_notice' ) );
    }

    /*
     * All functional methods have been moved to:
     * - includes/features/ai-page-generator/class-cpt-ai-page-generator.php
     * - includes/features/ai-menu-generator/class-cpt-ai-menu-generator.php
     */

    // public function show_deprecation_notice() {
    //     // Basic example notice
    //     echo '<div class="notice notice-warning is-dismissible"><p>';
    //     _e( 'The AI Sitemap Generator feature has been split into AI Page Generator and AI Menu Generator. The old class is deprecated and will be removed.', 'craftedpath-toolkit' );
    //     echo '</p></div>';
    // }

}