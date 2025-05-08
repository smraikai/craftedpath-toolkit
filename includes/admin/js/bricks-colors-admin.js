/**
 * JavaScript for Bricks Colors admin page
 */
(function ($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function () {
        // Initialize color pickers for color values only
        $('.color-picker').each(function () {
            var $input = $(this);
            var isNonColor = $input.data('non-color') === true;

            // Only apply color picker to actual color values
            if (!isNonColor) {
                $input.wpColorPicker({
                    // Enable alpha channel
                    alphaEnabled: true,

                    // Show the color palette
                    palettes: true,

                    // Update the color swatch in real-time when changing colors
                    change: function (event, ui) {
                        var colorValue = ui.color.toString();
                        var $swatch = $input.closest('.cpt-color-item').find('.cpt-color-swatch');

                        // Update the swatch color
                        $swatch.css('background-color', colorValue);
                    }
                });
            }
        });

        // Update all the swatches on page load
        $('.cpt-color-item').each(function () {
            var $item = $(this);
            var isNonColor = $item.find('.color-picker').data('non-color') === true;

            if (!isNonColor) {
                var colorValue = $item.find('.color-picker').val();
                $item.find('.cpt-color-swatch').css('background-color', colorValue);
            }
        });

        // Make sure the color picker UI is properly sized
        $('.wp-picker-container').css('width', '100%');
    });

})(jQuery); 