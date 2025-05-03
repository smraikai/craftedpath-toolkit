// Admin Quick Search JS - Redesign with Fuse.js

document.addEventListener('DOMContentLoaded', function () {
    console.log("Admin Quick Search JS Loaded - Fuse.js");

    const triggerButton = document.getElementById('wp-admin-bar-cpt-admin-quick-search-trigger');
    const modal = document.getElementById('cpt-admin-search-modal');
    const overlay = document.querySelector('.cpt-admin-search-overlay');
    const closeButton = document.querySelector('.cpt-admin-search-close');
    const searchInput = document.getElementById('cpt-admin-search-input');
    const resultsList = document.getElementById('cpt-admin-search-results');

    // Check if elements exist
    if (!triggerButton || !modal || !overlay || !closeButton || !searchInput || !resultsList) {
        console.warn("Admin Quick Search: Missing required elements.");
        return;
    }

    // Check if Fuse is available
    if (typeof Fuse === 'undefined') {
        console.error("Admin Quick Search: Fuse.js library not loaded.");
        resultsList.innerHTML = '<li class="cpt-admin-search-message">Error: Search library failed to load.</li>';
        return;
    }

    // Check if search items are localized
    if (typeof cptAdminSearchItems === 'undefined' || !Array.isArray(cptAdminSearchItems)) {
        console.error("Admin Quick Search: Search items not localized correctly (cptAdminSearchItems).");
        resultsList.innerHTML = '<li class="cpt-admin-search-message">Error: Search data unavailable.</li>';
        return;
    }

    // --- Fuse.js Setup ---
    const fuseOptions = {
        keys: [
            { name: 'title', weight: 0.7 }, // Give title higher weight
            { name: 'parent', weight: 0.3 }  // Parent context is less important
        ],
        includeScore: true, // Include score in results
        threshold: 0.4,     // Adjust threshold (0=exact match, 1=match anything)
        minMatchCharLength: 2, // Minimum characters to trigger search
        // includeMatches: true, // Optionally include match details for highlighting
    };
    const fuse = new Fuse(cptAdminSearchItems, fuseOptions);
    // --- End Fuse.js Setup ---

    let activeResultIndex = -1;
    let currentResults = [];

    function openModal() {
        overlay.style.display = 'block';
        modal.style.display = 'flex';
        searchInput.value = '';
        resultsList.innerHTML = '<li class="cpt-admin-search-message">Start typing to search...</li>';
        searchInput.focus();
        activeResultIndex = -1;
        currentResults = [];
        document.addEventListener('keydown', handleGlobalKeyDown);
    }

    function closeModal() {
        overlay.style.display = 'none';
        modal.style.display = 'none';
        document.removeEventListener('keydown', handleGlobalKeyDown);
    }

    function handleGlobalKeyDown(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    }

    function handleInputKeyDown(event) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            navigateResults(event.key);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            selectResult();
        }
    }

    function updateResults() {
        const query = searchInput.value.trim(); // No need for toLowerCase() with Fuse.js
        resultsList.innerHTML = '';
        activeResultIndex = -1;

        if (query.length < fuseOptions.minMatchCharLength) {
            resultsList.innerHTML = `<li class="cpt-admin-search-message">Type ${fuseOptions.minMatchCharLength} or more characters...</li>`;
            currentResults = [];
            return;
        }

        // Use Fuse.js to search
        currentResults = fuse.search(query);

        if (currentResults.length === 0) {
            resultsList.innerHTML = '<li class="cpt-admin-search-message">No results found.</li>';
        } else {
            // Fuse returns results like [{ item: {..original..}, score: 0.123 }, ...]
            currentResults.forEach((result, index) => {
                const item = result.item; // Get the original item data
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = item.url;
                if (item.target === '_blank') {
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                }

                // Icon Span
                const iconSpan = document.createElement('span');
                iconSpan.className = `result-icon dashicons ${item.icon || 'dashicons-admin-generic'}`;
                a.appendChild(iconSpan);

                // Text Container Span
                const textSpan = document.createElement('span');
                textSpan.className = 'result-text';

                // Title Span
                const titleSpan = document.createElement('span');
                titleSpan.className = 'result-title';
                titleSpan.textContent = item.title;
                textSpan.appendChild(titleSpan);

                // Parent/Context Span
                if (item.parent) {
                    const parentSpan = document.createElement('span');
                    parentSpan.className = 'result-parent';
                    parentSpan.textContent = item.parent;
                    textSpan.appendChild(parentSpan);
                }

                a.appendChild(textSpan);
                li.appendChild(a);
                li.dataset.index = index;

                a.addEventListener('click', (e) => {
                    // closeModal(); // Optional
                });

                resultsList.appendChild(li);
            });
        }
    }

    function navigateResults(key) {
        const actualResultItems = resultsList.querySelectorAll('li:not(.cpt-admin-search-message)');
        if (actualResultItems.length === 0) return;

        if (activeResultIndex >= 0) {
            actualResultItems[activeResultIndex]?.classList.remove('active');
        }

        if (key === 'ArrowDown') {
            activeResultIndex = (activeResultIndex + 1) % actualResultItems.length;
        } else if (key === 'ArrowUp') {
            activeResultIndex = (activeResultIndex - 1 + actualResultItems.length) % actualResultItems.length;
        }

        if (activeResultIndex >= 0) {
            actualResultItems[activeResultIndex]?.classList.add('active');
            actualResultItems[activeResultIndex]?.scrollIntoView({ block: 'nearest' });
        }
    }

    function selectResult() {
        const actualResultItems = resultsList.querySelectorAll('li:not(.cpt-admin-search-message)');
        if (activeResultIndex >= 0 && activeResultIndex < actualResultItems.length) {
            const link = actualResultItems[activeResultIndex]?.querySelector('a');
            if (link) {
                link.click();
                // closeModal(); // Optional
            }
        }
    }

    // --- Event Listeners ---
    triggerButton.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
    });

    document.addEventListener('keydown', function (event) {
        if (modal.style.display !== 'flex' && (event.metaKey || event.ctrlKey) && event.key === 'k') {
            event.preventDefault();
            openModal();
        }
    });

    closeButton.addEventListener('click', closeModal);
    overlay.addEventListener('click', closeModal);

    let debounceTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(updateResults, 100); // Even faster debounce for fuzzy search
    });

    searchInput.addEventListener('keydown', handleInputKeyDown);

}); 