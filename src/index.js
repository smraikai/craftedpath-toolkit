/**
 * Entry point for Gutenberg blocks and plugins.
 */
import './seo-panel'; // Assume this handles SEO panel registration

/**
 * WordPress dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
// import { PluginDocumentSettingPanel } from '@wordpress/edit-post'; // No longer needed here if handled in seo-panel.js
// import { __ } from '@wordpress/i18n'; // No longer needed here if handled in seo-panel.js

/**
 * Internal dependencies
 */
// import SeoFields from './features/seo/components/SeoFields'; // REMOVED - Assume handled by ./seo-panel
// import './features/seo/store'; // Existing SEO Store - COMMENTED OUT due to build error
import './components/ai-tools-sidebar'; // Import to register the main AI Tools sidebar
import AutoCategorizePanel from './features/ai-auto-categorize/panel'; // Import the specific tool panel
import AutoTagPanel from './features/ai-auto-tag/panel'; // Import the Auto Tag panel


// --- SEO Document Setting Panel --- (REMOVED - Assume handled by ./seo-panel)
/*
const SeoDocumentSettingPanel = () => (
    <PluginDocumentSettingPanel
        name="craftedpath-seo-panel"
        title={__('SEO', 'craftedpath-toolkit')}
        className="craftedpath-seo-panel"
    >
        <SeoFields />
    </PluginDocumentSettingPanel>
);

// Register the SEO panel if cptSeoData is available (set by PHP)
if (window.cptSeoData) {
    registerPlugin('craftedpath-seo-document-setting-panel', {
        render: SeoDocumentSettingPanel,
        icon: null, // Use default panel icon or set one here
    });
}
*/

// --- Register AI Auto Categorize Panel --- (New Code)
// Check if the localized data exists for the auto-categorize feature
// The component itself (`AutoCategorizePanel`) also checks this, but checking here prevents unnecessary import processing if disabled.
if (window.cptAiAutoCategorizeData && window.cptAiAutoCategorizeData.is_enabled) {
    // We don't need to call registerPlugin here because AutoCategorizePanel
    // renders an AiToolsPanelFill, which automatically places it within the
    // Slot defined by the already registered AiToolsSidebar component.
    // We just need to ensure the component code is loaded.

    // However, to ensure React renders it, we might need a dummy registration
    // or ensure it's part of the component tree somehow if it doesn't render immediately.
    // Let's try registering it simply to ensure it's processed.
    registerPlugin('craftedpath-ai-auto-categorize-panel-loader', {
        render: AutoCategorizePanel, // Render the panel component which uses the Fill
        icon: null // No icon needed for a Fill component registration
    });
    console.log('AI Auto Categorize Panel Loaded');
}

// --- Register AI Auto Tag Panel --- (New Code)
if (window.cptAiAutoTagData && window.cptAiAutoTagData.is_enabled) {
    registerPlugin('craftedpath-ai-auto-tag-panel-loader', {
        render: AutoTagPanel,
        icon: null
    });
    console.log('AI Auto Tag Panel Loaded');
}

// You could add more plugin registrations or component initializations here. 