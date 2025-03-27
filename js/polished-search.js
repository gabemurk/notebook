/**
 * Polished Notebook - Search Functionality
 * Provides real-time search capabilities for the notebook application
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
            searchContainer.classList.remove('active');
            
            // Refresh notes list
            refreshNotesList();
        });
    }
    
    // Close search results when clicking outside
    document.addEventListener('click', function(event) {
        if (!searchContainer.contains(event.target)) {
            searchContainer.classList.remove('active');
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
    const searchContainer = document.getElementById('searchContainer');
    
    // Clear previous results
    searchResults.innerHTML = '';
    
    if (query.length < 2) {
        searchContainer.classList.remove('active');
        return;
    }
    
    // Show loading indicator
    searchResults.innerHTML = '<div class="search-loading">Searching...</div>';
    searchContainer.classList.add('active');
    
    // Fetch search results
    fetch(`search_notes.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            searchResults.innerHTML = '';
            
            if (data.success && data.notes.length > 0) {
                const fragment = document.createDocumentFragment();
                
                data.notes.forEach(note => {
                    const resultItem = document.createElement('div');
                    resultItem.className = 'search-result-item';
                    resultItem.innerHTML = `
                        <div class="search-result-title">${note.title}</div>
                        <div class="search-result-date">${note.updated_at}</div>
                    `;
                    
                    // Click to load the note
                    resultItem.addEventListener('click', () => {
                        loadNote(note.id);
                        searchContainer.classList.remove('active');
                    });
                    
                    fragment.appendChild(resultItem);
                });
                
                searchResults.appendChild(fragment);
            } else {
                searchResults.innerHTML = '<div class="no-results">No matching notes found</div>';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            searchResults.innerHTML = '<div class="search-error">Error searching notes</div>';
        });
}

/**
 * Refresh the notes list after a search is cleared
 */
function refreshNotesList() {
    if (typeof loadAllNotes === 'function') {
        loadAllNotes();
    }
}
