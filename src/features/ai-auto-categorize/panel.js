/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelBody, Button, Spinner, Notice } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { store as coreStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor'; // For getting post content

/**
 * Internal dependencies
 */
import { AiToolsPanelFill } from '../../components/ai-tools-sidebar'; // Import the Fill component

// Access the localized data passed from PHP
const aiCategorizeData = window.cptAiAutoCategorizeData || {};
const i18n = aiCategorizeData.i18n || {}; // Get localization strings

const AutoCategorizePanel = () => {
    // Only render if the feature is enabled via PHP localization
    if (!aiCategorizeData.is_enabled) {
        return null;
    }

    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');
    const [statusType, setStatusType] = useState('info'); // 'info', 'success', 'error'

    // Get necessary post data using the core data store
    const { postId, postType, postTitle, postContent, currentCategories, postStatus } = useSelect(
        (select) => {
            const { getCurrentPostId, getEditedPostAttribute } = select(editorStore);
            const { getEntityRecord } = select(coreStore);

            const currentPostId = getCurrentPostId();
            const currentPostType = getEditedPostAttribute('type');

            // Fetch the post entity to get content and status accurately
            const postEntity = currentPostId && currentPostType
                ? getEntityRecord('postType', currentPostType, currentPostId)
                : null;

            return {
                postId: currentPostId,
                postType: currentPostType,
                postTitle: getEditedPostAttribute('title'),
                postContent: postEntity?.content?.raw || '', // Get raw content from entity if available
                currentCategories: getEditedPostAttribute('categories') || [],
                postStatus: getEditedPostAttribute('status'),
            };
        },
        [] // No dependencies, re-runs when editor state changes
    );

    // Get dispatch functions to update categories
    const { editPost } = useDispatch(editorStore);
    const { invalidateResolution } = useDispatch(coreStore); // To refresh category list if needed

    // Function to handle the API call
    const handleAutoCategorize = () => {
        // Basic checks before calling API
        if (!postId || postStatus === 'auto-draft') {
            setStatusType('error');
            setStatusMessage(i18n.needsSaveError || 'Please save the post first.');
            return;
        }
        if (!postTitle && !postContent) {
            setStatusType('error');
            setStatusMessage(i18n.noContentError || 'Post needs title or content.');
            return;
        }

        setIsLoading(true);
        setStatusMessage('');
        setStatusType('info');

        // Prepare data for AJAX call (matches PHP handler)
        const data = {
            action: 'cptk_auto_categorize_post', // Matches WP AJAX hook
            nonce: aiCategorizeData.nonce,
            post_id: postId,
            // Title & Content are fetched server-side in PHP from the post_id
        };

        // Use wp.ajax.post (requires jQuery, already a dependency)
        window.wp.ajax.post(data)
            .done((response) => {
                setStatusType('success');
                setStatusMessage(response.message || 'Success!');
                console.log('Auto Categorize Success:', response);

                // Update categories in the editor state
                if (response.category_id && response.category_name) {
                    // Replace existing categories with the new one
                    editPost({ categories: [response.category_id] });

                    // Invalidate term resolution to ensure the UI updates if the category was new
                    invalidateResolution('getEntityRecords', ['taxonomy', 'category', { per_page: -1 }]);
                } else {
                    setStatusType('warning');
                    setStatusMessage(i18n.genericError || 'Received success, but no category data.')
                }
            })
            .fail((jqXHR, textStatus, errorThrown) => {
                console.error('Auto Categorize Error:', jqXHR, textStatus, errorThrown);
                let errorMessage = i18n.genericError || 'An error occurred.';
                if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    errorMessage = jqXHR.responseJSON.data.message;
                } else if (jqXHR.responseText) {
                    // Try to get basic text if JSON fails
                    errorMessage = jqXHR.responseText.substring(0, 100); // Limit length
                }
                setStatusType('error');
                setStatusMessage(`${i18n.errorPrefix || 'Error:'} ${errorMessage}`);
            })
            .always(() => {
                setIsLoading(false);
            });
    };

    return (
        <AiToolsPanelFill>
            <PanelBody title={__('Auto Categorization', 'craftedpath-toolkit')} initialOpen={true}>
                <p style={{ marginBottom: '1em' }}>
                    {__('Automatically assign the most relevant category based on post content.', 'craftedpath-toolkit')}
                </p>
                <Button
                    variant="primary"
                    onClick={handleAutoCategorize}
                    isBusy={isLoading}
                    disabled={isLoading}
                    style={{ marginBottom: '1em' }}
                >
                    {isLoading ? i18n.loadingText || 'Categorizing...' : i18n.buttonText || 'Auto Categorize'}
                </Button>
                {isLoading && <Spinner style={{ marginLeft: '8px', verticalAlign: 'middle' }} />}
                {statusMessage && (
                    <Notice
                        status={statusType} // 'success', 'error', 'info', 'warning'
                        isDismissible={true}
                        onRemove={() => setStatusMessage('')}
                        style={{ marginTop: '1em' }}
                    >
                        {statusMessage}
                    </Notice>
                )}
            </PanelBody>
        </AiToolsPanelFill>
    );
};

export default AutoCategorizePanel; 