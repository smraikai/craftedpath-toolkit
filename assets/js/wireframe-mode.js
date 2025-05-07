/**
 * Wireframe Mode JavaScript
 * Handles wireframe mode functionality including image replacement
 */

class WireframeMode {
    constructor() {
        // Create SVG data URI with specified colors and smaller icon
        const svgContent = `<svg width="100%" height="100%" viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="mountainGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#4E4E4E"/>
                    <stop offset="100%" style="stop-color:#818181"/>
                </linearGradient>
                <pattern id="wavyPattern" x="0" y="0" width="20" height="100" patternUnits="userSpaceOnUse">
                    <path d="M10 0 Q -10 25, 10 50 T 10 100" stroke="#F7F7F7" stroke-width="1" fill="none"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="#DDDDDD"/>
            <rect width="100%" height="100%" fill="url(#wavyPattern)"/>
            <g transform="translate(192, 192) scale(0.25)">
                <path d="M256 32c12.5 0 24.1 6.4 30.8 17L503.4 394.4c5.6 8.9 8.6 19.2 8.6 29.7c0 30.9-25 55.9-55.9 55.9L55.9 480C25 480 0 455 0 424.1c0-10.5 3-20.8 8.6-29.7L225.2 49c6.6-10.6 18.3-17 30.8-17zm65 192L256 120.4 176.9 246.5l18.3 24.4c6.4 8.5 19.2 8.5 25.6 0l25.6-34.1c6-8.1 15.5-12.8 25.6-12.8l49 0z" fill="url(#mountainGradient)"/>
            </g>
        </svg>`;

        this.placeholderImage = `data:image/svg+xml,${encodeURIComponent(svgContent)}`;
        this.originalImages = new Map();
    }

    init() {
        this.replaceImages();
        this.observeDOMChanges();
    }

    replaceImages() {
        // Replace all images
        document.querySelectorAll('img').forEach(img => {
            if (!this.originalImages.has(img)) {
                this.originalImages.set(img, img.src);
                img.src = this.placeholderImage;
            }
        });

        // Replace background images
        document.querySelectorAll('*').forEach(element => {
            const bgImage = window.getComputedStyle(element).backgroundImage;
            if (bgImage && bgImage !== 'none') {
                element.style.backgroundImage = `url('${this.placeholderImage}')`;
            }
        });
    }

    observeDOMChanges() {
        // Create a MutationObserver to watch for new images
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length) {
                    this.replaceImages();
                }
            });
        });

        // Start observing the document with the configured parameters
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Method to restore original images (if needed)
    restoreImages() {
        this.originalImages.forEach((originalSrc, img) => {
            img.src = originalSrc;
        });
        this.originalImages.clear();
    }
}

// Initialize wireframe mode when the DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const wireframeMode = new WireframeMode();
    wireframeMode.init();
}); 