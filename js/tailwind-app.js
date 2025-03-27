/**
 * Tailwind Notebook Application
 * A responsive markdown note-taking application with dual database support
 */

// Global variables
// Note: currentNoteId is declared once at the window level to prevent redeclaration
if (typeof window.currentNoteId === 'undefined') {
    window.currentNoteId = 0;
}
// Use window.lastSavedContent to share variable across files
if (typeof window.lastSavedContent === 'undefined') {
    window.lastSavedContent = '';
}
let searchTimeout = null;

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
            if (editor.value !== window.lastSavedContent) {
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
                const statusText = dbStatus.querySelector('span:not(.status-dot)');
                
                if (data.pg_connected && data.sqlite_connected) {
                    statusDot.className = 'status-dot online';
                    statusText.textContent = 'Both DBs Connected';
                } else if (data.pg_connected) {
                    statusDot.className = 'status-dot online';
                    statusText.textContent = 'PostgreSQL Only';
                } else if (data.sqlite_connected) {
                    statusDot.className = 'status-dot warning';
                    statusText.textContent = 'SQLite Only';
                } else {
                    statusDot.className = 'status-dot offline';
                    statusText.textContent = 'No DB Connection';
                }
            })
            .catch(error => {
                console.error('Error checking database status:', error);
                const statusDot = dbStatus.querySelector('.status-dot');
                const statusText = dbStatus.querySelector('span:not(.status-dot)');
                statusDot.className = 'status-dot offline';
                statusText.textContent = 'Connection Error';
            });
    }
    
    /**
     * Attach event listeners to UI elements
     */
    function attachEventListeners() {
        // Editor input event
        editor.addEventListener('input', function() {
            updatePreview();
            updateSaveStatus();
        });
        
        // Tab switching
        editorTab.addEventListener('click', function() {
            editorTab.classList.add('bg-white', 'dark:bg-gray-800');
            editorTab.classList.remove('bg-gray-100', 'dark:bg-gray-600');
            previewTab.classList.remove('bg-white', 'dark:bg-gray-800');
            previewTab.classList.add('bg-gray-100', 'dark:bg-gray-600');
            editorContainer.classList.remove('hidden');
            previewContainer.classList.add('hidden');
        });
        
        previewTab.addEventListener('click', function() {
            previewTab.classList.add('bg-white', 'dark:bg-gray-800');
            previewTab.classList.remove('bg-gray-100', 'dark:bg-gray-600');
            editorTab.classList.remove('bg-white', 'dark:bg-gray-800');
            editorTab.classList.add('bg-gray-100', 'dark:bg-gray-600');
            previewContainer.classList.remove('hidden');
            editorContainer.classList.add('hidden');
        });
        
        // Sidebar toggle (mobile)
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('-translate-x-full');
                sidebar.classList.toggle('translate-x-0');
            });
        }
        
        // Save button
        if (saveButton) {
            saveButton.addEventListener('click', saveNote);
        }
        
        // New note buttons
        if (newNoteBtn) {
            newNoteBtn.addEventListener('click', showNewNoteModal);
        }
        
        if (newNoteBtn2) {
            newNoteBtn2.addEventListener('click', showNewNoteModal);
        }
        
        // Modal controls
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', hideNewNoteModal);
        }
        
        if (cancelNoteBtn) {
            cancelNoteBtn.addEventListener('click', hideNewNoteModal);
        }
        
        if (createNoteBtn) {
            createNoteBtn.addEventListener('click', createNewNote);
        }
    }
    
    /**
     * Update the preview with the markdown content
     */
    function updatePreview() {
        const content = editor.value;
        
        fetch('/render_markdown.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'content=' + encodeURIComponent(content)
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
        newNoteModal.classList.remove('hidden');
        document.getElementById('noteTitle').focus();
    }
    
    /**
     * Hide new note modal
     */
    function hideNewNoteModal() {
        newNoteModal.classList.add('hidden');
        
        // Reset form
        document.getElementById('noteTitle').value = '';
        document.getElementById('noteCategory').value = '';
        document.getElementById('noteTemplate').value = 'blank';
    }
    
    /**
     * Create new note from modal input
     */
    function createNewNote() {
        const title = document.getElementById('noteTitle').value.trim();
        const category = document.getElementById('noteCategory').value.trim();
        const template = document.getElementById('noteTemplate').value;
        
        let content = '';
        
        // Generate content based on template
        switch(template) {
            case 'basic':
                content = `# ${title || 'New Note'}\n\n` +
                          `Category: ${category || 'Uncategorized'}\n\n` +
                          `Date: ${new Date().toLocaleDateString()}\n\n` +
                          `## Overview\n\n` +
                          `Write your note here...\n\n` +
                          `## Details\n\n` +
                          `More information...\n\n`;
                break;
                
            case 'meeting':
                content = `# Meeting: ${title || 'Untitled Meeting'}\n\n` +
                          `Category: ${category || 'Meetings'}\n\n` +
                          `Date: ${new Date().toLocaleDateString()}\n` +
                          `Time: ${new Date().toLocaleTimeString()}\n\n` +
                          `## Attendees\n\n` +
                          `- Person 1\n` +
                          `- Person 2\n\n` +
                          `## Agenda\n\n` +
                          `1. Introduction\n` +
                          `2. Discussion\n` +
                          `3. Action Items\n\n` +
                          `## Notes\n\n` +
                          `Write meeting notes here...\n\n` +
                          `## Action Items\n\n` +
                          `- [ ] Task 1\n` +
                          `- [ ] Task 2\n`;
                break;
                
            case 'todo':
                content = `# To-Do List: ${title || 'Tasks'}\n\n` +
                          `Category: ${category || 'Tasks'}\n\n` +
                          `Created: ${new Date().toLocaleDateString()}\n\n` +
                          `## High Priority\n\n` +
                          `- [ ] Important task 1\n` +
                          `- [ ] Important task 2\n\n` +
                          `## Medium Priority\n\n` +
                          `- [ ] Regular task 1\n` +
                          `- [ ] Regular task 2\n\n` +
                          `## Low Priority\n\n` +
                          `- [ ] Optional task\n\n` +
                          `## Completed\n\n` +
                          `- [x] Example completed task\n`;
                break;
                
            default: // blank
                content = `# ${title || 'Untitled Note'}\n\n` +
                          `${category ? 'Category: ' + category + '\n\n' : ''}` +
                          `Created: ${new Date().toLocaleDateString()}\n\n`;
                break;
        }
        
        // Set content in editor
        editor.value = content;
        window.lastSavedContent = '';
        window.currentNoteId = 0;
        
        // Update preview
        updatePreview();
        updateSaveStatus();
        
        // Hide modal
        hideNewNoteModal();
        
        // Show notification
        showNotification('New note created', 'success');
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
                    userNotes.innerHTML = `<div class="p-4 text-sm text-red-500">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error loading notes:', error);
                userNotes.innerHTML = '<div class="p-4 text-sm text-red-500">Error connecting to server</div>';
            });
    }
    
    /**
     * Display notes in the sidebar
     */
    function displayNotes(notes) {
        if (!notes || notes.length === 0) {
            userNotes.innerHTML = '<div class="p-4 text-sm text-gray-500">No notes found. Create a new note to get started!</div>';
            return;
        }
        
        const fragment = document.createDocumentFragment();
        
        notes.forEach(note => {
            const noteElement = document.createElement('div');
            noteElement.className = 'p-3 border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer';
            noteElement.setAttribute('data-note-id', note.id);
            
            noteElement.innerHTML = `
                <div class="font-medium truncate">${note.title || 'Untitled Note'}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">${note.updated_at || 'Unknown date'}</div>
            `;
            
            noteElement.addEventListener('click', function() {
                loadNote(note.id);
                
                // On mobile, close sidebar after selecting a note
                if (window.innerWidth < 1024) {
                    sidebar.classList.add('-translate-x-full');
                    sidebar.classList.remove('translate-x-0');
                }
            });
            
            fragment.appendChild(noteElement);
        });
        
        userNotes.innerHTML = '';
        userNotes.appendChild(fragment);
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
                    window.currentNoteId = noteId;
                    window.lastSavedContent = data.content;
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
     * Save the current note
     */
    function saveNote() {
        const content = editor.value;
        
        // Extract title from content (first # heading)
        let title = 'Untitled Note';
        const titleMatch = content.match(/^#\s+(.+)$/m);
        if (titleMatch && titleMatch[1]) {
            title = titleMatch[1].trim();
        }
        
        // Update save status
        const statusDot = saveStatus.querySelector('.status-dot');
        const statusText = saveStatus.querySelector('span:not(.status-dot)');
        statusDot.className = 'status-dot warning';
        statusText.textContent = 'Saving...';
        
        // Create form data
        const formData = new FormData();
        formData.append('content', content);
        formData.append('title', title);
        formData.append('note_id', window.currentNoteId);
        
        fetch('/save.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update current note ID if this was a new note
                if (window.currentNoteId === 0) {
                    window.currentNoteId = data.note_id;
                }
                
                // Update save status
                window.lastSavedContent = content;
                statusDot.className = 'status-dot online';
                statusText.textContent = 'Saved';
                setTimeout(() => {
                    statusText.textContent = 'Ready to save';
                }, 3000);
                
                // Refresh notes list to show the new note
                loadNotes();
                
                // Show notification
                showNotification('Note saved successfully', 'success');
            } else {
                // Show error
                statusDot.className = 'status-dot offline';
                statusText.textContent = 'Save Error';
                
                // Show notification
                showNotification('Error saving note: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error saving note:', error);
            
            // Update save status
            statusDot.className = 'status-dot offline';
            statusText.textContent = 'Connection Error';
            
            // Show notification
            showNotification('Error connecting to server', 'error');
        });
    }
    
    /**
     * Update the save status based on changes
     */
    function updateSaveStatus() {
        const statusDot = saveStatus.querySelector('.status-dot');
        const statusText = saveStatus.querySelector('span:not(.status-dot)');
        
        if (editor.value !== window.lastSavedContent) {
            statusDot.className = 'status-dot warning';
            statusText.textContent = 'Unsaved changes';
        } else {
            statusDot.className = 'status-dot online';
            statusText.textContent = 'Ready to save';
        }
    }
    
    /**
     * Show a notification message
     */
    function showNotification(message, type = 'info') {
        const container = document.getElementById('notificationContainer');
        const notification = document.createElement('div');
        
        // Set notification styles based on type
        let bgColor, textColor, borderColor, iconSvg;
        
        switch(type) {
            case 'success':
                bgColor = 'bg-green-100 dark:bg-green-900';
                textColor = 'text-green-800 dark:text-green-100';
                borderColor = 'border-green-200 dark:border-green-700';
                iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>';
                break;
            case 'error':
                bgColor = 'bg-red-100 dark:bg-red-900';
                textColor = 'text-red-800 dark:text-red-100';
                borderColor = 'border-red-200 dark:border-red-700';
                iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>';
                break;
            case 'warning':
                bgColor = 'bg-yellow-100 dark:bg-yellow-900';
                textColor = 'text-yellow-800 dark:text-yellow-100';
                borderColor = 'border-yellow-200 dark:border-yellow-700';
                iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>';
                break;
            default: // info
                bgColor = 'bg-blue-100 dark:bg-blue-900';
                textColor = 'text-blue-800 dark:text-blue-100';
                borderColor = 'border-blue-200 dark:border-blue-700';
                iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>';
                break;
        }
        
        // Create notification element
        notification.className = `flex items-center p-3 mb-3 rounded-lg border shadow-md ${bgColor} ${textColor} ${borderColor}`;
        notification.innerHTML = `
            <div class="mr-3">${iconSvg}</div>
            <div class="text-sm">${message}</div>
            <button class="ml-auto text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
        `;
        
        // Add close button functionality
        const closeButton = notification.querySelector('button');
        closeButton.addEventListener('click', function() {
            notification.remove();
        });
        
        // Add to container
        container.appendChild(notification);
        
        // Auto-remove after delay
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.add('opacity-0', 'transform', 'translate-x-full');
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }
});

// Make functions available globally
window.saveNote = saveNote;
window.loadNote = loadNote;
window.updatePreview = updatePreview;
