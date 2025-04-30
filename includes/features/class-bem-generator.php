<?php
/**
 * BEM Class Generator Feature for CraftedPath Toolkit
 *
 * @package CraftedPath_Toolkit
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CPT_Bem_Generator Class
 */
class CPT_Bem_Generator
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        // Note: The script_loader_tag filter from the original plugin wasn't doing anything,
        // so it's omitted here unless the JS actually needs type="module".
    }

    /**
     * Enqueue scripts for the BEM class generator feature.
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        // Only enqueue if we are in the Bricks editor environment
        if (!$this->is_bricks_editor()) {
            return;
        }

        // Add capability check if needed in the future
        // if ( ! current_user_can( 'edit_posts' ) ) { return; }

        $path = 'js'; // Relative path within this plugin
        $filename = 'cpt-bem-generator.js'; // Use the correct filename
        $handle = 'cpt-bem-generator';

        $file_path = CPT_PLUGIN_DIR . "{$path}/{$filename}"; // Used for versioning
        $file_url = CPT_PLUGIN_URL . "{$path}/{$filename}"; // Used for src

        wp_register_script(
            $handle,
            $file_url,
            array(), // Dependencies
            file_exists($file_path) ? filemtime($file_path) : CPT_VERSION, // Version
            true // In footer
        );

        wp_enqueue_script($handle);
    }

    /**
     * Check if the current context is the Bricks editor.
     *
     * @return bool
     */
    private function is_bricks_editor()
    {
        // Check if Bricks functions or classes exist, or if specific Bricks query vars are set
        // Using the same check as the original plugin.
        return function_exists('bricks_is_builder_main') && \bricks_is_builder_main();
    }

}