/**
 * Tailwind Notebook - Database Functions
 * 
 * Handles all database interactions using the dual database system (PostgreSQL/SQLite)
 */

// Global variables
// Use window.currentNoteId to share variable across files
if (typeof window.currentNoteId === 'undefined') {
    window.currentNoteId = null;
}
// Use window.lastSavedContent to share variable across files
if (typeof window.lastSavedContent === 'undefined') {
    window.lastSavedContent = '';
}

// Initialize database status check when document is ready
document.addEventListener('DOMContentLoaded', function() {
    checkDatabaseStatus();
    
    // Set up interval for periodic status check (every 30 seconds)
    setInterval(checkDatabaseStatus, 30000);
});

/**
 * Check database status and update the UI accordingly
 */
function checkDatabaseStatus() {
    fetch('/database/get_db_status.php')
        .then(response => response.json())
        .then(data => {
            const dbStatusElement = document.getElementById('dbStatus');
            const statusDot = dbStatusElement.querySelector('.status-dot');
            const statusText = dbStatusElement.querySelector('span:not(.status-dot)');
            
            dbStatusElement.classList.remove('hidden');
            dbStatusElement.classList.add('sm:flex');
            
            if (data.pg_connected === true && data.sqlite_connected === true) {
                // Both databases connected
                statusDot.classList.remove('bg-red-500', 'bg-yellow-500', 'bg-gray-500');
                statusDot.classList.add('bg-green-500');
                statusText.textContent = 'PostgreSQL + SQLite';
            } else if (data.pg_connected === true) {
                // Only PostgreSQL connected
                statusDot.classList.remove('bg-red-500', 'bg-green-500', 'bg-gray-500');
                statusDot.classList.add('bg-yellow-500');
                statusText.textContent = 'PostgreSQL Only';
            } else if (data.sqlite_connected === true) {
                // Only SQLite connected
                statusDot.classList.remove('bg-red-500', 'bg-green-500', 'bg-gray-500');
                statusDot.classList.add('bg-yellow-500');
                statusText.textContent = 'SQLite Only';
            } else {
                // No database connected
                statusDot.classList.remove('bg-green-500', 'bg-yellow-500', 'bg-gray-500');
                statusDot.classList.add('bg-red-500');
                statusText.textContent = 'DB Connection Error';
            }
        })
        .catch(error => {
            console.error('Error checking database status:', error);
            // Show error state in UI
            const dbStatusElement = document.getElementById('dbStatus');
            const statusDot = dbStatusElement.querySelector('.status-dot');
            const statusText = dbStatusElement.querySelector('span:not(.status-dot)');
            
            statusDot.classList.remove('bg-green-500', 'bg-yellow-500', 'bg-gray-500');
            statusDot.classList.add('bg-red-500');
            statusText.textContent = 'DB Connection Error';
            
            showNotification('Error checking database status', 'error');
        });
}

/**
 * Load notes from database
 * Uses dual database system
 */
function loadNotes() {
    fetch('/tailwind-load-notes.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                showNotification(data.error, 'error');
                return;
            }
            
            const notesContainer = document.getElementById('userNotes');
            notesContainer.innerHTML = '';
            
            if (data.notes.length === 0) {
                notesContainer.innerHTML = '<div class="p-4 text-gray-400 text-sm text-center">No notes found. Create a new note to get started!</div>';
                return;
            }
            
            // Sort notes by last_updated (most recent first)
            data.notes.sort((a, b) => new Date(b.last_updated) - new Date(a.last_updated));
            
            // Group notes by category
            const notesByCategory = {};
            data.notes.forEach(note => {
                const category = note.category || 'Uncategorized';
                if (!notesByCategory[category]) {
                    notesByCategory[category] = [];
                }
                notesByCategory[category].push(note);
            });
            
            // Render notes by category
            Object.keys(notesByCategory).sort().forEach(category => {
                const categoryNotes = notesByCategory[category];
                
                // Create category section
                const categorySection = document.createElement('div');
                categorySection.className = 'mb-2';
                
                // Create category header
                const categoryHeader = document.createElement('div');
                categoryHeader.className = 'px-3 py-2 text-xs font-medium text-gray-400 border-b border-gray-700';
                categoryHeader.textContent = category;
                categorySection.appendChild(categoryHeader);
                
                // Create notes list for this category
                const notesList = document.createElement('div');
                notesList.className = 'notes-list';
                
                categoryNotes.forEach(note => {
                    const noteItem = document.createElement('div');
                    noteItem.className = 'note-item px-3 py-2 border-b border-gray-700 hover:bg-gray-700 cursor-pointer transition-colors';
                    noteItem.setAttribute('data-id', note.id);
                    
                    const noteTitle = document.createElement('div');
                    noteTitle.className = 'font-medium text-sm truncate';
                    noteTitle.textContent = note.title;
                    
                    const noteInfo = document.createElement('div');
                    noteInfo.className = 'text-xs text-gray-400 mt-1 flex justify-between';
                    
                    const lastUpdated = new Date(note.last_updated);
                    const formattedDate = lastUpdated.toLocaleDateString();
                    
                    noteInfo.innerHTML = `
                        <span>${formattedDate}</span>
                        <span>${note.content.length} chars</span>
                    `;
                    
                    noteItem.appendChild(noteTitle);
                    noteItem.appendChild(noteInfo);
                    
                    // Add click event to load the note
                    noteItem.addEventListener('click', () => {
                        loadNote(note.id);
                    });
                    
                    notesList.appendChild(noteItem);
                });
                
                categorySection.appendChild(notesList);
                notesContainer.appendChild(categorySection);
            });
        })
        .catch(error => {
            console.error('Error loading notes:', error);
            showNotification('Failed to load notes', 'error');
        });
}

/**
 * Load a specific note by ID
 */
function loadNote(noteId) {
    // Update UI to show loading state
    const saveStatusElement = document.getElementById('saveStatus');
    saveStatusElement.innerHTML = `
        <span class="mr-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
        </span>
        <span>Loading...</span>
    `;
    
    // Clear previous selected note
    const noteItems = document.querySelectorAll('.note-item');
    noteItems.forEach(item => {
        item.classList.remove('bg-gray-700');
    });
    
    fetch(`/tailwind-load-note.php?id=${noteId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                showNotification(data.error, 'error');
                updateSaveStatus('error');
                return;
            }
            
            // Update UI
            const editor = document.getElementById('editor');
            editor.value = data.content;
            updateLineNumbers();
            updatePreview();
            
            // Highlight selected note
            const selectedNote = document.querySelector(`.note-item[data-id="${noteId}"]`);
            if (selectedNote) {
                selectedNote.classList.add('bg-gray-700');
                selectedNote.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Update current note ID
            window.currentNoteId = noteId;
            window.lastSavedContent = data.content;
            
            // Update save status
            updateSaveStatus('ready');
        })
        .catch(error => {
            console.error('Error loading note:', error);
            showNotification('Failed to load note', 'error');
            updateSaveStatus('error');
        });
}

/**
 * Save the current note
 */
function saveNote() {
    if (!window.currentNoteId) {
        showNotification('No note selected to save', 'warning');
        return;
    }
    
    const content = document.getElementById('editor').value;
    
    // Don't save if content hasn't changed
    if (content === window.lastSavedContent) {
        showNotification('No changes to save', 'info');
        return;
    }
    
    // Update UI to show saving state
    updateSaveStatus('saving');
    
    const formData = new FormData();
    formData.append('id', window.currentNoteId);
    formData.append('content', content);
    
    fetch('/tailwind-save-note.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.error) {
            showNotification(data.error, 'error');
            updateSaveStatus('error');
            return;
        }
        
        window.lastSavedContent = content;
        showNotification('Note saved successfully', 'success');
        updateSaveStatus('saved');
        
        // Refresh notes list to update last modified time
        loadNotes();
    })
    .catch(error => {
        console.error('Error saving note:', error);
        showNotification('Failed to save note', 'error');
        updateSaveStatus('error');
    });
}

/**
 * Create a new note
 */
function createNewNote(title, category) {
    // Update UI to show saving state
    updateSaveStatus('saving');
    
    const formData = new FormData();
    formData.append('title', title);
    formData.append('category', category || '');
    
    fetch('/tailwind-create-note.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            showNotification(data.message || 'Error creating note', 'error');
            updateSaveStatus('error');
            return;
        }
        
        // Note created successfully
        showNotification('New note created', 'success');
        
        // Log success data for debugging
        console.log('Create note response:', data);
        
        // Load the new note
        loadNotes();
        loadNote(data.note_id);
        
        // Apply template
        applyTemplate(document.getElementById('noteTemplate').value);
        
        // Update save status
        updateSaveStatus('ready');
    })
    .catch(error => {
        console.error('Error creating note:', error);
        showNotification('Failed to create note', 'error');
        updateSaveStatus('error');
    });
}

/**
 * Search notes
 */
function searchNotes(query) {
    if (!query.trim()) {
        return;
    }
    
    fetch(`/tailwind-search-notes.php?q=${encodeURIComponent(query)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const searchResults = document.getElementById('searchResults');
            searchResults.innerHTML = '';
            
            if (data.error) {
                searchResults.innerHTML = `<div class="p-3 text-sm text-red-400">${data.error}</div>`;
                searchResults.classList.remove('hidden');
                return;
            }
            
            if (data.results.length === 0) {
                searchResults.innerHTML = '<div class="p-3 text-sm text-gray-400">No results found</div>';
                searchResults.classList.remove('hidden');
                return;
            }
            
            // Display results
            data.results.forEach(note => {
                const resultItem = document.createElement('div');
                resultItem.className = 'p-2 hover:bg-gray-700 cursor-pointer border-b border-gray-700 last:border-0';
                resultItem.setAttribute('data-id', note.id);
                
                const noteTitle = document.createElement('div');
                noteTitle.className = 'font-medium text-sm';
                noteTitle.textContent = note.title;
                
                const noteInfo = document.createElement('div');
                noteInfo.className = 'text-xs text-gray-400 mt-1';
                noteInfo.textContent = note.category || 'Uncategorized';
                
                resultItem.appendChild(noteTitle);
                resultItem.appendChild(noteInfo);
                
                // Add click event to load the note
                resultItem.addEventListener('click', () => {
                    loadNote(note.id);
                    searchResults.classList.add('hidden');
                    document.getElementById('searchInput').value = '';
                });
                
                searchResults.appendChild(resultItem);
            });
            
            searchResults.classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error searching notes:', error);
            const searchResults = document.getElementById('searchResults');
            searchResults.innerHTML = '<div class="p-3 text-sm text-red-400">Error searching notes</div>';
            searchResults.classList.remove('hidden');
        });
}

/**
 * Update save status UI
 */
function updateSaveStatus(status) {
    const saveStatusElement = document.getElementById('saveStatus');
    saveStatusElement.classList.remove('hidden');
    saveStatusElement.classList.add('sm:flex');
    
    switch (status) {
        case 'ready':
            saveStatusElement.innerHTML = `
                <span class="mr-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                </span>
                <span>Ready</span>
            `;
            break;
        case 'saving':
            saveStatusElement.innerHTML = `
                <span class="mr-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </span>
                <span>Saving...</span>
            `;
            break;
        case 'saved':
            saveStatusElement.innerHTML = `
                <span class="mr-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </span>
                <span>Saved</span>
            `;
            setTimeout(() => {
                updateSaveStatus('ready');
            }, 2000);
            break;
        case 'error':
            saveStatusElement.innerHTML = `
                <span class="mr-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </span>
                <span>Error</span>
            `;
            break;
        default:
            break;
    }
}

/**
 * Show a notification message
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, error, info, warning)
 */
function showNotification(message, type = 'info') {
    const container = document.getElementById('notification-container');
    if (!container) return;
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification mb-3 p-3 rounded-lg shadow-lg border-l-4 transition-all transform duration-300 ease-in-out`;
    
    // Set color based on type
    switch (type) {
        case 'success':
            notification.classList.add('bg-green-100', 'text-green-800', 'border-green-500', 'dark:bg-green-800', 'dark:text-green-100');
            break;
        case 'error':
            notification.classList.add('bg-red-100', 'text-red-800', 'border-red-500', 'dark:bg-red-800', 'dark:text-red-100');
            break;
        case 'warning':
            notification.classList.add('bg-yellow-100', 'text-yellow-800', 'border-yellow-500', 'dark:bg-yellow-800', 'dark:text-yellow-100');
            break;
        case 'info':
        default:
            notification.classList.add('bg-blue-100', 'text-blue-800', 'border-blue-500', 'dark:bg-blue-800', 'dark:text-blue-100');
            break;
    }
    
    // Create content
    notification.innerHTML = `
        <div class="flex justify-between items-center">
            <div>${message}</div>
            <button class="ml-3 text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100">
                &times;
            </button>
        </div>
    `;
    
    // Add to container
    container.appendChild(notification);
    
    // Add animation
    setTimeout(() => {
        notification.classList.add('translate-x-0', 'opacity-100');
    }, 10);
    
    // Add close button functionality
    const closeButton = notification.querySelector('button');
    closeButton.addEventListener('click', () => {
        removeNotification(notification);
    });
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        removeNotification(notification);
    }, 5000);
}

/**
 * Remove a notification with animation
 * @param {HTMLElement} notification - The notification element to remove
 */
function removeNotification(notification) {
    notification.classList.add('opacity-0', 'translate-x-full');
    setTimeout(() => {
        notification.remove();
    }, 300);
}
