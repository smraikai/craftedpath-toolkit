<?php
/**
 * Social Share Image Generator for CraftedPath Toolkit.
 *
 * @package CraftedPath\Toolkit
 */

namespace CraftedPath\Toolkit\SEO\SocialImage;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the upload directory for SEO images.
 * 
 * @return array|false Array with 'path' and 'url' keys, or false on failure.
 */
function get_seo_upload_dir()
{
    $upload_dir = wp_upload_dir();
    $seo_dir = 'seo';

    // Create the directory if it doesn't exist
    $seo_path = $upload_dir['basedir'] . '/' . $seo_dir;
    if (!file_exists($seo_path)) {
        if (!wp_mkdir_p($seo_path)) {
            error_log('Failed to create SEO upload directory: ' . $seo_path);
            return false;
        }
    }

    // Check if directory is writable
    if (!is_writable($seo_path)) {
        error_log('SEO upload directory is not writable: ' . $seo_path);
        return false;
    }

    return array(
        'path' => $seo_path,
        'url' => $upload_dir['baseurl'] . '/' . $seo_dir
    );
}

/**
 * Get color value from preset key or return hex.
 * 
 * @param string $key Color key (primary, black, white, alt, hover) or hex color.
 * @return string Hex color value.
 */
function get_color_value($key)
{
    // Check if it's already a hex color
    if (preg_match('/^#[a-f0-9]{6}$/i', $key)) {
        return $key;
    }

    switch ($key) {
        case 'primary':
            return '#f55f4b'; // --primary
        case 'black':
            return '#1f2937'; // --dark
        case 'white':
            return '#ffffff'; // --white
        case 'alt':
            return '#6b7280'; // --secondary
        case 'hover':
            return '#e14b37'; // --primary-hover
        default:
            return '#f55f4b'; // Default to primary
    }
}

/**
 * Get the path to the default font file.
 * 
 * @return string Font file path.
 */
function get_font_path()
{
    // Assumes this file is in includes/features/seo/
    return plugin_dir_path(dirname(__FILE__, 3)) . 'assets/fonts/OpenSans-Bold.ttf';
}

/**
 * Wrap text to fit within a given width.
 * 
 * @param float $fontSize The font size.
 * @param float $angle The angle.
 * @param string $fontFile The font file path.
 * @param string $text The text string.
 * @param float $maxWidth The maximum width.
 * @return array Lines of text.
 */
function wrap_text($fontSize, $angle, $fontFile, $text, $maxWidth)
{
    $words = explode(' ', $text);
    $lines = [];
    $currentLine = '';

    foreach ($words as $word) {
        $testLine = $currentLine . ($currentLine ? ' ' : '') . $word;
        $testBox = @imagettfbbox($fontSize, $angle, $fontFile, $testLine);
        // Check if imagettfbbox returned false
        if (!$testBox) {
            error_log("Error calculating text box for: {$testLine}");
            // Handle error: maybe skip this word or line?
            continue;
        }
        $testWidth = abs($testBox[4] - $testBox[0]);

        if ($testWidth <= $maxWidth) {
            $currentLine = $testLine;
        } else {
            if ($currentLine) {
                $lines[] = $currentLine;
            }
            $currentLine = $word;
            // Handle case where a single word is too long
            $wordBox = @imagettfbbox($fontSize, $angle, $fontFile, $word);
            if (!$wordBox) {
                error_log("Error calculating word box for: {$word}");
                continue;
            }
            if (abs($wordBox[4] - $wordBox[0]) > $maxWidth) {
                // Simple truncation for very long words (can be improved)
                while (abs($wordBox[4] - $wordBox[0]) > $maxWidth && mb_strlen($word) > 0) {
                    $word = mb_substr($word, 0, -1);
                    $wordBox = @imagettfbbox($fontSize, $angle, $fontFile, $word . '…');
                    if (!$wordBox) {
                        error_log("Error calculating truncated word box for: {$word}");
                        break; // Exit loop if error persists
                    }
                }
                $currentLine = $word . '…';
            }
        }
    }
    $lines[] = $currentLine;

    return $lines;
}

/**
 * Calculate a hash based on relevant image generation settings.
 *
 * @param array $settings The settings array.
 * @param string|null $title Optional title.
 * @return string The MD5 hash.
 */
function calculate_settings_hash($settings, $title = null)
{
    $options = !empty($settings) ? $settings : get_option('craftedpath_seo_settings', []);
    $default_site_name = get_bloginfo('name');

    // Extract relevant settings for hash calculation
    $hash_data = [
        'bg_color' => $options['social_image_bg_color'] ?? '#ffffff',
        'bg_opacity' => isset($options['social_image_bg_opacity']) ? intval($options['social_image_bg_opacity']) : 100,
        'bg_image_id' => $options['social_image_bg_image_id'] ?? 0,
        'logo_id' => $options['social_share_logo_id'] ?? 0,
        'site_name' => !empty($options['site_name']) ? $options['site_name'] : $default_site_name,
    ];
    return md5(json_encode($hash_data));
}

/**
 * Main function to generate the social share image based on settings.
 *
 * @param array $settings SEO settings array.
 * @param string $title Optional title override (e.g., for post-specific previews).
 * @param string $type Type of image to generate ('base' or 'preview').
 * @param string|null $stored_hash The hash of the currently saved base image (only relevant for type 'base').
 * @return array|string|false \{
 *     For type 'base': Array ['url' => string, 'hash' => string] on success, false on failure.
 *     For type 'preview': string URL on success, false on failure.
 * }
 */
function generate_image($settings = [], $title = null, $type = 'base', $stored_hash = null)
{
    $options = !empty($settings) ? $settings : get_option('craftedpath_seo_settings', []);

    // --- Calculate Hash --- 
    $current_settings_hash = calculate_settings_hash($options, $title);

    // --- Determine Filename & Path --- 
    $seo_dir = get_seo_upload_dir();
    if (!$seo_dir) {
        error_log('Social Image Generator: Failed to get SEO upload directory.');
        return false;
    }

    $filename = ($type === 'preview') ? 'social_share_preview.jpg' : 'social_share.jpg';
    $file_path = $seo_dir['path'] . '/' . $filename;
    $file_url = $seo_dir['url'] . '/' . $filename;
    if (is_ssl()) {
        $file_url = str_replace('http://', 'https://', $file_url);
    }

    // --- Caching Logic (for 'base' type only) ---
    if ($type === 'base') {
        // If the stored hash matches the current hash AND the file exists, no need to regenerate.
        if ($stored_hash !== null && $stored_hash === $current_settings_hash && file_exists($file_path)) {
            // Return existing URL and the matching hash
            // Even though we didn't generate, return the structure expected on success
            return ['url' => $file_url, 'hash' => $current_settings_hash];
        }
        // Otherwise, proceed to generate (overwrite existing or create new)
    }
    // For 'preview' type, we always regenerate.

    // --- Start Generation --- 

    // Defaults & Settings Extraction (moved after hash calculation)
    $width = 1200;
    $height = 630;
    $padding = 80;
    $default_site_name = get_bloginfo('name');
    $site_name = !empty($options['site_name']) ? $options['site_name'] : $default_site_name;
    $bg_color = $options['social_image_bg_color'] ?? '#ffffff';
    $bg_opacity = isset($options['social_image_bg_opacity']) ? intval($options['social_image_bg_opacity']) : 100;
    $bg_image_id = $options['social_image_bg_image_id'] ?? 0;
    $logo_id = $options['social_share_logo_id'] ?? 0;

    // Get font path
    $font_path = get_font_path();
    if (!file_exists($font_path)) {
        error_log('Social Image Generator: Font file not found at ' . $font_path);
        return false;
    }

    // Get logo URL and resource
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
    $logo_img_resource = null;
    if ($logo_url) {
        $logo_data = @file_get_contents($logo_url);
        if ($logo_data) {
            $logo_img_resource = @imagecreatefromstring($logo_data);
            if (!$logo_img_resource) {
                error_log('Social Image Generator: Failed to create logo image resource from URL: ' . $logo_url);
            }
        } else {
            error_log('Social Image Generator: Failed to get logo data from URL: ' . $logo_url);
        }
    }

    // Get Background Image URL
    $bg_image_url = $bg_image_id ? wp_get_attachment_image_url($bg_image_id, 'full') : '';

    // --- Create Image --- 
    $image = imagecreatetruecolor($width, $height);
    if (!$image) {
        error_log('Social Image Generator: Failed to create true color image.');
        if ($logo_img_resource)
            imagedestroy($logo_img_resource);
        return false;
    }

    // Allocate background color
    list($bg_r, $bg_g, $bg_b) = sscanf($bg_color, "#%02x%02x%02x");
    $bg_alloc = imagecolorallocate($image, $bg_r, $bg_g, $bg_b);
    if ($bg_alloc === false) {
        error_log('Social Image Generator: Failed to allocate background color: ' . $bg_color);
        imagedestroy($image);
        if ($logo_img_resource)
            imagedestroy($logo_img_resource);
        return false;
    }
    imagefill($image, 0, 0, $bg_alloc);

    // --- Draw Background Image --- 
    if ($bg_image_url) {
        $bg_image_data = @file_get_contents($bg_image_url);
        if ($bg_image_data) {
            $bg_img_resource = @imagecreatefromstring($bg_image_data);
            if ($bg_img_resource) {
                // Calculate dimensions for cover sizing
                $canvas_ratio = $width / $height;
                // Prevent division by zero if image height is 0
                $bg_img_h = imagesy($bg_img_resource);
                if ($bg_img_h == 0) {
                    error_log('Social Image Generator: Background image height is zero. URL: ' . $bg_image_url);
                    imagedestroy($bg_img_resource); // Clean up resource
                } else {
                    $img_ratio = imagesx($bg_img_resource) / $bg_img_h;

                    if ($img_ratio > $canvas_ratio) {
                        $draw_height = $height;
                        $draw_width = $draw_height * $img_ratio;
                        $draw_x = ($width - $draw_width) / 2;
                        $draw_y = 0;
                    } else {
                        $draw_width = $width;
                        $draw_height = $draw_width / $img_ratio;
                        $draw_x = 0;
                        $draw_y = ($height - $draw_height) / 2;
                    }

                    imagecopyresampled($image, $bg_img_resource, $draw_x, $draw_y, 0, 0, $draw_width, $draw_height, imagesx($bg_img_resource), imagesy($bg_img_resource));
                    imagedestroy($bg_img_resource);
                }
            } else {
                error_log('Social Image Generator: Failed to create background image resource from URL: ' . $bg_image_url);
            }
        } else {
            error_log('Social Image Generator: Failed to get background image data from URL: ' . $bg_image_url);
        }
    }

    // --- Apply Color Overlay --- 
    if ($bg_opacity >= 0 && $bg_opacity < 100) { // Allow 0 opacity (no overlay), skip if 100 (fully transparent overlay)
        // Use the already allocated background color components ($bg_r, $bg_g, $bg_b)
        $alpha = round(((100 - $bg_opacity) / 100) * 127); // Convert 0-100 opacity to 127-0 alpha
        error_log('[SEO Image Gen] Applying overlay. Opacity: ' . $bg_opacity . ', Calculated Alpha: ' . $alpha . ', Color: ' . $bg_color);
        $overlay_alloc = imagecolorallocatealpha($image, $bg_r, $bg_g, $bg_b, $alpha);
        if ($overlay_alloc !== false) {
            if (!imagefilledrectangle($image, 0, 0, $width, $height, $overlay_alloc)) {
                error_log('[SEO Image Gen] Failed to draw filled rectangle for overlay.');
            }
        } else {
            error_log('[SEO Image Gen] Failed to allocate overlay color.');
        }
    } elseif ($bg_opacity == 100) {
        error_log('[SEO Image Gen] Skipping overlay: Opacity is 100.');
    } else {
        error_log('[SEO Image Gen] Skipping overlay: Opacity is invalid (< 0): ' . $bg_opacity);
    }

    // --- Draw Logo and Text (Based on Style) --- 
    if ($logo_img_resource) {
        $logo_w = imagesx($logo_img_resource);
        $logo_h = imagesy($logo_img_resource);

        // Prevent division by zero if logo dimensions are 0
        if ($logo_w == 0 || $logo_h == 0) {
            error_log('Social Image Generator: Logo dimensions are invalid (width: ' . $logo_w . ', height: ' . $logo_h . '). URL: ' . $logo_url);
            imagedestroy($logo_img_resource);
            $logo_img_resource = null; // Ensure it's null so the 'else' block below handles it
        } else {
            // Proceed with drawing logo if dimensions are valid
            // Draw logo centered, slightly raised
            $max_logo_w = $width * 0.6;
            $max_logo_h = $height * 0.6;
            $ratio = min($max_logo_w / $logo_w, $max_logo_h / $logo_h);
            $new_w = $logo_w * $ratio;
            $new_h = $logo_h * $ratio;
            $logo_x = ($width - $new_w) / 2;
            $logo_y = ($height - $new_h) / 2; // Center vertically
            imagecopyresampled($image, $logo_img_resource, $logo_x, $logo_y, 0, 0, $new_w, $new_h, $logo_w, $logo_h);

            imagedestroy($logo_img_resource); // Clean up logo resource
        } // End else for valid logo dimensions
    }

    // Handle case where logo resource is not available or invalid
    if (!$logo_img_resource) {
        error_log('Social Image Generator: No logo resource available. Image will contain background/overlay only.');
    }

    // --- Save Image --- 
    if (!imagejpeg($image, $file_path, 90)) {
        error_log('Social Image Generator: Failed to save image to: ' . $file_path);
        imagedestroy($image);
        return false;
    }
    imagedestroy($image);

    // --- Return Value --- 
    if ($type === 'base') {
        // Return URL and the hash of the settings used to generate this image
        return ['url' => $file_url, 'hash' => $current_settings_hash];
    } else { // 'preview'
        // Return only the URL for the preview image
        // Append a timestamp to prevent browser caching of the preview image
        return $file_url . '?t=' . time();
    }
}