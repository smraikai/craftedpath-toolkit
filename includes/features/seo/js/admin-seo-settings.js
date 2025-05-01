jQuery(document).ready(function ($) {
    console.log('[SEO Settings] Document ready.');

    // Media uploader script
    let mediaFrame;
    const imageUploader = '.craftedpath-image-uploader'; // Container class

    $(document).on('click', imageUploader + ' .upload-button', function (e) {
        e.preventDefault();

        const $button = $(this);
        const $container = $button.closest(imageUploader);
        const $input = $container.find('.image-id');
        const $preview = $container.find('.image-preview');
        const $removeButton = $container.find('.remove-button');

        // If the media frame already exists, reopen it.
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        // Create the media frame.
        mediaFrame = wp.media({
            title: $button.data('uploader-title') || 'Select or Upload Image',
            button: {
                text: $button.data('uploader-button-text') || 'Use this image',
            },
            multiple: false // Set to true if you want to allow multiple image selection
        });

        // When an image is selected, run a callback.
        mediaFrame.on('select', function () {
            // We only get one image from the selection
            const attachment = mediaFrame.state().get('selection').first().toJSON();

            // Update the hidden input field value
            $input.val(attachment.id);

            // Update the image preview
            if (attachment.sizes && attachment.sizes.medium) {
                $preview.html('<img src="' + attachment.sizes.medium.url + '" style="max-width: 200px; height: auto;" />');
            } else {
                $preview.html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto;" />');
            }

            // Show the remove button
            $removeButton.show();
        });

        // Finally, open the modal
        mediaFrame.open();
    });

    // Handle remove image button click
    $(document).on('click', imageUploader + ' .remove-button', function (e) {
        e.preventDefault();

        const $button = $(this);
        const $container = $button.closest(imageUploader);
        const $input = $container.find('.image-id');
        const $preview = $container.find('.image-preview');

        // Clear the input field value
        $input.val(''); // Use empty string or 0, depending on how you handle no image

        // Clear the preview
        $preview.html('');

        // Hide the remove button
        $button.hide();
    });

    // --- Social Share Logo Uploader --- 
    function initializeSocialLogoUploader() {
        const $container = $('.social-logo-uploader');
        if (!$container.length) return;

        // Check if wp.media is loaded
        if (typeof wp === 'undefined' || !wp.media) {
            console.error('[SEO Settings] WP Media library not available.');
            return;
        }

        let mediaFrame;
        const $input = $container.find('.image-id');
        const $preview = $container.find('.image-preview');
        const $uploadButton = $container.find('.upload-button');
        const $removeButton = $container.find('.remove-button');
        const noLogoText = $preview.find('.description').length ? $preview.find('.description').text() : 'No logo selected.';

        // Remove any existing handlers first
        $uploadButton.off('click');
        $removeButton.off('click');

        // Attach new handlers
        $uploadButton.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (typeof wp === 'undefined' || !wp.media) {
                console.error('[SEO Settings] wp.media not available on click!');
                return;
            }

            // If the media frame already exists, reopen it.
            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            // Create the media frame.
            mediaFrame = wp.media({
                title: 'Select or Upload Logo',
                button: {
                    text: 'Use this logo'
                },
                library: { type: 'image' },
                multiple: false
            });

            // When an image is selected, run a callback.
            mediaFrame.on('select', function () {
                const attachment = mediaFrame.state().get('selection').first().toJSON();
                $input.val(attachment.id);
                const displayUrl = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
                $preview.html('<img src="' + displayUrl + '" style="max-width: 100%; height: auto; display: block;" />');
                $removeButton.show();
            });

            // Finally, open the modal
            mediaFrame.open();
        });

        $removeButton.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $input.val('0');
            $preview.html('<span class="description">' + noLogoText + '</span>');
            $(this).hide();
        });
    }

    // --- AJAX Preview Update for Layout/Color Settings --- 
    $('select[name^="craftedpath_seo_settings[social_image"]').off('change').on('change', function () {
        const $previewContainer = $('.social-share-settings .auto-generate-preview .preview-image');
        if (!$previewContainer.length) return;

        $previewContainer.css('opacity', '0.5');

        const style = $('select[name="craftedpath_seo_settings[social_image_style]"]').val();
        const bgColor = $('select[name="craftedpath_seo_settings[social_image_bg_color]"]').val();
        const textColor = $('select[name="craftedpath_seo_settings[social_image_text_color]"]').val();

        $.post(ajaxurl, {
            action: 'update_social_image_preview',
            style: style,
            bg_color: bgColor,
            text_color: textColor,
            nonce: wp?.ajax?.settings?.nonce || ''
        }, function (response) {
            if (response.success && response.data.preview_url) {
                $previewContainer.find('img').attr('src', response.data.preview_url + '?t=' + new Date().getTime());
            } else {
                console.error('Failed to update preview:', response);
            }
        }).fail(function (xhr, status, error) {
            console.error('AJAX request failed:', status, error);
        }).always(function () {
            $previewContainer.css('opacity', '1');
        });
    });

    // Initialize the logo uploader
    initializeSocialLogoUploader();
}); 