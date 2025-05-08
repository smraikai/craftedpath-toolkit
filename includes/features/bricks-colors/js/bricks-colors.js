console.log('[BricksColors] bricks-colors.js File Loaded'); // Log 1: File loaded

(function ($) {
    'use strict';

    // --- Color Conversion Helpers ---

    function hexToRgb(hex) {
        let result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    function rgbToHex(r, g, b) {
        const toHex = (c) => ('0' + c.toString(16)).slice(-2);
        return `#${toHex(r)}${toHex(g)}${toHex(b)}`.toUpperCase();
    }

    function rgbToHsl(r, g, b) {
        r /= 255; g /= 255; b /= 255;
        let max = Math.max(r, g, b), min = Math.min(r, g, b);
        let h, s, l = (max + min) / 2;

        if (max == min) {
            h = s = 0; // achromatic
        } else {
            let d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch (max) {
                case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                case g: h = (b - r) / d + 2; break;
                case b: h = (r - g) / d + 4; break;
            }
            h /= 6;
        }
        // Return H, S, L as values between 0-100 (easier for adjustments)
        return { h: Math.round(h * 360), s: Math.round(s * 100), l: Math.round(l * 100) };
    }

    function hslToRgb(h, s, l) {
        h /= 360; s /= 100; l /= 100;
        let r, g, b;

        if (s == 0) {
            r = g = b = l; // achromatic
        } else {
            const hue2rgb = (p, q, t) => {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1 / 6) return p + (q - p) * 6 * t;
                if (t < 1 / 2) return q;
                if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
                return p;
            };
            let q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            let p = 2 * l - q;
            r = hue2rgb(p, q, h + 1 / 3);
            g = hue2rgb(p, q, h);
            b = hue2rgb(p, q, h - 1 / 3);
        }
        return {
            r: Math.round(r * 255),
            g: Math.round(g * 255),
            b: Math.round(b * 255)
        };
    }

    function hexToHsl(hex) {
        const rgb = hexToRgb(hex);
        return rgb ? rgbToHsl(rgb.r, rgb.g, rgb.b) : null;
    }

    function hslToHex(h, s, l) {
        const rgb = hslToRgb(h, s, l);
        return rgbToHex(rgb.r, rgb.g, rgb.b);
    }

    // --- End Color Conversion Helpers ---

    // Helper function to convert various color formats to a #RRGGBB hex string (Simplified now relies on above)
    function toStandardHex(colorString) {
        if (typeof colorString !== 'string') return null;
        if (/^#[0-9A-Fa-f]{6}$/i.test(colorString)) {
            return colorString.toUpperCase();
        }
        if (/^#[0-9A-Fa-f]{3}$/i.test(colorString)) {
            return (
                '#' +
                colorString[1] + colorString[1] +
                colorString[2] + colorString[2] +
                colorString[3] + colorString[3]
            ).toUpperCase();
        }
        // Corrected Regex for RGB/RGBA
        let match = colorString.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*[\d\.]+)?\s*\)$/i);
        if (match) {
            return rgbToHex(parseInt(match[1]), parseInt(match[2]), parseInt(match[3]));
        }
        // Could add HSL parsing here if needed
        console.warn('[BricksColors] toStandardHex: Could not parse color string:', colorString);
        return null;
    }

    /**
     * Darkens a HEX color using HSL adjustments for better perceptual results.
     * @param {string} hexColor The base color in HEX format.
     * @param {number} darkPercent How much to darken (e.g., 20 for 20%). Applied to Lightness.
     * @param {number} minLightness Minimum allowed Lightness (0-100), e.g., 10.
     * @returns {string|null} The darkened HEX color or null if input is invalid.
     */
    function darkenColorHSL(hexColor, darkPercent = 15, minLightness = 10) {
        const stdHex = toStandardHex(hexColor);
        if (!stdHex) {
            console.warn('darkenColorHSL: Invalid input hex', hexColor);
            return null;
        }
        const hsl = hexToHsl(stdHex);
        if (!hsl) {
            console.warn('darkenColorHSL: Could not convert hex to HSL', stdHex);
            return null; // Should not happen if stdHex is valid
        }

        let currentL = hsl.l;
        // Reduce lightness by a percentage of its current value
        let lightnessReduction = currentL * (darkPercent / 100);
        let newL = currentL - lightnessReduction;

        // Ensure lightness doesn't go below the minimum threshold
        newL = Math.max(minLightness, newL);

        // console.log(`Darkening: Base L=${currentL}, Reduction=${lightnessReduction}, New L=${newL}`);

        return hslToHex(hsl.h, hsl.s, Math.round(newL)); // Round newL
    }

    $(document).ready(function () {
        console.log('[BricksColors] Document Ready Fired'); // Log 2

        console.log('[BricksColors] Checking for wpColorPicker...'); // Log 3
        if (typeof $.wp !== 'undefined' && typeof $.wp.wpColorPicker !== 'undefined') {
            console.log('[BricksColors] wpColorPicker found. Initializing...'); // Log 4

            const $inputs = $('.cpt-bricks-color-input-field');
            console.log(`[BricksColors] Found ${$inputs.length} color input fields.`); // Log 5

            $inputs.each(function (index) {
                var $input = $(this);
                var $item = $input.closest('.cpt-bricks-color-item');
                var $valueText = $item.find('.cpt-bricks-color-value-text');
                var inputId = $input.attr('id');
                var variableName = $input.data('variable-name'); // Get the variable name

                // console.log(`[BricksColors] Initializing picker for: #${inputId}, Variable: ${variableName}`); // Log 6

                try {
                    $input.wpColorPicker({
                        palettes: true,
                        change: function (event, ui) {
                            var rawColorFromPicker = ui.color.toString();
                            // console.log(`[BricksColors] CHANGE event for ${variableName} (#${inputId}): Raw color = ${rawColorFromPicker}`); // Log 7

                            let displayColor = rawColorFromPicker;
                            let hexForCalculation = toStandardHex(rawColorFromPicker);

                            if (hexForCalculation) {
                                displayColor = hexForCalculation;
                                // console.log(`[BricksColors] ${variableName}: Converted to hex ${hexForCalculation}`); // Log 8
                            } else {
                                // console.warn(`[BricksColors] ${variableName}: Could not convert '${rawColorFromPicker}' to standard hex.`); // Log 9
                            }
                            $valueText.text(displayColor); // Update the text display for the changed input

                            // --- Auto-update hover color logic (using variable name) ---
                            if (variableName && !variableName.endsWith('-hover') && hexForCalculation) {
                                let hoverVariableName = variableName + '-hover';
                                // console.log(`[BricksColors] ${variableName}: Looking for hover variable '${hoverVariableName}'`); // Log 10

                                let $hoverInput = $('.cpt-bricks-color-input-field[data-variable-name="' + hoverVariableName + '"]');

                                if ($hoverInput.length) {
                                    let hoverInputId = $hoverInput.attr('id');
                                    // console.log(`[BricksColors] ${variableName}: Found hover input #${hoverInputId} for variable '${hoverVariableName}'`); // Log 11

                                    // Use the new HSL-based darkening function - INCREASED PERCENTAGE AGAIN
                                    let newHoverColor = darkenColorHSL(hexForCalculation, 40, 10); // Darken by 40%, min Lightness 10%

                                    if (newHoverColor) {
                                        // console.log(`[BricksColors] ${variableName}: Calculated new hover color ${newHoverColor}`); // Log 12
                                        $hoverInput.val(newHoverColor);
                                        $hoverInput.wpColorPicker('color', newHoverColor); // Programmatically update its picker

                                        let $hoverItem = $hoverInput.closest('.cpt-bricks-color-item');
                                        $hoverItem.find('.cpt-bricks-color-value-text').text(newHoverColor); // Update hover text display
                                        // console.log(`[BricksColors] ${variableName}: Updated hover input #${hoverInputId} to ${newHoverColor}`); // Log 13
                                    } else {
                                        // console.warn(`[BricksColors] ${variableName}: Could not calculate new hover color.`); // Log 14
                                    }
                                } else {
                                    // console.log(`[BricksColors] ${variableName}: Hover variable '${hoverVariableName}' not found.`); // Log 15
                                }
                            } else if (hexForCalculation === null) {
                                // console.warn(`[BricksColors] ${variableName}: Cannot calculate hover color because base color could not be converted to hex.`); // Log 16
                            }
                            // --- End Auto-update hover color logic ---
                        },
                        clear: function () {
                            // console.log(`[BricksColors] CLEAR event for ${variableName} (#${inputId})`); // Log 17
                            $valueText.text(''); // Clear text for the cleared input
                            if (variableName && !variableName.endsWith('-hover')) {
                                let hoverVariableName = variableName + '-hover';
                                let $hoverInput = $('.cpt-bricks-color-input-field[data-variable-name="' + hoverVariableName + '"]');
                                if ($hoverInput.length) {
                                    let hoverInputId = $hoverInput.attr('id');
                                    $hoverInput.val('');
                                    $hoverInput.wpColorPicker('color', ''); // Clear its picker
                                    let $hoverItem = $hoverInput.closest('.cpt-bricks-color-item');
                                    $hoverItem.find('.cpt-bricks-color-value-text').text(''); // Clear hover text display
                                    // console.log(`[BricksColors] Cleared hover input #${hoverInputId} for variable '${hoverVariableName}'`); // Log 18
                                }
                            }
                        }
                    });
                } catch (e) {
                    console.error(`[BricksColors] ERROR initializing wpColorPicker for #${inputId} (Var: ${variableName}):`, e); // Log 19
                }
            });
        } else {
            console.warn('[BricksColors] WordPress Color Picker function ($.wp.wpColorPicker) not found. UI will be limited.'); // Log 20
            // Fallback text display
            $('.cpt-bricks-color-input-field').each(function () {
                var $input = $(this);
                var $item = $input.closest('.cpt-bricks-color-item');
                var $valueText = $item.find('.cpt-bricks-color-value-text');
                $valueText.text($input.val());
            });
        }
        console.log('[BricksColors] Initialization complete.'); // Log 21
    });

})(jQuery); 