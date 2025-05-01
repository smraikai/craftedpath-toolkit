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
 * Main function to generate the social share image based on settings.
 *
 * @param array $settings SEO settings array.
 * @param string $title Optional title override (e.g., for post-specific previews).
 * @param string $filename_suffix Optional suffix for filename differentiation (e.g., 'preview').
 * @return string|false Generated image URL or false on failure.
 */
function generate_image($settings = [], $title = null, $filename_suffix = 'base')
{
    $options = !empty($settings) ? $settings : get_option('craftedpath_seo_settings', []);

    // Defaults
    $width = 1200;
    $height = 630;
    $padding = 80;
    $default_site_name = get_bloginfo('name');
    $site_name = !empty($options['site_name']) ? $options['site_name'] : $default_site_name;
    $style = $options['social_image_style'] ?? 'style1';
    $bg_color_input = $options['social_image_bg_color'] ?? '#ffffff';
    $custom_bg_color = $options['social_image_custom_bg_color'] ?? '#f55f4b';
    $bg_opacity = isset($options['social_image_bg_opacity']) ? intval($options['social_image_bg_opacity']) : 100;
    $bg_image_id = $options['social_image_bg_image_id'] ?? 0;
    $text_color_input = $options['social_image_text_color'] ?? 'white';
    $logo_id = $options['social_share_logo_id'] ?? 0;

    // Determine actual background color
    $bg_color = ($bg_color_input === 'custom') ? $custom_bg_color : get_color_value($bg_color_input);
    $text_color = get_color_value($text_color_input);

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

    // --- Generate Filename --- 
    // Create a hash based on relevant settings to ensure regeneration when needed
    $settings_hash = md5(json_encode([
        'style' => $style,
        'bg_color' => $bg_color,
        'opacity' => $bg_opacity,
        'bg_image_id' => $bg_image_id,
        'text_color' => $text_color,
        'logo_id' => $logo_id,
        'site_name' => $site_name,
        // Add title if it's being used (for potential future per-post images)
        'title' => $title
    ]));

    $filename = 'social-' . $filename_suffix . '-' . $settings_hash . '.jpg';

    // --- Check Cache --- 
    $seo_dir = get_seo_upload_dir();
    if (!$seo_dir) {
        error_log('Social Image Generator: Failed to get SEO upload directory.');
        if ($logo_img_resource)
            imagedestroy($logo_img_resource);
        return false;
    }
    $file_path = $seo_dir['path'] . '/' . $filename;
    $file_url = $seo_dir['url'] . '/' . $filename;
    if (is_ssl()) {
        $file_url = str_replace('http://', 'https://', $file_url);
    }

    // If image exists, return its URL
    if (file_exists($file_path)) {
        if ($logo_img_resource)
            imagedestroy($logo_img_resource);
        return $file_url;
    }

    // Delete old files with the same suffix (e.g., old base or preview images)
    $old_files = glob($seo_dir['path'] . '/social-' . $filename_suffix . '-*.jpg');
    if ($old_files) {
        foreach ($old_files as $old_file) {
            if ($old_file !== $file_path) { // Don't delete the file we are about to create
                @unlink($old_file);
            }
        }
    }

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

    // Allocate text color
    list($text_r, $text_g, $text_b) = sscanf($text_color, "#%02x%02x%02x");
    $text_alloc = imagecolorallocate($image, $text_r, $text_g, $text_b);
    if ($text_alloc === false) {
        error_log('Social Image Generator: Failed to allocate text color: ' . $text_color);
        imagedestroy($image);
        if ($logo_img_resource)
            imagedestroy($logo_img_resource);
        return false;
    }

    // --- Draw Background Image --- 
    if ($bg_image_url) {
        $bg_image_data = @file_get_contents($bg_image_url);
        if ($bg_image_data) {
            $bg_img_resource = @imagecreatefromstring($bg_image_data);
            if ($bg_img_resource) {
                // Calculate dimensions for cover sizing
                $canvas_ratio = $width / $height;
                $img_ratio = imagesx($bg_img_resource) / imagesy($bg_img_resource);

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
            } else {
                error_log('Social Image Generator: Failed to create background image resource from URL: ' . $bg_image_url);
            }
        } else {
            error_log('Social Image Generator: Failed to get background image data from URL: ' . $bg_image_url);
        }
    }

    // --- Apply Color Overlay --- 
    if ($bg_opacity > 0 && $bg_opacity <= 100) {
        $alpha = round(((100 - $bg_opacity) / 100) * 127); // Convert 0-100 opacity to 127-0 alpha
        $overlay_alloc = imagecolorallocatealpha($image, $bg_r, $bg_g, $bg_b, $alpha);
        if ($overlay_alloc !== false) {
            imagefilledrectangle($image, 0, 0, $width, $height, $overlay_alloc);
        } else {
            error_log('Social Image Generator: Failed to allocate overlay color.');
        }
    }

    // --- Draw Logo and Text (Based on Style) --- 
    if ($logo_img_resource) {
        $logo_w = imagesx($logo_img_resource);
        $logo_h = imagesy($logo_img_resource);

        switch ($style) {
            case 'style2': // Split Layout
                $logo_area_width = $width * 0.4;
                $text_area_x = $logo_area_width + $padding;
                $text_area_width = $width - $text_area_x - $padding;

                // Draw logo (centered in left area)
                $max_logo_w = $logo_area_width - ($padding * 1.5);
                $max_logo_h = $height - ($padding * 2);
                $ratio = min($max_logo_w / $logo_w, $max_logo_h / $logo_h);
                $new_w = $logo_w * $ratio;
                $new_h = $logo_h * $ratio;
                $logo_x = ($logo_area_width - $new_w) / 2;
                $logo_y = ($height - $new_h) / 2;
                imagecopyresampled($image, $logo_img_resource, $logo_x, $logo_y, 0, 0, $new_w, $new_h, $logo_w, $logo_h);

                // Draw Title (if provided) or Site Name (if not) in right area
                $display_text = $title ?: $site_name;
                $font_size = 60; // Start large
                $lines = [];
                // Decrease font size until text fits
                do {
                    $lines = wrap_text($font_size, 0, $font_path, $display_text, $text_area_width);
                    if (empty($lines)) { // Check if wrap_text failed
                        error_log("Social Image Generator: wrap_text failed for font size {$font_size}");
                        $font_size -= 2;
                        continue;
                    }
                    $total_text_height = (count($lines) * $font_size * 1.5); // Approximate height
                    if ($total_text_height > ($height - $padding * 2)) {
                        $font_size -= 2;
                    } else {
                        break;
                    }
                } while ($font_size > 10); // Minimum font size

                $line_height = $font_size * 1.5;
                $start_y = ($height - (count($lines) * $line_height - ($line_height - $font_size))) / 2 + $font_size / 2; // Center vertically

                foreach ($lines as $i => $line) {
                    $text_box = @imagettfbbox($font_size, 0, $font_path, $line);
                    if (!$text_box) {
                        continue;
                    }
                    $line_width = abs($text_box[4] - $text_box[0]);
                    $line_x = $text_area_x + ($text_area_width - $line_width) / 2; // Center horizontally in text area
                    $line_y = $start_y + ($i * $line_height);
                    imagettftext($image, $font_size, 0, $line_x, $line_y, $text_alloc, $font_path, $line);
                }

                // Optionally add site name at bottom if title was shown
                if ($title && $site_name) {
                    $site_name_font_size = 20;
                    $site_name_box = @imagettfbbox($site_name_font_size, 0, $font_path, $site_name);
                    if ($site_name_box) {
                        $site_name_width = abs($site_name_box[4] - $site_name_box[0]);
                        imagettftext($image, $site_name_font_size, 0, $width - $padding - $site_name_width, $height - $padding + 10, $text_alloc, $font_path, $site_name);
                    }
                }
                break;

            case 'style3': // Logo Overlay
                // Draw logo centered
                $max_w = $width - ($padding * 3);
                $max_h = $height - ($padding * 3);
                $ratio = min($max_w / $logo_w, $max_h / $logo_h);
                $new_w = $logo_w * $ratio;
                $new_h = $logo_h * $ratio;
                $logo_x = ($width - $new_w) / 2;
                $logo_y = ($height - $new_h) / 2;
                imagecopyresampled($image, $logo_img_resource, $logo_x, $logo_y, 0, 0, $new_w, $new_h, $logo_w, $logo_h);

                // Add slight dark overlay to make text more readable potentially?
                // Let's keep this simple for now and match the original logic where style3 didn't add text.
                break;

            default: // style1 - Logo Focus (Logo + Site Name Below)
                // Draw logo centered, slightly raised
                $max_logo_w = $width * 0.6;
                $max_logo_h = $height * 0.6;
                $ratio = min($max_logo_w / $logo_w, $max_logo_h / $logo_h);
                $new_w = $logo_w * $ratio;
                $new_h = $logo_h * $ratio;
                $logo_x = ($width - $new_w) / 2;
                $logo_y = ($height - $new_h) / 2 - 30; // Adjust vertical position
                imagecopyresampled($image, $logo_img_resource, $logo_x, $logo_y, 0, 0, $new_w, $new_h, $logo_w, $logo_h);

                // Draw site name below logo
                $font_size = 40;
                $text_box = @imagettfbbox($font_size, 0, $font_path, $site_name);
                if ($text_box) {
                    $site_name_width = abs($text_box[4] - $text_box[0]);
                    // Prevent text exceeding image width
                    while ($site_name_width > ($width - $padding * 2) && $font_size > 10) {
                        $font_size -= 2;
                        $text_box = @imagettfbbox($font_size, 0, $font_path, $site_name);
                        if (!$text_box)
                            break;
                        $site_name_width = abs($text_box[4] - $text_box[0]);
                    }
                    if ($text_box) { // Check again after loop
                        $site_name_x = ($width - $site_name_width) / 2;
                        $site_name_y = $logo_y + $new_h + $padding / 2 + $font_size; // Position below logo
                        imagettftext($image, $font_size, 0, $site_name_x, $site_name_y, $text_alloc, $font_path, $site_name);
                    }
                } else {
                    error_log('Social Image Generator: Failed to calculate text box for site name: ' . $site_name);
                }
                break;
        }
        imagedestroy($logo_img_resource); // Clean up logo resource
    } else {
        // No logo - Maybe just draw Title or Site Name centered?
        // For now, let's keep it simple: if no logo, the image might be suboptimal based on style.
        // Style 1 would just be background + overlay.
        // Style 2 would be background + overlay + Text (if title/site name exists).
        // Style 3 would just be background + overlay.
        if ($style === 'style2') {
            $display_text = $title ?: $site_name;
            if ($display_text) {
                $text_area_x = $padding;
                $text_area_width = $width - ($padding * 2);
                $font_size = 70; // Start large
                $lines = [];
                do {
                    $lines = wrap_text($font_size, 0, $font_path, $display_text, $text_area_width);
                    if (empty($lines)) {
                        $font_size -= 2;
                        continue;
                    }
                    $total_text_height = (count($lines) * $font_size * 1.5);
                    if ($total_text_height > ($height - $padding * 2)) {
                        $font_size -= 2;
                    } else {
                        break;
                    }
                } while ($font_size > 10);

                $line_height = $font_size * 1.5;
                $start_y = ($height - (count($lines) * $line_height - ($line_height - $font_size))) / 2 + $font_size / 2;

                foreach ($lines as $i => $line) {
                    $text_box = @imagettfbbox($font_size, 0, $font_path, $line);
                    if (!$text_box) {
                        continue;
                    }
                    $line_width = abs($text_box[4] - $text_box[0]);
                    $line_x = $text_area_x + ($text_area_width - $line_width) / 2;
                    $line_y = $start_y + ($i * $line_height);
                    imagettftext($image, $font_size, 0, $line_x, $line_y, $text_alloc, $font_path, $line);
                }
            }
        }
        error_log('Social Image Generator: Logo resource not available or failed to load.');
    }

    // --- Save Image --- 
    if (!imagejpeg($image, $file_path, 90)) {
        error_log('Social Image Generator: Failed to save image to: ' . $file_path);
        imagedestroy($image);
        return false;
    }
    imagedestroy($image);

    return $file_url;
}