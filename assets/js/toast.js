jQuery(document).ready(function ($) {
    console.log('Toast.js initialized'); // Debug log

    // Function to create the toast container if it doesn't exist
    function getOrCreateToastContainer() {
        let container = $('#cpt-toast-container');
        if (container.length === 0) {
            container = $('<div id="cpt-toast-container" class="cpt-toast-container"></div>');
            $('body').append(container);
        }
        return container;
    }

    // Function to show a toast message
    // type can be 'success', 'error', 'warning', 'info' (or default)
    // duration is in milliseconds (0 means it won't auto-hide)
    window.showCPTToast = function (message, type = 'info', duration = 5000) {
        console.log('showCPTToast called:', message, type); // Debug log
        const container = getOrCreateToastContainer();

        const toastId = 'cpt-toast-' + Date.now(); // Unique ID for the toast
        const toast = $(`
            <div id="${toastId}" class="cpt-toast ${type}">
                <span class="cpt-toast-message">${message}</span>
                <button class="cpt-toast-close">&times;</button>
            </div>
        `);

        container.append(toast);

        // Trigger the animation
        setTimeout(() => {
            toast.addClass('show');
        }, 10);

        // Auto-hide logic
        if (duration > 0) {
            setTimeout(() => {
                hideToast(toast);
            }, duration);
        }

        // Close button logic
        toast.find('.cpt-toast-close').on('click', function () {
            hideToast(toast);
        });
    }

    // Function to hide a specific toast
    function hideToast(toastElement) {
        toastElement.removeClass('show');
        // Remove the element after the transition completes
        toastElement.on('transitionend webkitTransitionEnd oTransitionEnd', function () {
            $(this).remove();
        });
        // Fallback removal if transitionend doesn't fire (e.g., if transitions are disabled)
        setTimeout(() => {
            if (toastElement.parent().length) { // Check if it hasn't been removed already
                toastElement.remove();
            }
        }, 600); // Slightly longer than the CSS transition
    }

    // --- Example Usage (Remove or comment out for production) ---
    // showCPTToast('This is a success message.', 'success');
    // showCPTToast('This is an error message.', 'error', 0); // Won't auto-hide
    // showCPTToast('This is a warning.', 'warning', 3000);
    // showCPTToast('Just some information.', 'info');
    // -----------------------------------------------------------

});
