<?php
/**
 * BEM Class Generator Feature
 *
 * @package CraftedPath_Toolkit
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * BEM Class Generator class.
 */
class CPT_Bem_Generator
{

    /**
     * Initialize the feature.
     */
    public function __construct()
    {
        // Use wp_enqueue_scripts but check inside the function if it's Bricks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        // Keep the filter for potentially adding type="module" later
        add_filter('script_loader_tag', array($this, 'add_type_attribute'), 10, 3);
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

        $path = 'includes/features/bem-generator/js';
        $filename = 'bem-class-generator.min.js';
        $file_path = CPT_PLUGIN_DIR . "{$path}/{$filename}";
        $file_url = CPT_PLUGIN_URL . "{$path}/{$filename}";

        // Register and enqueue the script
        wp_register_script(
            'cpt-bem-class-generator',
            $file_url,
            array(),
            file_exists($file_path) ? filemtime($file_path) : CPT_VERSION,
            true
        );

        wp_enqueue_script('cpt-bem-class-generator');
    }

    /**
     * Check if the current context is the Bricks editor.
     *
     * @return bool
     */
    private function is_bricks_editor()
    {
        return function_exists('bricks_is_builder_main') && \bricks_is_builder_main();
    }

    /**
     * Adds type attribute to the script tag if needed
     *
     * @param string $tag The original script tag.
     * @param string $handle The script handle.
     * @param string $src The script source.
     * @return string
     */
    public function add_type_attribute($tag, $handle, $src)
    {
        if ('cpt-bem-class-generator' === $handle) {
            // Currently returning unmodified tag
            // Modify this if we need to add type="module" in the future
        }
        return $tag;
    }
}