// Admin Quick Search JS - Redesign

document.addEventListener('DOMContentLoaded', function () {
    console.log("Admin Quick Search JS Loaded - Redesign");

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

    // Check if search items are localized
    if (typeof cptAdminSearchItems === 'undefined' || !Array.isArray(cptAdminSearchItems)) {
        console.error("Admin Quick Search: Search items not localized correctly (cptAdminSearchItems).");
        resultsList.innerHTML = '<li class="cpt-admin-search-message">Error: Search data unavailable.</li>';
        return; // Stop execution
    }

    let activeResultIndex = -1; // For keyboard navigation
    let currentResults = []; // Hold the currently filtered results

    function openModal() {
        overlay.style.display = 'block';
        modal.style.display = 'flex';
        searchInput.value = '';
        resultsList.innerHTML = '<li class="cpt-admin-search-message">Start typing to search...</li>'; // Initial message
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

    // Renamed to avoid conflict with input keydown
    function handleGlobalKeyDown(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
        // Keyboard nav handled by input's keydown listener
    }

    // Specific handler for input/results list nav
    function handleInputKeyDown(event) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            navigateResults(event.key);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            selectResult();
        }
        // Allow Escape to bubble up to handleGlobalKeyDown
    }

    function updateResults() {
        const query = searchInput.value.toLowerCase().trim();
        resultsList.innerHTML = ''; // Clear previous results
        activeResultIndex = -1;

        if (query.length < 1) { // Allow searching from 1 character
            resultsList.innerHTML = '<li class="cpt-admin-search-message">Start typing to search...</li>';
            currentResults = [];
            return;
        }

        // Basic filtering (can be improved with fuzzy search library later)
        currentResults = cptAdminSearchItems.filter(item => {
            const titleMatch = item.title.toLowerCase().includes(query);
            const parentMatch = item.parent && item.parent.toLowerCase().includes(query);
            const typeMatch = item.type.toLowerCase().includes(query); // Allow searching type e.g., "action"
            return titleMatch || parentMatch || typeMatch;
        });

        if (currentResults.length === 0) {
            resultsList.innerHTML = '<li class="cpt-admin-search-message">No results found.</li>';
        } else {
            currentResults.forEach((item, index) => {
                const li = document.createElement('li');
                // Create the anchor tag FIRST
                const a = document.createElement('a');
                a.href = item.url;
                if (item.target === '_blank') {
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer'; // Security for target blank
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

                // Append Text container to Anchor
                a.appendChild(textSpan);

                // Append Anchor to List Item
                li.appendChild(a);
                li.dataset.index = index; // Store index for navigation

                // Click handler for the anchor
                a.addEventListener('click', (e) => {
                    // In future, could check item.type === 'action' and prevent default/do AJAX
                    // console.log(`Clicked: ${item.title} (Type: ${item.type})`);
                    // closeModal(); // Optional: close modal after click
                });

                // Append List Item to the Results List
                resultsList.appendChild(li);
            });
        }
    }

    function navigateResults(key) {
        const resultsItems = resultsList.querySelectorAll('li'); // Select the li elements
        // Ensure we only navigate actual results, not messages
        const actualResultItems = Array.from(resultsItems).filter(li => !li.classList.contains('cpt-admin-search-message'));
        if (actualResultItems.length === 0) return;

        // Remove active class from previously active item
        if (activeResultIndex >= 0 && activeResultIndex < actualResultItems.length) {
            actualResultItems[activeResultIndex].classList.remove('active');
        }

        if (key === 'ArrowDown') {
            activeResultIndex = (activeResultIndex + 1) % actualResultItems.length;
        } else if (key === 'ArrowUp') {
            activeResultIndex = (activeResultIndex - 1 + actualResultItems.length) % actualResultItems.length;
        }

        // Add active class to the new item
        if (activeResultIndex >= 0 && activeResultIndex < actualResultItems.length) {
            actualResultItems[activeResultIndex].classList.add('active');
            actualResultItems[activeResultIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    function selectResult() {
        const resultsItems = resultsList.querySelectorAll('li');
        const actualResultItems = Array.from(resultsItems).filter(li => !li.classList.contains('cpt-admin-search-message'));

        if (activeResultIndex >= 0 && activeResultIndex < actualResultItems.length) {
            const link = actualResultItems[activeResultIndex].querySelector('a');
            if (link) {
                link.click(); // Simulate click on the active item's link
                // closeModal(); // Optional: close modal after selection
            }
        }
    }

    // --- Event Listeners ---

    // Open modal via trigger button
    triggerButton.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
    });

    // Open modal via keyboard shortcut (Cmd/Ctrl + K)
    document.addEventListener('keydown', function (event) {
        // Ensure modal isn't already open
        if (modal.style.display !== 'flex' && (event.metaKey || event.ctrlKey) && event.key === 'k') {
            event.preventDefault();
            openModal();
        }
    });

    // Close modal
    closeButton.addEventListener('click', closeModal);
    overlay.addEventListener('click', closeModal);

    // Update results on input (debounced)
    let debounceTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(updateResults, 150); // Faster debounce
    });

    // Add keyboard navigation specifically to the input field
    searchInput.addEventListener('keydown', handleInputKeyDown);

}); 