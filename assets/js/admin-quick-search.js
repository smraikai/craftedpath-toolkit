// Admin Quick Search JS - Redesign with Fuse.js + Grouping + Highlighting

document.addEventListener('DOMContentLoaded', function () {
    console.log("Admin Quick Search JS Loaded - Grouping/Highlighting");

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
            { name: 'title', weight: 0.7 },
            { name: 'parent', weight: 0.3 }
        ],
        includeScore: true,
        includeMatches: true,
        threshold: 0.4,
        minMatchCharLength: 2,
    };
    const fuse = new Fuse(cptAdminSearchItems, fuseOptions);
    // --- End Fuse.js Setup ---

    let activeResultIndex = -1;
    let currentFlatResults = [];

    function openModal() {
        overlay.style.display = 'block';
        modal.style.display = 'flex';
        searchInput.value = '';
        resultsList.innerHTML = '';
        searchInput.focus();
        activeResultIndex = -1;
        currentFlatResults = [];
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

    // --- Highlighting Helper ---
    function applyHighlight(text, matches = [], key) {
        const result = [];
        let lastIndex = 0;

        // Filter matches for the specific key ('title' or 'parent')
        const keyMatches = matches.filter(match => match.key === key);

        // Flatten indices and sort them
        const indices = keyMatches.flatMap(match => match.indices);
        indices.sort((a, b) => a[0] - b[0]);

        // Iterate through sorted indices to build the highlighted string
        indices.forEach(([start, end]) => {
            // Add text before the match
            if (start > lastIndex) {
                result.push(document.createTextNode(text.substring(lastIndex, start)));
            }
            // Add the highlighted match
            const highlightSpan = document.createElement('span');
            highlightSpan.className = 'cpt-search-highlight';
            highlightSpan.textContent = text.substring(start, end + 1);
            result.push(highlightSpan);
            lastIndex = end + 1;
        });

        // Add any remaining text after the last match
        if (lastIndex < text.length) {
            result.push(document.createTextNode(text.substring(lastIndex)));
        }

        return result; // Return an array of nodes (text and span)
    }
    // --- End Highlighting Helper ---

    function updateResults() {
        const query = searchInput.value.trim();
        resultsList.innerHTML = '';
        activeResultIndex = -1;
        currentFlatResults = [];

        if (query.length < fuseOptions.minMatchCharLength) {
            searchInput.removeAttribute('aria-activedescendant');
            resultsList.removeAttribute('role');
            searchInput.setAttribute('aria-expanded', 'false');
            return;
        }

        const fuseResults = fuse.search(query);

        if (fuseResults.length === 0) {
            resultsList.innerHTML = '<li class="cpt-admin-search-message">No results found.</li>';
            searchInput.removeAttribute('aria-activedescendant');
            resultsList.removeAttribute('role');
            searchInput.setAttribute('aria-expanded', 'false');
            return;
        }

        // --- Grouping Logic ---
        const groupedResults = {};
        fuseResults.forEach(result => {
            const type = result.item.type || 'other'; // Default type
            if (!groupedResults[type]) {
                groupedResults[type] = [];
            }
            groupedResults[type].push(result);
        });

        // Define group order and titles
        const groupOrder = ['action', 'menu', 'page', 'post', 'user', 'other'];
        const groupTitles = {
            action: 'Quick Actions',
            menu: 'Admin Menu',
            page: 'Pages',
            post: 'Posts',
            user: 'Users',
            other: 'Other'
        };
        // --- End Grouping Logic ---

        // --- Render Grouped Results ---
        groupOrder.forEach(groupKey => {
            if (groupedResults[groupKey] && groupedResults[groupKey].length > 0) {
                // Add Group Heading
                const heading = document.createElement('li');
                heading.className = 'cpt-search-group-heading';
                heading.textContent = groupTitles[groupKey] || groupKey;
                heading.setAttribute('role', 'presentation'); // Make it clear it's not selectable
                resultsList.appendChild(heading);

                // Add Items in Group
                groupedResults[groupKey].forEach(result => {
                    const item = result.item;
                    const matches = result.matches; // Get match details
                    const li = document.createElement('li');
                    li.setAttribute('role', 'option'); // ARIA role for list items
                    li.id = `search-result-${currentFlatResults.length}`; // Unique ID for ARIA

                    const a = document.createElement('a');
                    a.href = item.url;
                    if (item.target === '_blank') {
                        a.target = '_blank';
                        a.rel = 'noopener noreferrer';
                    }

                    const iconSpan = document.createElement('span');
                    iconSpan.className = `result-icon dashicons ${item.icon || 'dashicons-admin-generic'}`;
                    a.appendChild(iconSpan);

                    const textSpan = document.createElement('span');
                    textSpan.className = 'result-text';

                    const titleSpan = document.createElement('span');
                    titleSpan.className = 'result-title';
                    // Apply highlighting to title
                    applyHighlight(item.title, matches, 'title').forEach(node => titleSpan.appendChild(node));
                    textSpan.appendChild(titleSpan);

                    if (item.parent) {
                        const parentSpan = document.createElement('span');
                        parentSpan.className = 'result-parent';
                        // Apply highlighting to parent
                        applyHighlight(item.parent, matches, 'parent').forEach(node => parentSpan.appendChild(node));
                        textSpan.appendChild(parentSpan);
                    }

                    a.appendChild(textSpan);
                    li.appendChild(a);

                    a.addEventListener('click', (e) => {
                        // closeModal();
                    });

                    resultsList.appendChild(li);
                    currentFlatResults.push(li); // Add to flat list for navigation
                });
            }
        });
        // --- End Render Grouped Results ---
        // Update ARIA attributes after rendering
        resultsList.setAttribute('role', 'listbox');
        searchInput.setAttribute('aria-expanded', 'true');
        searchInput.setAttribute('aria-controls', 'cpt-admin-search-results');
    }

    function navigateResults(key) {
        // Navigate the flat list of actual result items (li elements)
        if (currentFlatResults.length === 0) return;

        let previousActiveIndex = activeResultIndex;

        if (activeResultIndex >= 0) {
            currentFlatResults[activeResultIndex]?.classList.remove('active');
            currentFlatResults[activeResultIndex]?.removeAttribute('aria-selected');
        }

        if (key === 'ArrowDown') {
            activeResultIndex = (activeResultIndex + 1) % currentFlatResults.length;
        } else if (key === 'ArrowUp') {
            activeResultIndex = (activeResultIndex - 1 + currentFlatResults.length) % currentFlatResults.length;
        }

        if (activeResultIndex >= 0) {
            currentFlatResults[activeResultIndex]?.classList.add('active');
            currentFlatResults[activeResultIndex]?.scrollIntoView({ block: 'nearest' });
            // Update ARIA active descendant
            currentFlatResults[activeResultIndex]?.setAttribute('aria-selected', 'true');
            searchInput.setAttribute('aria-activedescendant', currentFlatResults[activeResultIndex].id);
        } else {
            searchInput.removeAttribute('aria-activedescendant');
        }
    }

    function selectResult() {
        if (activeResultIndex >= 0 && activeResultIndex < currentFlatResults.length) {
            const link = currentFlatResults[activeResultIndex]?.querySelector('a');
            if (link) {
                link.click();
                // closeModal();
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
        debounceTimer = setTimeout(updateResults, 100);
    });

    searchInput.addEventListener('keydown', handleInputKeyDown);

    // Add ARIA roles initially
    searchInput.setAttribute('role', 'combobox');
    searchInput.setAttribute('aria-autocomplete', 'list');
    searchInput.setAttribute('aria-haspopup', 'listbox');
    searchInput.setAttribute('aria-expanded', 'false');
}); 