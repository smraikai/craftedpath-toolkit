/**
 * WordPress dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { PanelBody, createSlotFill } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';

// --- Icon --- 
// Replace Dashicon with an Iconoir icon element
const AiToolsIcon = <i className="iconoir-sparks"></i>;

// --- SlotFill Setup --- 
// Create a SlotFill pair for our AI tools panel content.
// Tools will use the <Fill> component to add their UI here.
const { Fill, Slot } = createSlotFill('CraftedPathAiToolsPanelSlot');

// --- Main Sidebar Component --- 
const AiToolsSidebar = () => (
    <Fragment>
        {/* Register the menu item */}
        <PluginSidebarMoreMenuItem
            target="craftedpath-ai-tools-sidebar"
            icon={AiToolsIcon}
        >
            {__('AI Tools', 'craftedpath-toolkit')}
        </PluginSidebarMoreMenuItem>

        {/* Register the sidebar */}
        <PluginSidebar
            name="craftedpath-ai-tools-sidebar"
            title={__('AI Tools', 'craftedpath-toolkit')}
            icon={AiToolsIcon}
        >
            {/* 
               This Slot component defines the area where 
               individual AI tools (using the Fill component) will render their UI.
            */}
            <Slot>
                {/* 
                    Optional: We could pass props from Slot to Fill components if needed,
                    though typically data is fetched within the Fill components themselves.
                    Example: <Slot fillProps={ { someProp: 'value' } } /> 
                 */}
                {(fills) => {
                    // fills will be an array of all the registered Fill components.
                    // We can check if fills is empty to potentially hide the sidebar
                    // or display a "No tools active" message, but for now,
                    // let's just render them.
                    if (!fills || fills.length === 0) {
                        return (
                            <PanelBody>
                                {__('No AI tools available for this post type or context.', 'craftedpath-toolkit')}
                            </PanelBody>
                        );
                    }
                    return fills; // Render all the registered fills
                }}
            </Slot>
        </PluginSidebar>
    </Fragment>
);

// --- Register Plugin --- 
// Register the sidebar component with WordPress.
registerPlugin('craftedpath-ai-tools', {
    render: AiToolsSidebar,
    // No icon needed here as it's defined in PluginSidebar and PluginSidebarMoreMenuItem
});

// --- Export Fill --- 
// Export the Fill component so individual tools can use it.
export const AiToolsPanelFill = Fill; 