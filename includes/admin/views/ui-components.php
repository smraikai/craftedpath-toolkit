<?php
/**
 * UI Components for CraftedPath Toolkit Admin
 *
 * @package CraftedPath_Toolkit
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Renders the standard header card.
 */
function cptk_render_header_card()
{
    ?>
    <!-- Header Card -->
    <div class="craftedpath-header-card">
        <div class="craftedpath-header-content">
            <div class="craftedpath-logo">
                <img src="https://craftedpath.co/wp-content/uploads/2025/02/logo.webp" alt="CraftedPath Logo">
            </div>
            <div class="craftedpath-version">v<?php echo esc_html(CPT_VERSION); ?></div>
        </div>
    </div>
    <?php
}

/**
 * Renders a standard card component.
 *
 * @param string $title The card title.
 * @param string $icon Dashicon class for the title icon (e.g., 'dashicons-admin-plugins').
 * @param callable $content_callback A function/method that echoes the card body content.
 * @param string $footer_content Optional HTML string for the card footer.
 */
function cptk_render_card($title, $icon, $content_callback, $footer_content = '')
{
    ?>
    <div class="craftedpath-card">
        <div class="craftedpath-card-header">
            <h2>
                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                <?php echo esc_html($title); ?>
            </h2>
            <?php
            // Potential placeholder for description - can be added as a parameter if needed 
            // echo '<p>Description goes here...</p>'; 
            ?>
        </div>
        <div class="craftedpath-card-body">
            <?php
            // Content can be either a callable or direct HTML content
            if (is_callable($content_callback)) {
                call_user_func($content_callback);
            } elseif (is_string($content_callback)) {
                echo $content_callback; // Direct HTML content
            } elseif (is_array($content_callback) && count($content_callback) == 2) {
                // This handles array($object, 'method_name') style callbacks
                call_user_func($content_callback);
            }
            ?>
        </div>
        <?php if (!empty($footer_content)): ?>
            <div class="craftedpath-card-footer">
                <?php echo $footer_content; // Already prepared HTML, no need for esc_html ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Helper function to create a hidden toast trigger that will be detected by JS
 * and converted to a toast notification.
 *
 * @param string $message The message to display in the toast.
 * @param string $type The type of toast ('success', 'error', 'warning', 'info').
 */
function cptk_create_toast_trigger($message = 'Settings saved.', $type = 'success')
{
    // Add a custom notice specifically for the toast trigger
    add_settings_error(
        'cpt_toast_trigger',      // Unique setting slug for our trigger
        'cpt_features_saved',     // Unique error code
        $message,                 // Message (will be used by JS but not displayed in the notice)
        'cpt-toast-notice ' . $type  // Custom CSS class type
    );

    // Output a hidden container for the notice
    echo '<div class="cpt-toast-trigger-area" style="display: none;">';
    settings_errors('cpt_toast_trigger');
    echo '</div>';

    // Add inline script to ensure the toast is displayed
    // This is a backup in case the element isn't found by our main script
    echo '<script>
    jQuery(function($) {
        if (typeof window.showCPTToast === "function") {
            console.log("Inline trigger calling showCPTToast with: ' . esc_js($message) . '");
            window.showCPTToast("' . esc_js($message) . '", "' . esc_js($type) . '");
        } else {
            console.error("Inline trigger: showCPTToast function not available");
        }
    });
    </script>';
}