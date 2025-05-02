/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelBody, Button, Spinner, Notice } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { store as coreStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';

/**
 * Internal dependencies
 */
import { AiToolsPanelFill } from '../../components/ai-tools-sidebar';

// Localized data from PHP
const aiTagData = window.cptAiAutoTagData || {};
const i18n = aiTagData.i18n || {};

const AutoTagPanel = () => {
    if (!aiTagData.is_enabled) {
        return null;
    }

    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');
    const [statusType, setStatusType] = useState('info');

    const { postId, postType, postTitle, postContent, postStatus } = useSelect(
        (select) => {
            const { getCurrentPostId, getEditedPostAttribute } = select(editorStore);
            const { getEntityRecord } = select(coreStore);
            const currentPostId = getCurrentPostId();
            const currentPostType = getEditedPostAttribute('type');
            const postEntity = currentPostId && currentPostType
                ? getEntityRecord('postType', currentPostType, currentPostId)
                : null;
            return {
                postId: currentPostId,
                postType: currentPostType,
                postTitle: getEditedPostAttribute('title'),
                postContent: postEntity?.content?.raw || '',
                postStatus: getEditedPostAttribute('status'),
            };
        },
        []
    );

    const { editPost } = useDispatch(editorStore);

    const handleAutoTag = () => {
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

        const data = {
            action: 'cptk_auto_tag_post',
            nonce: aiTagData.nonce,
            post_id: postId,
        };

        window.wp.ajax.post(data)
            .done((response) => {
                console.log('Auto Tag Success:', response);
                if (response.tag_term_ids && response.tag_term_ids.length > 0) {
                    setStatusType('success');
                    setStatusMessage(response.message || 'Tags assigned!');
                    // Update editor state - wp_set_post_tags should trigger this,
                    // but explicitly dispatching is safer.
                    editPost({ tags: response.tag_term_ids });
                } else if (response.tag_term_ids && response.tag_term_ids.length === 0) {
                    // Handle case where AI returned no tags
                    setStatusType('info');
                    setStatusMessage(i18n.noTagsAdded || 'No relevant tags suggested.');
                    // Optionally clear existing tags if replacing
                    editPost({ tags: [] });
                } else {
                    // Handle unexpected success response
                    setStatusType('warning');
                    setStatusMessage(i18n.genericError || 'Received success, but no tag data.')
                }
            })
            .fail((jqXHR) => {
                console.error('Auto Tag Error:', jqXHR);
                let errorMessage = i18n.genericError || 'An error occurred.';
                if (jqXHR.responseJSON?.data?.message) {
                    errorMessage = jqXHR.responseJSON.data.message;
                } else if (jqXHR.responseText) {
                    errorMessage = jqXHR.responseText.substring(0, 100);
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
            <PanelBody title={i18n.panelTitle || 'AI Auto Tagging'} initialOpen={true}>
                <p style={{ marginBottom: '1em' }}>
                    {__('Automatically assign relevant tags based on post content.', 'craftedpath-toolkit')}
                </p>
                <Button
                    variant="primary"
                    onClick={handleAutoTag}
                    isBusy={isLoading}
                    disabled={isLoading}
                    style={{ marginBottom: '1em' }}
                >
                    {isLoading ? i18n.loadingText || 'Tagging...' : i18n.buttonText || 'Auto Tag Post'}
                </Button>
                {isLoading && <Spinner style={{ marginLeft: '8px', verticalAlign: 'middle' }} />}
                {statusMessage && (
                    <Notice
                        status={statusType}
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

export default AutoTagPanel; 