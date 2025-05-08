(function ($) {
    'use strict';

    // Document ready function to ensure DOM is fully loaded
    $(document).ready(function () {
        // Add our color picker
        if (typeof $.wp !== 'undefined' && typeof $.wp.wpColorPicker !== 'undefined') {
            // Initialize color pickers with proper configuration
            $('.cpt-bricks-color-input').wpColorPicker({
                // Show a palette of common colors
                palettes: true,
                // Callback when color changes
                change: function (event, ui) {
                    var colorValue = ui.color.toString();
                    var $input = $(this);
                    var $swatch = $input.closest('.cpt-bricks-color-item').find('.cpt-bricks-color-swatch');
                    $swatch.css('background-color', colorValue);
                }
            });

            // Ensure the color picker container has proper width
            setTimeout(function () {
                $('.wp-picker-container').each(function () {
                    $(this).css('width', '100%');
                });
            }, 100);
        } else {
            // Fallback if color picker not available
            console.log('WordPress Color Picker not available');

            // Still update swatches with initial values
            $('.cpt-bricks-color-item').each(function () {
                var colorValue = $(this).find('.cpt-bricks-color-input').val();
                $(this).find('.cpt-bricks-color-swatch').css('background-color', colorValue);
            });
        }
    });
})(jQuery); 