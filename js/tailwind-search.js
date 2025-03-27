/**
 * Tailwind Notebook - Search Functionality
 * Provides modern responsive search capabilities for the Tailwind-styled notebook application
 */

// Add event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const searchContainer = document.getElementById('searchContainer');
    const clearSearchBtn = document.getElementById('clearSearch');
    
    if (!searchInput || !searchResults) return;
    
    // Search input event listeners
    searchInput.addEventListener('input', debounce(handleSearch, 300));
    searchInput.addEventListener('focus', function() {
        searchContainer.classList.add('active');
    });
    
    // Clear search button
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            searchResults.innerHTML = '';
            searchResults.classList.add('hidden');
            
            // Refresh notes list
            refreshNotesList();
        });
    }
    
    // Close search results when clicking outside
    document.addEventListener('click', function(event) {
        if (!searchContainer.contains(event.target)) {
            searchResults.classList.add('hidden');
        }
    });
});

/**
 * Debounce function to limit how often a function is called
 */
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

/**
 * Handle search input and display results
 */
function handleSearch(event) {
    const query = event.target.value.trim();
    const searchResults = document.getElementById('searchResults');
    
    // Clear previous results
    searchResults.innerHTML = '';
    
    if (query.length < 2) {
        searchResults.classList.add('hidden');
        return;
    }
    
    // Show loading indicator
    searchResults.classList.remove('hidden');
    searchResults.innerHTML = '<div class="p-4 text-sm text-center text-gray-500 dark:text-gray-400">Searching...</div>';
    
    // Fetch search results
    fetch(`search_notes.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            searchResults.innerHTML = '';
            
            if (data.success && data.notes.length > 0) {
                const fragment = document.createDocumentFragment();
                
                data.notes.forEach(note => {
                    const resultItem = document.createElement('div');
                    resultItem.className = 'p-3 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-200 dark:border-gray-700 last:border-b-0';
                    resultItem.innerHTML = `
                        <div class="font-medium text-gray-800 dark:text-gray-200">${note.title}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">${note.updated_at}</div>
                    `;
                    
                    // Click to load the note
                    resultItem.addEventListener('click', () => {
                        loadNote(note.id);
                        searchResults.classList.add('hidden');
                        searchInput.value = '';
                    });
                    
                    fragment.appendChild(resultItem);
                });
                
                searchResults.appendChild(fragment);
            } else {
                searchResults.innerHTML = '<div class="p-4 text-sm text-center text-gray-500 dark:text-gray-400">No matching notes found</div>';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            searchResults.innerHTML = '<div class="p-4 text-sm text-center text-red-500 dark:text-red-400">Error searching notes</div>';
        });
}

/**
 * Refresh the notes list after a search is cleared
 */
function refreshNotesList() {
    if (typeof loadNotes === 'function') {
        loadNotes();
    }
}
