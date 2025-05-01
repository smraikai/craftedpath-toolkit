jQuery(document).ready(function ($) {
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
}); 