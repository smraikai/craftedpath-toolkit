jQuery(document).ready(function ($) {
    console.log('[SEO Social Settings] Document ready.'); // Changed log prefix

    // Store nonce
    const updatePreviewNonce = $('#craftedpath_update_social_preview_nonce').val();

    // --- Helper: Debounce --- 
    function debounce(func, wait, immediate) {
        var timeout;
        return function () {
            var context = this, args = arguments;
            var later = function () {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    };

    // --- Server-Side Preview Update Trigger --- 
    const triggerServerSidePreviewUpdate = debounce(function (triggerSource) {
        console.log(`[SEO Social Settings] Triggering server-side preview update from: ${triggerSource}`); // Changed log prefix
        const $previewContainer = $('.social-share-settings .auto-generate-preview .preview-image');
        const $previewImage = $previewContainer.find('img');
        // Add inline styles for absolute centering over the container
        const $loadingSpinner = $('<span class="spinner is-active" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10; background-color: rgba(255,255,255,0.6); border-radius: 50%; padding: 5px;"></span>');

        if (!$previewContainer.length) return;

        // Ensure container has relative positioning for absolute child
        $previewContainer.css('position', 'relative');

        // Show loading state
        $previewImage.css('opacity', 0.5);
        $previewContainer.append($loadingSpinner); // Append spinner to container

        // Gather data
        const data = {
            action: 'update_social_image_preview',
            nonce: updatePreviewNonce,
            bg_color: $('#social-bg-color').val(),
            bg_opacity: $('#social-bg-opacity').val(),
            logo_id: $('.social-logo-uploader .image-id').val(),
            bg_image_id: $('.social-bg-uploader .image-id').val(),
            site_name: $('input[name="craftedpath_seo_settings[site_name]"]').val()
            // text_color: removed
        };

        // Perform AJAX request
        console.log('[SEO Social Settings] Sending AJAX data:', data); // Changed log prefix
        $.post(ajaxurl, data, function (response) {
            console.log('[SEO Social Settings] AJAX Response: ', response); // Changed log prefix
            $loadingSpinner.remove(); // Remove spinner regardless of outcome
            $previewImage.css('opacity', 1);

            if (response.success && response.data.preview_url) {
                // Update preview image source
                $previewImage.attr('src', response.data.preview_url);
                console.log(`[SEO Social Settings] Preview image src updated to: ${response.data.preview_url}`); // Changed log prefix
                console.log('[SEO Social Settings] Preview updated successfully.'); // Changed log prefix
            } else {
                // Handle error - maybe show a message?
                console.error('[SEO Social Settings] Failed to update preview.', response.data); // Changed log prefix
                // Optionally revert to a default or show the fallback URL if provided
                if (response.data && response.data.fallback_url) {
                    $previewImage.attr('src', response.data.fallback_url);
                }
                // Consider adding a user-visible error message here
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('[SEO Social Settings] AJAX request failed: ', textStatus, errorThrown); // Changed log prefix
            $loadingSpinner.remove();
            $previewImage.css('opacity', 1);
            // Handle AJAX failure - show generic error?
            console.error('[SEO Social Settings] AJAX request failed.'); // Changed log prefix
        });

    }, 500); // Debounce for 500ms

    // --- Media Uploader Initialization (Generic) --- 
    function initializeMediaUploader($container, updateCallback) {
        if (!$container.length) return;

        if (typeof wp === 'undefined' || !wp.media) {
            console.error('[SEO Social Settings] WP Media library not available.'); // Changed log prefix
            return;
        }

        let mediaFrame;
        const $input = $container.find('.image-id');
        const $preview = $container.find('.image-preview');
        const $uploadButton = $container.find('.upload-button');
        const $removeButton = $container.find('.remove-button');
        const noImageText = $preview.find('.description').length ? $preview.find('.description').text() : 'No image selected.';
        const isLogoUploader = $container.hasClass('social-logo-uploader');
        const title = isLogoUploader ? 'Select or Upload Logo' : 'Select or Upload Background Image';
        const buttonText = isLogoUploader ? 'Use this logo' : 'Use this image';

        // Clean up old handlers
        $uploadButton.off('click.cptkSeoUploader');
        $removeButton.off('click.cptkSeoUploader');

        // Attach new handlers with namespace
        $uploadButton.on('click.cptkSeoUploader', function (e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent potential conflicts

            if (typeof wp === 'undefined' || !wp.media) {
                console.error('[SEO Social Settings] wp.media not available on click!'); // Changed log prefix
                return;
            }

            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title: title,
                button: { text: buttonText },
                library: { type: 'image' },
                multiple: false
            });

            mediaFrame.on('select', function () {
                const attachment = mediaFrame.state().get('selection').first().toJSON();
                $input.val(attachment.id);
                const displayUrl = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;

                // Different preview structure for logo vs background
                if (isLogoUploader) {
                    $preview.html('<img src="' + displayUrl + '" style="max-width: 100%; max-height: 150px; height: auto; display: block;" />');
                } else {
                    $preview.html('<img src="' + displayUrl + '" style="max-width: 100%; height: auto; display: block;" />');
                }

                $uploadButton.text(isLogoUploader ? 'Change Logo' : 'Change Image');
                $removeButton.show();

                // Trigger update after selection
                if (updateCallback) updateCallback();
            });

            mediaFrame.open();
        });

        $removeButton.on('click.cptkSeoUploader', function (e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent potential conflicts
            $input.val('0'); // Use 0 for no image
            $preview.html('<span class="description" style="margin: 0;">' + noImageText + '</span>');
            $uploadButton.text(isLogoUploader ? 'Upload/Select Logo' : 'Upload/Select Image');
            $(this).hide();

            // Trigger update after removal
            if (updateCallback) updateCallback();
        });

        // Initial state check
        if ($input.val() && $input.val() !== '0') {
            $uploadButton.text(isLogoUploader ? 'Change Logo' : 'Change Image');
            $removeButton.show();
        } else {
            $uploadButton.text(isLogoUploader ? 'Upload/Select Logo' : 'Upload/Select Image');
            $removeButton.hide();
        }
    }

    // --- Initialize Specific Uploaders --- 
    initializeMediaUploader($('.social-logo-uploader'), function () { triggerServerSidePreviewUpdate('Logo Uploader'); });
    initializeMediaUploader($('.social-bg-uploader'), function () { triggerServerSidePreviewUpdate('BG Uploader'); });

    // --- Color Picker Initialization ---
    function initializeColorPicker() {
        console.log('[SEO Social Settings] Initializing color picker...'); // Changed log prefix
        const $colorPickerInput = $('#social-bg-color');

        if ($colorPickerInput.length && $.fn.wpColorPicker) {
            // Clean up previous instance if exists
            if ($colorPickerInput.closest('.wp-picker-container').length) {
                $colorPickerInput.wpColorPicker('destroy');
            }

            try {
                $colorPickerInput.wpColorPicker({
                    change: function (event, ui) {
                        console.log('[SEO Social Settings] Color picker changed'); // Changed log prefix
                        triggerServerSidePreviewUpdate('Color Picker Change');
                    },
                    clear: function () {
                        console.log('[SEO Social Settings] Color picker cleared'); // Changed log prefix
                        triggerServerSidePreviewUpdate('Color Picker Clear');
                    }
                });
                console.log('[SEO Social Settings] Color picker initialized successfully.'); // Changed log prefix
            } catch (e) {
                console.error('[SEO Social Settings] Error initializing color picker:', e); // Changed log prefix
            }
        } else {
            console.log('[SEO Social Settings] Color picker input not found or wpColorPicker not available.'); // Changed log prefix
        }

        /* // REMOVED logic for select toggle
        const $bgColorSelect = $('#social-bg-color');
        const $customColorInput = $('#social-custom-bg-color');
        const $customColorContainer = $customColorInput.closest('.wp-picker-container') || $customColorInput;

        function toggleCustomColor() {
            if ($bgColorSelect.val() === 'custom') {
                $customColorContainer.show();
            } else {
                $customColorContainer.hide();
            }
            triggerServerSidePreviewUpdate(); // Trigger on select change too
        }

        $bgColorSelect.on('change', toggleCustomColor);
        toggleCustomColor(); // Initial check
        */
    }

    // --- Opacity Slider --- 
    function initializeOpacitySlider() {
        const $slider = $('#social-bg-opacity');
        const $valueDisplay = $('#social-bg-opacity-value');

        if ($slider.length && $valueDisplay.length) {
            $slider.on('input change', function () {
                $valueDisplay.text($(this).val() + '%');
                console.log('[SEO Social Settings] Opacity slider changed'); // Changed log prefix
                triggerServerSidePreviewUpdate('Opacity Slider');
            });
        }
    }

    // --- Site Name Input --- 
    function initializeSiteNameInput() {
        $('input[name="craftedpath_seo_settings[site_name]"]').on('input', function () {
            console.log('[SEO Social Settings] Site name changed'); // Changed log prefix
            triggerServerSidePreviewUpdate('Site Name Input');
        });
    }

    // --- REMOVED Text Color Select Listener ---
    /*
    $('select[name="craftedpath_seo_settings[social_image_text_color]"]').on('change', function() {
        triggerServerSidePreviewUpdate();
    });
    */

    // --- Initialization --- 
    function initSeoSocialSettingsPage() { // Renamed init function
        console.log('[SEO Social Settings] Initializing social components...'); // Changed log prefix
        initializeColorPicker();
        initializeOpacitySlider();
        initializeSiteNameInput();
        // Uploaders are initialized earlier
        console.log('[SEO Social Settings] Social components initialized.'); // Changed log prefix
    }

    // Wait for WP Media library if needed
    if (typeof wp === 'undefined' || !wp.media) {
        console.warn('[SEO Social Settings] wp.media not ready, delaying init slightly...'); // Changed log prefix
        setTimeout(initSeoSocialSettingsPage, 500); // Delay init slightly
    } else {
        initSeoSocialSettingsPage(); // Initialize immediately
    }

}); // End document ready 