<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Renders the Sitemap Generator admin page content.
 */
function aismg_render_sitemap_page()
{
    ?>
    <div class="wrap aismg-wrap aismg-sitemap-page">
        <h1><?php esc_html_e('AI Sitemap Page Generator', 'ai-sitemap-menu-generator'); ?></h1>
        <p><?php esc_html_e('Describe the website or business you want to create a sitemap for. The AI will suggest a hierarchical list of pages with SEO-friendly slugs.', 'ai-sitemap-menu-generator'); ?>
        </p>

        <div class="aismg-content-box">
            <div class="aismg-section">
                <h2><?php esc_html_e('1. Describe Your Site', 'ai-sitemap-menu-generator'); ?></h2>
                <label
                    for="aismg_sitemap_prompt"><?php esc_html_e('Site Description:', 'ai-sitemap-menu-generator'); ?></label>
                <textarea id="aismg_sitemap_prompt" name="aismg_sitemap_prompt" rows="5" class="large-text"
                    placeholder="e.g., A small local bakery selling custom cakes, bread, and pastries. We also offer catering."></textarea>
                <button id="aismg-generate-sitemap" class="button button-primary">Generate Sitemap Pages</button>
                <span class="spinner"></span>
            </div>

            <div id="aismg-sitemap-results" class="aismg-results" style="display: none;">
                <h2><?php esc_html_e('2. Review & Select Pages', 'ai-sitemap-menu-generator'); ?></h2>
                <!-- AJAX results for sitemap pages will be loaded here -->
            </div>
            <div class="aismg-status notice notice-success is-dismissible" style="display: none;">
                <!-- Status messages for page creation -->
            </div>
            <div class="aismg-error notice notice-error is-dismissible" style="display: none;">
                <!-- Error messages -->
            </div>
        </div>

    </div>
    <?php
}

/**
 * Renders the Menu Generator admin page content.
 */
function aismg_render_menu_page()
{
    ?>
    <div class="wrap aismg-wrap aismg-menu-page">
        <h1><?php esc_html_e('AI Menu Generator', 'ai-sitemap-menu-generator'); ?></h1>
        <p><?php esc_html_e('Describe the navigation menu you want. The AI will suggest items, linking to existing pages where possible and using indentation for sub-menus.', 'ai-sitemap-menu-generator'); ?>
        </p>

        <div class="aismg-content-box">
            <div class="aismg-section">
                <h2><?php esc_html_e('1. Describe Your Menu', 'ai-sitemap-menu-generator'); ?></h2>
                <label
                    for="aismg_menu_prompt"><?php esc_html_e('Menu Description:', 'ai-sitemap-menu-generator'); ?></label>
                <textarea id="aismg_menu_prompt" name="aismg_menu_prompt" rows="5" class="large-text"
                    placeholder="e.g., Main navigation for the bakery website, including Home, About (with Mission and Team sub-items), Products, and Contact."></textarea>
                <button id="aismg-generate-menu" class="button button-primary">Generate Menu Items</button>
                <span class="spinner"></span>
            </div>

            <div id="aismg-menu-results" class="aismg-results" style="display: none;">
                <h2><?php esc_html_e('2. Review & Create Menu', 'ai-sitemap-menu-generator'); ?></h2>
                <!-- AJAX results for menu items will be loaded here -->
            </div>
            <div class="aismg-status notice notice-success is-dismissible" style="display: none;">
                <!-- Status messages for menu creation -->
            </div>
            <div class="aismg-error notice notice-error is-dismissible" style="display: none;">
                <!-- Error messages -->
            </div>
        </div>

    </div>
    <?php
}

/**
 * Renders the Settings admin page content.
 */
function aismg_render_settings_page()
{
    ?>
    <div class="wrap aismg-wrap aismg-settings-page">
        <h1><?php esc_html_e('AI Site Generator - Settings', 'ai-sitemap-menu-generator'); ?></h1>

        <div class="aismg-content-box">
            <form method="post" action="options.php">
                <?php
                settings_fields('aismg_settings_group'); // Matches the group name used in register_setting
                do_settings_sections('aismg-settings'); // Matches the page slug where sections/fields were added
                submit_button();
                ?>
            </form>
        </div>
    </div>
    <?php
}

?>