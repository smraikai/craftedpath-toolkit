/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { TextControl, TextareaControl, PanelRow, RangeControl, Notice, CheckboxControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';

const SeoPanel = () => {
    // Get localized settings
    const siteName = window.cptSeoData?.siteName || 'Default Site Name'; // Provide fallback
    const divider = window.cptSeoData?.divider || '|'; // Provide fallback

    // Get post data
    const { postTitle, seoTitle, seoDescription, seoNoIndex } = useSelect(
        (select) => {
            const { getEditedPostAttribute } = select(editorStore);
            const meta = getEditedPostAttribute('meta') || {};
            return {
                postTitle: getEditedPostAttribute('title'),
                seoTitle: meta?._craftedpath_seo_title || '',
                seoDescription: meta?._craftedpath_seo_description || '',
                seoNoIndex: meta?._craftedpath_seo_noindex || false,
            };
        },
        []
    );

    // Update post meta
    const { editPost } = useDispatch(editorStore);

    const updateMeta = (key, value) => {
        editPost({ meta: { [key]: value } });
    };

    // Default Title
    const defaultSeoTitle = postTitle ? `${postTitle} ${divider} ${siteName}` : ``;
    const currentSeoTitle = seoTitle || defaultSeoTitle;
    const currentSeoDescription = seoDescription; // Keep user input even if empty

    // Character counts
    const titleLength = currentSeoTitle.length;
    const descriptionLength = currentSeoDescription.length;

    // Recommended lengths
    const titleRecommendedMin = 50;
    const titleRecommendedMax = 60; // Often cited max, though Google adjusts
    const descriptionRecommendedMin = 50;
    const descriptionRecommendedMax = 160;

    // Determine status for visual indicators
    const getLengthStatus = (length, min, max) => {
        if (length === 0) return 'default';
        if (length < min) return 'warning';
        if (length > max) return 'error';
        return 'success';
    };

    const titleStatus = getLengthStatus(titleLength, titleRecommendedMin, titleRecommendedMax);
    const descriptionStatus = getLengthStatus(descriptionLength, descriptionRecommendedMin, descriptionRecommendedMax);

    const getStatusColor = (status) => {
        switch (status) {
            case 'warning': return '#ffb900'; // WordPress yellow
            case 'error': return '#dc3232'; // WordPress red
            case 'success': return '#46b450'; // WordPress green
            default: return '#a0a5aa'; // Gray
        }
    };

    // Simple visual progress bar (can be enhanced with CSS)
    const ProgressBar = ({ value, max, status }) => {
        const percentage = Math.min(100, (value / max) * 100);
        const color = getStatusColor(status);
        return (
            <div style={{ height: '8px', background: '#e0e0e0', borderRadius: '4px' }}>
                <div style={{ height: '100%', width: `${percentage}%`, background: color, borderRadius: '4px' }}></div>
            </div>
        );
    };

    return (
        <PluginDocumentSettingPanel
            name="craftedpath-seo-panel"
            title={__('SEO Settings', 'craftedpath-toolkit')}
            className="craftedpath-seo-panel"
        >
            {/* Grouping div for Title section */}
            <div style={{ marginBottom: '16px' }}>
                <TextControl
                    label={__('SEO Title', 'craftedpath-toolkit')}
                    value={currentSeoTitle}
                    hideLabelFromVision={false}
                    placeholder={defaultSeoTitle}
                    onChange={(value) => updateMeta('_craftedpath_seo_title', value)}
                    help={`${titleLength} / ${titleRecommendedMax} ${__('characters', 'craftedpath-toolkit')}`}
                    style={{ marginBottom: '4px' }} // Space before progress bar
                />
                <ProgressBar value={titleLength} max={titleRecommendedMax} status={titleStatus} />
            </div>

            {/* Grouping div for Description section */}
            <div style={{ marginBottom: '16px' }}>
                <TextareaControl
                    label={__('Meta Description', 'craftedpath-toolkit')}
                    value={currentSeoDescription}
                    hideLabelFromVision={false}
                    help={`${descriptionLength} / ${descriptionRecommendedMax} ${__('characters', 'craftedpath-toolkit')}. ${__('Recommended:', 'craftedpath-toolkit')} ${descriptionRecommendedMin}-${descriptionRecommendedMax}`}
                    onChange={(value) => updateMeta('_craftedpath_seo_description', value)}
                    style={{ width: '100%', marginBottom: '4px' }} // Space before progress bar
                />
                <ProgressBar value={descriptionLength} max={descriptionRecommendedMax} status={descriptionStatus} />
            </div>

            {/* No Index Checkbox section (already styled like a card) */}
            <div style={{
                marginTop: '16px', // Keep space above this section
                padding: '12px 16px', // Adjusted padding slightly
                border: '1px solid #e0e0e0',
                borderRadius: '4px'
            }}>
                {/* No PanelRow needed inside here either */}
                <CheckboxControl
                    label={__("Noindex this page", 'craftedpath-toolkit')}
                    checked={seoNoIndex}
                    onChange={(isChecked) => updateMeta('_craftedpath_seo_noindex', isChecked)}
                // Remove help prop if previously added
                />
            </div>

        </PluginDocumentSettingPanel>
    );
};

registerPlugin('craftedpath-seo', {
    render: SeoPanel,
    icon: <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" >
        <path strokeLinecap="round" strokeLinejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M6 6h.008v.008H6V6Z" />
    </svg>,
}); 