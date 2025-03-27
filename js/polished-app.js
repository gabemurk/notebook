/**
 * Polished Notebook Application
 * A markdown note-taking application with dual database support
 */

// Global variables
let currentNoteId = 0;
let searchTimeout = null;
let lastSavedContent = '';

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    // Element references
    const editor = document.getElementById('editor');
    const preview = document.getElementById('preview');
    const editorTab = document.getElementById('editorTab');
    const previewTab = document.getElementById('previewTab');
    const editorContainer = document.getElementById('editorContainer');
    const previewContainer = document.getElementById('previewContainer');
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const searchInput = document.getElementById('searchInput');
    const newNoteBtn = document.getElementById('newNoteBtn');
    const newNoteBtn2 = document.getElementById('newNoteBtn2');
    const saveButton = document.getElementById('saveButton');
    const userNotes = document.getElementById('userNotes');
    const saveStatus = document.getElementById('saveStatus');
    const dbStatus = document.getElementById('dbStatus');
    
    // New Note Modal elements
    const newNoteModal = document.getElementById('newNoteModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelNoteBtn = document.getElementById('cancelNoteBtn');
    const createNoteBtn = document.getElementById('createNoteBtn');
    
    // Initialize editor
    initializeApp();
    
    /**
     * Initialize the application
     */
    function initializeApp() {
        // Load initial notes
        loadNotes();
        
        // Check database status
        checkDatabaseStatus();
        
        // Attach event listeners
        attachEventListeners();
        
        // Update preview initially
        updatePreview();
        
        // Add unload warning if there are unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (editor.value !== lastSavedContent) {
                // Standard text (browser will override this)
                const message = 'You have unsaved changes. Are you sure you want to leave?';
                e.returnValue = message;
                return message;
            }
        });
        
        // Show notification
        showNotification('Application loaded successfully', 'success');
    }
    
    /**
     * Check database connection status
     */
    function checkDatabaseStatus() {
        fetch('/simple_db_check.php')
            .then(response => response.json())
            .then(data => {
                const statusDot = dbStatus.querySelector('.status-dot');
                const statusText = dbStatus.querySelector('.status-text');
                
                if (data.pg_connected && data.sqlite_connected) {
                    statusDot.className = 'status-dot online';
                    statusText.textContent = 'Both Databases Connected';
                } else if (data.pg_connected) {
                    statusDot.className = 'status-dot online';
                    statusText.textContent = 'PostgreSQL Only';
                } else if (data.sqlite_connected) {
                    statusDot.className = 'status-dot warning';
                    statusText.textContent = 'SQLite Only (Fallback)';
                } else {
                    statusDot.className = 'status-dot offline';
                    statusText.textContent = 'No Database Connection';
                }
            })
            .catch(error => {
                console.error('Error checking database status:', error);
                const statusDot = dbStatus.querySelector('.status-dot');
                const statusText = dbStatus.querySelector('.status-text');
                statusDot.className = 'status-dot offline';
                statusText.textContent = 'Connection Error';
            });
    }
    
    /**
     * Attach event listeners to elements
     */
    function attachEventListeners() {
        // Tab switching
        editorTab.addEventListener('click', function() {
            editorTab.classList.add('active');
            previewTab.classList.remove('active');
            editorContainer.style.display = 'flex';
            previewContainer.style.display = 'none';
        });
        
        previewTab.addEventListener('click', function() {
            previewTab.classList.add('active');
            editorTab.classList.remove('active');
            previewContainer.style.display = 'block';
            editorContainer.style.display = 'none';
            updatePreview(); // Update preview when switching to it
        });
        
        // Editor content change
        editor.addEventListener('input', function() {
            updatePreview();
            updateSaveStatus();
        });
        
        // Sidebar toggle
        sidebarToggle.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
        
        // Search functionality
        searchInput.addEventListener('input', function() {
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            searchTimeout = setTimeout(function() {
                const searchQuery = searchInput.value.trim().toLowerCase();
                searchNotes(searchQuery);
            }, 300);
        });
        
        // Save button
        saveButton.addEventListener('click', saveNote);
        
        // New note buttons
        if (newNoteBtn) {
            newNoteBtn.addEventListener('click', showNewNoteModal);
        }
        
        if (newNoteBtn2) {
            newNoteBtn2.addEventListener('click', showNewNoteModal);
        }
        
        // Modal dialog event listeners
        closeModalBtn.addEventListener('click', hideNewNoteModal);
        cancelNoteBtn.addEventListener('click', hideNewNoteModal);
        createNoteBtn.addEventListener('click', createNewNote);
        
        // Close modal when clicking outside
        newNoteModal.addEventListener('click', function(event) {
            if (event.target === this) {
                hideNewNoteModal();
            }
        });
        
        // Handle keyboard events for modal
        document.addEventListener('keydown', function(event) {
            if (newNoteModal.classList.contains('active')) {
                if (event.key === 'Escape') {
                    hideNewNoteModal();
                } else if (event.key === 'Enter' && event.ctrlKey) {
                    createNewNote();
                }
            }
        });
    }
    
    /**
     * Update the preview with the markdown content
     */
    function updatePreview() {
        const content = editor.value;
        
        // Send to server for rendering
        fetch('/render_markdown.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'content': content
            })
        })
        .then(response => response.text())
        .then(html => {
            preview.innerHTML = html;
        })
        .catch(error => {
            console.error('Error rendering markdown:', error);
            preview.innerHTML = '<div class="error">Error rendering markdown</div>';
        });
    }
    
    /**
     * Show new note modal
     */
    function showNewNoteModal() {
        newNoteModal.classList.add('active');
        document.getElementById('noteTitle').focus();
    }
    
    /**
     * Hide new note modal
     */
    function hideNewNoteModal() {
        newNoteModal.classList.remove('active');
        document.getElementById('noteTitle').value = '';
        document.getElementById('noteCategory').value = '';
        document.getElementById('noteTemplate').value = 'blank';
    }
    
    /**
     * Create new note from modal input
     */
    function createNewNote() {
        const title = document.getElementById('noteTitle').value.trim() || 'Untitled Note';
        const category = document.getElementById('noteCategory').value.trim();
        const template = document.getElementById('noteTemplate').value;
        
        // Generate content based on template
        let content = '';
        
        // Add title as heading
        content = `# ${title}\n\n`;
        
        // Add category if provided
        if (category) {
            content += `Category: ${category}\n\n`;
        }
        
        // Add template content
        switch(template) {
            case 'basic':
                content += '## Introduction\n\nEnter your introduction here.\n\n'
                        + '## Main Content\n\nStart writing your main content here.\n\n'
                        + '## Conclusion\n\nSummarize your thoughts here.\n';
                break;
            case 'meeting':
                const today = new Date().toISOString().slice(0, 10);
                content += `## Meeting: ${today}\n\n`
                        + `### Attendees\n\n- Person 1\n- Person 2\n\n`
                        + `### Agenda\n\n1. Item 1\n2. Item 2\n\n`
                        + `### Action Items\n\n- [ ] Task 1\n- [ ] Task 2\n`;
                break;
            case 'todo':
                content += '## To-Do List\n\n'
                        + '- [ ] Task 1\n'
                        + '- [ ] Task 2\n'
                        + '- [ ] Task 3\n\n'
                        + '## Completed\n\n'
                        + '- [x] Completed task\n';
                break;
            default: // blank
                content += 'Start writing here...\n';
                break;
        }
        
        // Set content in editor
        editor.value = content;
        updatePreview();
        
        // Reset currentNoteId to create a new note
        currentNoteId = 0;
        
        // Update save status
        updateSaveStatus();
        
        // Hide modal
        hideNewNoteModal();
        
        // Focus editor
        editor.focus();
        
        // Show notification
        showNotification('New note created! Don\'t forget to save.', 'info');
    }
    
    /**
     * Load notes from server
     */
    function loadNotes() {
        fetch('/load_notes.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotes(data.notes);
                } else {
                    userNotes.innerHTML = '<div class="note-error">Error loading notes: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading notes:', error);
                userNotes.innerHTML = '<div class="note-error">Error connecting to server</div>';
            });
    }
    
    /**
     * Display notes in the sidebar
     */
    function displayNotes(notes) {
        userNotes.innerHTML = '';
        
        if (notes.length === 0) {
            userNotes.innerHTML = '<div class="no-notes">No notes found</div>';
            return;
        }
        
        notes.forEach(note => {
            const noteItem = document.createElement('div');
            noteItem.className = 'note-item';
            noteItem.setAttribute('data-id', note.id);
            noteItem.innerHTML = `
                <div class="note-title">${note.title}</div>
                <div class="note-date">${note.updated_at}</div>
            `;
            
            noteItem.addEventListener('click', function() {
                document.querySelectorAll('.note-item').forEach(item => {
                    item.classList.remove('active');
                });
                this.classList.add('active');
                loadNote(note.id);
            });
            
            userNotes.appendChild(noteItem);
        });
    }
    
    /**
     * Load a specific note by ID
     */
    function loadNote(noteId) {
        fetch(`/load_note.php?id=${noteId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    editor.value = data.content;
                    currentNoteId = noteId;
                    lastSavedContent = data.content;
                    updatePreview();
                    updateSaveStatus();
                    
                    // Show notification
                    showNotification('Note loaded successfully', 'success');
                } else {
                    showNotification('Error loading note: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error loading note:', error);
                showNotification('Error connecting to server', 'error');
            });
    }
    
    /**
     * Search notes based on query
     */
    function searchNotes(query) {
        if (!query) {
            loadNotes(); // If empty query, load all notes
            return;
        }
        
        fetch(`/search_notes.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotes(data.notes);
                } else {
                    userNotes.innerHTML = '<div class="note-error">Error searching notes: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error searching notes:', error);
                userNotes.innerHTML = '<div class="note-error">Error connecting to server</div>';
            });
    }
    
    /**
     * Save the current note
     */
    function saveNote() {
        const content = editor.value;
        
        if (!content.trim()) {
            showNotification('Cannot save empty note!', 'warning');
            return;
        }
        
        // Extract title from first heading or use default
        const titleMatch = content.match(/^# (.+)$/m);
        const title = titleMatch ? titleMatch[1] : 'Untitled Note';
        
        // Update save status
        const statusDot = saveStatus.querySelector('.status-dot');
        const statusText = saveStatus.querySelector('span:not(.status-dot)');
        statusDot.className = 'status-dot syncing';
        statusText.textContent = 'Saving...';
        saveButton.disabled = true;
        
        // Send to server
        fetch('/save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'content': content,
                'title': title,
                'note_id': currentNoteId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentNoteId = data.note_id;
                lastSavedContent = content;
                
                // Update status
                statusDot.className = 'status-dot online';
                statusText.textContent = 'Saved';
                
                // Update notes list to include new note
                loadNotes();
                
                // Show notification
                showNotification('Note saved successfully', 'success');
            } else {
                // Update status
                statusDot.className = 'status-dot offline';
                statusText.textContent = 'Save Failed';
                
                // Show notification
                showNotification('Error saving note: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error saving note:', error);
            
            // Update status
            statusDot.className = 'status-dot offline';
            statusText.textContent = 'Connection Error';
            
            // Show notification
            showNotification('Error connecting to server', 'error');
        })
        .finally(() => {
            saveButton.disabled = false;
        });
    }
    
    /**
     * Update the save status based on changes
     */
    function updateSaveStatus() {
        const statusDot = saveStatus.querySelector('.status-dot');
        const statusText = saveStatus.querySelector('span:not(.status-dot)');
        
        if (editor.value !== lastSavedContent) {
            statusDot.className = 'status-dot warning';
            statusText.textContent = 'Unsaved Changes';
        } else {
            statusDot.className = 'status-dot online';
            statusText.textContent = 'Saved';
        }
    }
    
    /**
     * Show a notification message
     */
    function showNotification(message, type = 'info') {
        // Create notification container if it doesn't exist
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            document.body.appendChild(container);
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span>${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;
        
        // Add to container
        container.appendChild(notification);
        
        // Add close event
        notification.querySelector('.notification-close').addEventListener('click', function() {
            container.removeChild(notification);
        });
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode === container) {
                container.removeChild(notification);
            }
        }, 5000);
    }
    
    // Make functions available globally
    window.saveNote = saveNote;
    window.loadNote = loadNote;
    window.updatePreview = updatePreview;
});
