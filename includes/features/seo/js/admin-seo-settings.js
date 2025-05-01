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

                // Update the social share preview
                updateSocialSharePreview();
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

            // Update the social share preview
            updateSocialSharePreview();
        });

        // If there's already a logo, make sure the remove button is visible
        if ($input.val() && $input.val() !== '0') {
            $removeButton.show();
        }
    }

    // --- Social Share Preview Generation ---
    function updateSocialSharePreview() {
        const $previewContainer = $('.social-share-settings .auto-generate-preview .preview-image');
        if (!$previewContainer.length) return;

        const bgColor = $('select[name="craftedpath_seo_settings[social_image_bg_color]"]').val();
        const textColor = $('select[name="craftedpath_seo_settings[social_image_text_color]"]').val();
        const logoId = $('.social-logo-uploader .image-id').val();
        const logoUrl = $('.social-logo-uploader .image-preview img').attr('src');
        const siteName = $('input[name="craftedpath_seo_settings[site_name]"]').val() || 'Site Name';

        // Create canvas
        const canvas = document.createElement('canvas');
        canvas.width = 1200;
        canvas.height = 630;
        const ctx = canvas.getContext('2d');

        // Set background color
        const bgColorValue = getColorValue(bgColor);
        ctx.fillStyle = bgColorValue;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // Load logo if available
        if (logoUrl) {
            const logoImg = new Image();
            logoImg.crossOrigin = 'anonymous';
            logoImg.onload = function () {
                const padding = 80;
                const textColorValue = getColorValue(textColor);

                // Draw logo
                const maxW = canvas.width * 0.6;
                const maxH = canvas.height * 0.6;
                const ratio = Math.min(maxW / logoImg.width, maxH / logoImg.height);
                const newW = logoImg.width * ratio;
                const newH = logoImg.height * ratio;
                const logoX = (canvas.width - newW) / 2;
                const logoY = (canvas.height - newH) / 2;
                ctx.drawImage(logoImg, logoX, logoY, newW, newH);

                // Update preview
                $previewContainer.find('img').attr('src', canvas.toDataURL('image/jpeg', 0.9));
            };
            logoImg.src = logoUrl;
        } else {
            // If no logo, show site name
            const textColorValue = getColorValue(textColor);
            ctx.fillStyle = textColorValue;
            ctx.font = 'bold 80px Open Sans';
            const siteNameWidth = ctx.measureText(siteName).width;
            ctx.fillText(siteName, (canvas.width - siteNameWidth) / 2, canvas.height / 2);

            // Update preview
            $previewContainer.find('img').attr('src', canvas.toDataURL('image/jpeg', 0.9));
        }
    }

    // Helper function to get color value
    function getColorValue(key) {
        switch (key) {
            case 'primary':
                return '#f55f4b';
            case 'black':
                return '#1f2937';
            case 'white':
                return '#ffffff';
            case 'alt':
                return '#6b7280';
            case 'hover':
                return '#e14b37';
            default:
                return '#f55f4b';
        }
    }

    // --- Event Listeners ---
    $('select[name^="craftedpath_seo_settings[social_image"]').on('change', function () {
        updateSocialSharePreview();
    });

    $('input[name="craftedpath_seo_settings[site_name]"]').on('input', function () {
        updateSocialSharePreview();
    });

    // Initialize the logo uploader
    initializeSocialLogoUploader();

    // Wait for wp.media to be available before initializing preview
    function waitForMedia() {
        if (typeof wp !== 'undefined' && wp.media) {
            // Initialize the preview
            updateSocialSharePreview();
        } else {
            setTimeout(waitForMedia, 100);
        }
    }

    // Start waiting for wp.media
    waitForMedia();
}); 