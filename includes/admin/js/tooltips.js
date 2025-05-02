// Tooltip initialization using Floating UI
document.addEventListener('DOMContentLoaded', () => {
    const { computePosition, offset, shift, flip, arrow } = FloatingUIDOM; // Use FloatingUIDOM global

    const tooltipElements = document.querySelectorAll('[data-tooltip-content]');
    const tooltip = document.createElement('div');
    const tooltipArrow = document.createElement('div');

    tooltip.id = 'cptk-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltipArrow.id = 'cptk-tooltip-arrow';
    tooltip.appendChild(tooltipArrow);

    let currentTarget = null;
    let hideTimeout;

    function updateTooltipPosition() {
        if (!currentTarget) return;

        computePosition(currentTarget, tooltip, {
            placement: 'top', // Start with top placement
            middleware: [
                offset(8), // Add some space between the target and tooltip
                flip(),    // Flip to bottom if not enough space on top
                shift({ padding: 5 }), // Shift horizontally to stay in view
                arrow({ element: tooltipArrow }), // Manage arrow positioning
            ],
        }).then(({ x, y, placement, middlewareData }) => {
            // Position the tooltip
            Object.assign(tooltip.style, {
                left: `${x}px`,
                top: `${y}px`,
            });

            // Position the arrow
            if (middlewareData.arrow) {
                const { x: arrowX, y: arrowY } = middlewareData.arrow;

                // Determine side based on placement (top/bottom)
                const staticSide = {
                    top: 'bottom',
                    right: 'left',
                    bottom: 'top',
                    left: 'right',
                }[placement.split('-')[0]];

                Object.assign(tooltipArrow.style, {
                    left: arrowX != null ? `${arrowX}px` : '',
                    top: arrowY != null ? `${arrowY}px` : '',
                    right: '',
                    bottom: '',
                    [staticSide]: '-4px', // Position arrow slightly outside the tooltip
                });
            }
        });
    }

    function showTooltip(event) {
        clearTimeout(hideTimeout);
        currentTarget = event.currentTarget;
        tooltip.textContent = currentTarget.dataset.tooltipContent;
        tooltip.appendChild(tooltipArrow); // Ensure arrow is still appended
        document.body.appendChild(tooltip);
        tooltip.style.display = 'block';
        updateTooltipPosition();
    }

    function hideTooltip() {
        hideTimeout = setTimeout(() => {
            tooltip.style.display = 'none';
            currentTarget = null;
            if (tooltip.parentNode === document.body) {
                document.body.removeChild(tooltip);
            }
        }, 100); // Small delay before hiding
    }

    // Attach listeners
    tooltipElements.forEach(el => {
        el.addEventListener('mouseenter', showTooltip);
        el.addEventListener('focus', showTooltip); // Also show on focus
        el.addEventListener('mouseleave', hideTooltip);
        el.addEventListener('blur', hideTooltip); // Hide on blur
    });

    // Clear timeout if hovering over the tooltip itself
    tooltip.addEventListener('mouseenter', () => clearTimeout(hideTimeout));
    tooltip.addEventListener('mouseleave', hideTooltip);
}); 