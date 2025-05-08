(function ($) {
    'use strict';
    $(document).ready(function () {
        $('.cpt-bricks-color-input').wpColorPicker({
            alphaEnabled: true,
            palettes: true,
            change: function (event, ui) {
                var colorValue = ui.color.toString();
                var $input = $(this);
                var $swatch = $input.closest('.cpt-bricks-color-item').find('.cpt-bricks-color-swatch');
                $swatch.css('background-color', colorValue);
            }
        });
        $('.cpt-bricks-color-item').each(function () {
            var colorValue = $(this).find('.cpt-bricks-color-input').val();
            $(this).find('.cpt-bricks-color-swatch').css('background-color', colorValue);
        });
        $('.wp-picker-container').css('width', '100%');
    });
})(jQuery); 