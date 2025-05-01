<?php
/**
 * Class CPT_Admin_Refresh_UI
 *
 * Handles the admin UI refresh feature based on the Admin Redesign Exploration plugin.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

final class CPT_Admin_Refresh_UI
{
	private static $instance = null;
	private $dist_path;
	private $dist_url;

	public static function instance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		// Define paths relative to the main plugin file
		$this->dist_path = CPT_PLUGIN_DIR . 'includes/features/admin-refresh-ui/dist/';
		$this->dist_url = CPT_PLUGIN_URL . 'includes/features/admin-refresh-ui/dist/';

		$this->init_hooks();
	}

	private function init_hooks()
	{
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('admin_head', array($this, 'add_admin_head_styles'));
		add_filter('admin_body_class', array($this, 'remove_auto_fold_class'));
	}

	/**
	 * Enqueue the admin redesign styles and scripts.
	 */
	public function enqueue_assets()
	{
		$style_asset_file = $this->dist_path . 'css/admin-redesign.asset.php';
		$style_asset = file_exists($style_asset_file) ? require $style_asset_file : [];

		wp_enqueue_style(
			'cpt-admin-refresh-ui-style', // Use prefixed handle
			$this->dist_url . 'css/admin-redesign.css',
			$style_asset['dependencies'] ?? [],
			$style_asset['version'] ?? CPT_VERSION // Use main plugin version
		);

		$script_asset_file = $this->dist_path . 'js/admin-enhancements.asset.php';
		$script_asset = file_exists($script_asset_file) ? require $script_asset_file : [];

		wp_enqueue_script(
			'cpt-admin-refresh-ui-script', // Use prefixed handle
			$this->dist_url . 'js/admin-enhancements.js',
			$script_asset['dependencies'] ?? [],
			$script_asset['version'] ?? CPT_VERSION, // Use main plugin version
			[
				'strategy' => 'defer', // Use modern 'strategy' key
			]
		);
	}

	/**
	 * Add custom admin styles/scripts to the admin head.
	 */
	public function add_admin_head_styles()
	{
		?>
		<script id="cpt-admin-refresh-ui-initial-width">
			// Set the initial width of the sidebar
			const initialWidth = localStorage.getItem('cpt-admin-refresh-sidebar-width') || 300; // Prefixed local storage key
			document.documentElement.style.setProperty('--admin-redesign-sidebar-width', `${initialWidth}px`); // Keep original CSS var for now unless CSS is also refactored
		</script>
		<?php
	}


	/**
	 * Remove the auto-fold class from the admin body classes.
	 *
	 * @param string $classes The current body classes.
	 * @return string Modified body classes.
	 */
	public function remove_auto_fold_class($classes)
	{
		// Ensure it's a string before exploding
		if (!is_string($classes)) {
			return $classes;
		}

		$classes_array = explode(' ', $classes);
		$auto_fold_key = array_search('auto-fold', $classes_array, true);

		if (false !== $auto_fold_key) {
			unset($classes_array[$auto_fold_key]);
		}

		return implode(' ', $classes_array); // Use implode instead of join
	}
}

// Note: We don't instantiate here. Instantiation happens in the main plugin file based on settings.