// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('ServiceWorker registration successful');
                // Check for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showToast('App updated! Please refresh for the latest version.', 'info');
                        }
                    });
                });
            })
            .catch(err => {
                console.error('ServiceWorker registration failed: ', err);
            });
    });
}

// Handle offline/online events
function updateOnlineStatus() {
    const indicator = document.getElementById('offline-indicator');
    if (!navigator.onLine) {
        indicator.textContent = 'You are offline';
        indicator.style.display = 'block';
        checkDatabaseConnection(); // Re-check database status
    } else {
        indicator.style.display = 'none';
        syncOfflineChanges(); // Try to sync when back online
    }
}

window.addEventListener('online', updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);

// IndexedDB setup
const dbName = 'notebookPWA';
const dbVersion = 1;
let db;

const request = indexedDB.open(dbName, dbVersion);

request.onerror = (event) => {
    console.error('IndexedDB error:', event.target.error);
};

request.onsuccess = (event) => {
    db = event.target.result;
    console.log('IndexedDB connected');
};

request.onupgradeneeded = (event) => {
    const db = event.target.result;
    
    // Create notes store
    if (!db.objectStoreNames.contains('notes')) {
        const notesStore = db.createObjectStore('notes', { keyPath: 'id', autoIncrement: true });
        notesStore.createIndex('userId', 'userId', { unique: false });
        notesStore.createIndex('updatedAt', 'updatedAt', { unique: false });
    }
    
    // Create offline changes store
    if (!db.objectStoreNames.contains('offlineChanges')) {
        const changesStore = db.createObjectStore('offlineChanges', { keyPath: 'id', autoIncrement: true });
        changesStore.createIndex('type', 'type', { unique: false });
        changesStore.createIndex('timestamp', 'timestamp', { unique: false });
    }
};

// Function to sync offline changes
async function syncOfflineChanges() {
    if (!navigator.onLine) return;
    
    const transaction = db.transaction(['offlineChanges'], 'readwrite');
    const store = transaction.objectStore('offlineChanges');
    const changes = await store.getAll();
    
    for (const change of changes) {
        try {
            const response = await fetch('/api/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(change),
            });
            
            if (response.ok) {
                await store.delete(change.id);
            }
        } catch (error) {
            console.error('Sync error:', error);
        }
    }
}

// Toast notification system
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Function to check database connection status
function checkDatabaseConnection() {
    const dbStatusElement = document.getElementById('dbStatus');
    const statusDot = dbStatusElement ? dbStatusElement.querySelector('.status-dot') : null;
    const statusText = dbStatusElement ? dbStatusElement.querySelector('.status-text') : null;
    
    if (statusDot && statusText) {
        // Set status to checking
        statusDot.className = 'status-dot checking';
        statusText.textContent = 'DB: Checking...';
        
        // Check connection
        $.ajax({
            url: 'check_db_connection.php',
            method: 'GET',
            timeout: 5000, // 5 second timeout
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (result.connected) {
                        // Connected
                        statusDot.className = 'status-dot connected';
                        statusText.textContent = 'DB: Connected';
                    } else {
                        // Disconnected
                        statusDot.className = 'status-dot disconnected';
                        statusText.textContent = 'DB: Disconnected';
                        console.error('Database connection error:', result.message);
                    }
                } catch (e) {
                    // Error parsing response
                    statusDot.className = 'status-dot disconnected';
                    statusText.textContent = 'DB: Error';
                    console.error('Error parsing database status:', e);
                }
            },
            error: function() {
                // Connection error
                statusDot.className = 'status-dot disconnected';
                statusText.textContent = 'DB: Unreachable';
                console.error('Failed to check database connection');
            }
        });
    }
}

// Function to check authentication status
function checkAuthStatus() {
    $.ajax({
        url: 'check_auth.php',
        method: 'GET',
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.authenticated) {
                    // User is logged in
                    const userDisplayName = document.getElementById('userDisplayName');
                    const logoutButton = document.getElementById('logoutButton');
                    const loginPageButton = document.getElementById('loginPageButton');
                    
                    if (userDisplayName) userDisplayName.textContent = result.username;
                    if (logoutButton) logoutButton.style.display = 'inline-block';
                    if (loginPageButton) loginPageButton.style.display = 'none';
                    // Load user's notes
                    loadUserNotes();
                    
                    // Check database connection
                    checkDatabaseConnection();
                } else if (window.location.pathname.indexOf('login-page.php') === -1) {
                    // User is not logged in and not on login page
                    // Redirect to login page
                    window.location.href = 'login-page.php';
                }
            } catch (e) {
                console.error('Error parsing auth response:', e);
            }
        },
        error: function() {
            console.error('Failed to check authentication status');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Get elements
    const editorTextarea = document.getElementById('editor');
    const previewDiv = document.getElementById('preview');
    const markdownViewBtn = document.getElementById('markdownViewBtn');
    const mindmapViewBtn = document.getElementById('mindmapViewBtn');
    const splitViewBtn = document.getElementById('splitViewBtn');
    const editorContainer = document.getElementById('editor-container');
    const previewContainer = document.getElementById('preview-container');
    
    // Markdown toolbar buttons
    const toolbarButtons = document.querySelectorAll('.md-toolbar button');
    let currentNoteId = 0; // Track current note ID
    const markdownView = document.getElementById('markdownView');
    const mindmapView = document.getElementById('mindmapView');
    const mindmapContainer = document.getElementById('mindmap');
    
    // Get editor/preview toggle elements
    const editorToggleBtn = document.getElementById('editorToggleBtn');
    const previewToggleBtn = document.getElementById('previewToggleBtn');
    const editorPanelContainer = document.getElementById('editorContainer');
    const previewPanelContainer = document.getElementById('previewContainer');
    
    // Get user interface elements
    const userDisplayName = document.getElementById('userDisplayName');
    const logoutButton = document.getElementById('logoutButton');
    const loginPageButton = document.getElementById('loginPageButton');
    
    // Variable to track if markmap has been initialized
    let markmapInitialized = false;
    
    // Check authentication status on page load
    checkAuthStatus();
    
    // Set up periodic database connection check (every 30 seconds)
    setInterval(checkDatabaseConnection, 30000);
    
    // Handle tab key and enter key in the editor
    editorTextarea.addEventListener('keydown', function(e) {
        // Handle Tab key
        if (e.key === 'Tab') {
            e.preventDefault();
            
            // Get cursor position
            const start = this.selectionStart;
            const end = this.selectionEnd;
            
            // Insert tab at cursor position
            this.value = this.value.substring(0, start) + '\t' + this.value.substring(end);
            
            // Move cursor after tab
            this.selectionStart = this.selectionEnd = start + 1;
            
            // Trigger the preview update
            const event = new Event('input');
            this.dispatchEvent(event);
        }
        
        // Handle Enter key to preserve indentation and continue lists
        if (e.key === 'Enter') {
            e.preventDefault();
            
            // Get cursor position
            const start = this.selectionStart;
            const end = this.selectionEnd;
            
            // Get current line up to cursor position
            const text = this.value;
            const lineStart = text.lastIndexOf('\n', start - 1) + 1;
            const line = text.substring(lineStart, start);
            
            // Detect leading whitespace (tabs and spaces)
            const leadingWhitespace = line.match(/^[\t ]*/) || [''];
            
            // Check if the current line is a list item
            const listMatch = line.match(/^([\t ]*)([*+-]|\d+\.) (.*)/); 
            
            let insertText;
            if (listMatch) {
                // If the list item is empty (just the marker), remove the list marker
                if (listMatch[3].trim() === '') {
                    // End the list and just insert a newline with indentation
                    insertText = '\n' + leadingWhitespace[0];
                } else {
                    // Continue the list
                    if (listMatch[2].match(/\d+\./)) { 
                        // For numbered lists, increment the number
                        const num = parseInt(listMatch[2], 10);
                        insertText = '\n' + leadingWhitespace[0] + (num + 1) + '. ';
                    } else {
                        // For bullet lists, use the same marker
                        insertText = '\n' + leadingWhitespace[0] + listMatch[2] + ' ';
                    }
                }
            } else {
                // Not a list item, just preserve indentation
                insertText = '\n' + leadingWhitespace[0];
            }
            
            this.value = text.substring(0, start) + insertText + text.substring(end);
            
            // Move cursor to the position after the list marker or indentation on the new line
            const newPosition = start + insertText.length;
            this.selectionStart = this.selectionEnd = newPosition;
            
            // Trigger the preview update
            const event = new Event('input');
            this.dispatchEvent(event);
        }
    });
    
    // Function to update preview with enhanced markdown parsing
    function updatePreview() {
        let content = editorTextarea.value;
        
        // Advanced markdown parsing for preview
        // Headers
        content = content.replace(/^# (.+)$/gm, '<h1>$1</h1>');
        content = content.replace(/^## (.+)$/gm, '<h2>$1</h2>');
        content = content.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        content = content.replace(/^#### (.+)$/gm, '<h4>$1</h4>');
        content = content.replace(/^##### (.+)$/gm, '<h5>$1</h5>');
        content = content.replace(/^###### (.+)$/gm, '<h6>$1</h6>');
        
        // Bold, italic, strikethrough
        content = content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        content = content.replace(/\*(.+?)\*/g, '<em>$1</em>');
        content = content.replace(/\_(.+?)\_/g, '<em>$1</em>');
        content = content.replace(/\~\~(.+?)\~\~/g, '<del>$1</del>');
        
        // Lists
        content = content.replace(/^\* (.+)$/gm, '<ul><li>$1</li></ul>');
        content = content.replace(/^\- (.+)$/gm, '<ul><li>$1</li></ul>');
        content = content.replace(/^\d+\. (.+)$/gm, '<ol><li>$1</li></ol>');
        
        // Links and images
        content = content.replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank">$1</a>');
        content = content.replace(/!\[(.+?)\]\((.+?)\)/g, '<img src="$2" alt="$1" class="img-fluid">');
        
        // Code blocks
        content = content.replace(/```([\s\S]+?)```/g, '<pre><code>$1</code></pre>');
        content = content.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Blockquotes
        content = content.replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>');
        
        // Horizontal rule
        content = content.replace(/^---$/gm, '<hr>');
        
        // Fix nested lists (simplified)
        content = content.replace(/<\/ul><br><ul>/g, '');
        content = content.replace(/<\/ol><br><ol>/g, '');
        
        // Add paragraphs for remaining text
        content = content.replace(/^(?!<h|<li|<blockquote|<pre|<hr)(.+)$/gm, '<p>$1</p>');
        
        // Replace newlines with <br> tags
        content = content.replace(/\n/g, '<br>');
        
        // Display in preview div
        previewDiv.innerHTML = content;
        
        // If we're in split view, adjust heights
        if (splitViewBtn && splitViewBtn.classList.contains('active')) {
            adjustSplitView();
        }
    }
    
    // Function to adjust split view layout
    function adjustSplitView() {
        if (!editorContainer || !previewContainer) return;
        
        editorContainer.style.width = '50%';
        previewContainer.style.width = '50%';
        previewContainer.style.display = 'block';
    }
    
    // Function to adjust full editor view
    function adjustFullEditorView() {
        if (!editorContainer || !previewContainer) return;
        
        editorContainer.style.width = '100%';
        previewContainer.style.display = 'none';
    }
    
    // Function to adjust full preview view
    function adjustFullPreviewView() {
        if (!editorContainer || !previewContainer) return;
        
        editorContainer.style.width = '0';
        previewContainer.style.width = '100%';
        previewContainer.style.display = 'block';
    }
    
    // Variable to track auto-save timer
    let autoSaveTimer = null;
    let lastSavedContent = '';
    
    // Function to update save status indicator
    function updateSaveStatus(status, message) {
        const saveStatusElement = document.getElementById('saveStatus');
        if (!saveStatusElement) return;
        
        const statusText = saveStatusElement.querySelector('.status-text');
        
        // Reset all classes
        saveStatusElement.classList.remove('saving', 'saved', 'failed');
        
        // Set new status
        if (status) {
            saveStatusElement.classList.add(status);
        }
        
        // Update message
        if (statusText && message) {
            statusText.textContent = message;
        }
    }
    
    // Function to auto-save content
    function autoSave() {
        const content = editorTextarea.value;
        
        // Only save if content has changed since last save
        if (content !== lastSavedContent) {
            // Update status to saving
            updateSaveStatus('saving', 'Saving...');
            
            // Clear any existing timers
            clearTimeout(autoSaveTimer);
            
            // Set a new timer (debounce)
            autoSaveTimer = setTimeout(function() {
                // Extract title from content if it starts with a heading
                let title = '';
                if (content.startsWith('# ')) {
                    const match = content.match(/^# (.+)$/m);
                    if (match) title = match[1];
                }
                
                // Save data
                $.ajax({
                    url: 'save_note.php',
                    method: 'POST',
                    data: {
                        note_id: currentNoteId,
                        content: content,
                        title: title
                    },
                    success: function(response) {
                        try {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            
                            if (result.success) {
                                // Update last saved content
                                lastSavedContent = content;
                                // Update status to saved
                                updateSaveStatus('saved', 'Saved ' + new Date().toLocaleTimeString());
                                
                                // Update current note ID if it's a new note
                                if (result.note_id && currentNoteId === 0) {
                                    currentNoteId = result.note_id;
                                    // Update URL without reloading
                                    const newUrl = window.location.pathname + '?note=' + currentNoteId;
                                    history.pushState({noteId: currentNoteId}, '', newUrl);
                                }
                                
                                // Refresh note list occasionally
                                if (Math.random() < 0.2) { // 20% chance to refresh list
                                    loadUserNotes();
                                }
                            } else {
                                // Update status to failed
                                updateSaveStatus('failed', 'Save failed');
                                console.error('Save error:', result.message);
                            }
                        } catch (e) {
                            // Update status to failed
                            updateSaveStatus('failed', 'Save error');
                            console.error('Error parsing save response:', e);
                        }
                    },
                    error: function() {
                        // Update status to failed
                        updateSaveStatus('failed', 'Save failed');
                        console.error('Failed to save content');
                    }
                });
            }, 1000); // 1 second delay
    }
    
    // Add event listener to update preview as user types
    editorTextarea.addEventListener('input', function() {
        // Update the preview
        updatePreview();
        
        // Update mind map when content changes
        updateMindMap();
        
        // Clear previous auto-save timer
        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
        }
        
        // Set status to modified
        updateSaveStatus(null, 'Modified');
        
        // Schedule auto-save after 2 seconds of inactivity
        autoSaveTimer = setTimeout(autoSave, 2000);
    });
    
    // Function to update the mind map based on current markdown content
    function updateMindMap() {
        // Get the markdown content
        const markdown = editorTextarea.value;
        
        // Only update if there's content
        if (markdown.trim()) {
            try {
                // Check if our custom mind map renderer function is available
                if (window.renderMindMap) {
                    // Use our custom function to render the mind map
                    window.renderMindMap(markdown, mindmapContainer);
                } else {
                    // If function is not loaded yet, show loading message
                    mindmapContainer.innerHTML = '<div class="loading" style="padding: 20px;">Loading mind map renderer...</div>';
                    
                    // Try again in 1 second (allowing time for scripts to load)
                    setTimeout(() => {
                        if (window.renderMindMap) {
                            window.renderMindMap(markdown, mindmapContainer);
                        } else {
                            mindmapContainer.innerHTML = '<div class="error" style="padding: 20px; color: red;">Mind map renderer not available. Please refresh the page.</div>';
                        }
                    }, 1000);
                }
            } catch (error) {
                console.error('Error generating mind map:', error);
                mindmapContainer.innerHTML = '<div class="error">Error generating mind map: ' + error.message + '</div>';
            }
        } else {
            mindmapContainer.innerHTML = '<div class="empty-message">Enter some markdown content to see a mind map.</div>';
        }
    }
    
    // View switching functionality
    markdownViewBtn.addEventListener('click', function() {
        // Switch to markdown view
        markdownViewBtn.classList.add('active');
        mindmapViewBtn.classList.remove('active');
        markdownView.classList.add('active-view');
        mindmapView.classList.remove('active-view');
    });
    
    // Editor/Preview Toggle Functionality
    editorToggleBtn.addEventListener('click', function() {
        // Switch to editor view
        editorToggleBtn.classList.add('active');
        previewToggleBtn.classList.remove('active');
        editorContainer.classList.add('active-subview');
        previewContainer.classList.remove('active-subview');
    });
    
    previewToggleBtn.addEventListener('click', function() {
        // Switch to preview view
        previewToggleBtn.classList.add('active');
        editorToggleBtn.classList.remove('active');
        previewContainer.classList.add('active-subview');
        editorContainer.classList.remove('active-subview');
        
        // Ensure preview is updated
        updatePreview();
    });
    
    mindmapViewBtn.addEventListener('click', function() {
        // Switch to mind map view
        mindmapViewBtn.classList.add('active');
        markdownViewBtn.classList.remove('active');
        mindmapView.classList.add('active-view');
        markdownView.classList.remove('active-view');
        
        // Update mind map when switching to the view
        updateMindMap();
        
        // Markmap autoloader handles fitting automatically
    });
    
    // Handle login/logout buttons
    if (loginPageButton) {
        loginPageButton.addEventListener('click', function() {
            window.location.href = 'login-page.php';
        });
    }
    
    if (logoutButton) {
        logoutButton.addEventListener('click', function() {
            // Perform logout
            $.ajax({
                url: 'logout.php',
                method: 'POST',
                success: function(response) {
                    // Redirect to login page
                    window.location.href = 'login-page.php';
                },
                error: function() {
                    alert('Logout failed. Please try again.');
                }
            });
        });
    }
    
    // Save Note
    document.getElementById('saveButton').addEventListener('click', function() {
        const content = document.getElementById('editor').value;
        saveData(content);
    });
    
    // Initialize the mind map on page load
    updateMindMap();

    // Search Notes
    document.getElementById('searchButton').addEventListener('click', function() {
        const searchTerm = document.getElementById('searchInput').value;
        searchNotes(searchTerm);
    });

    // Register User
    document.getElementById('registrationForm').addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent the default form submission
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const email = document.getElementById('email').value;

        registerUser(username, password, email);
    });
    
    // Login User
    document.getElementById('loginForm').addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent the default form submission
        const username = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;

        loginUser(username, password);
    });
});

function saveData(content) {
    $.ajax({
        url: 'save.php',
        type: 'POST',
        data: { content: content },
        success: function(response) {
            console.log('Data saved successfully:', response);
        },
        error: function(error) {
            console.error('Error saving data:', error);
        }
    });
}

function searchNotes(searchTerm) {
    $.ajax({
        url: 'search.php',
        type: 'POST',
        data: { searchTerm: searchTerm },
        success: function(response) {
            const notes = JSON.parse(response);
            displaySearchResults(notes);
        },
        error: function(error) {
            console.error('Error searching notes:', error);
        }
    });
}

function displaySearchResults(notes) {
    const resultsDiv = document.getElementById('searchResults');
    resultsDiv.innerHTML = ''; // Clear previous results
    if (notes.length > 0) {
        notes.forEach(note => {
            const noteElement = document.createElement('div');
            noteElement.textContent = note.content; // Display the note content
            resultsDiv.appendChild(noteElement);
        });
    } else {
        resultsDiv.textContent = 'No notes found.';
    }
}

function registerUser(username, password, email) {
    $.ajax({
        url: 'register.php',
        type: 'POST',
        data: { username: username, password: password, email: email },
        success: function(response) {
            console.log('User registered successfully:', response);
        },
        error: function(error) {
            console.error('Error registering user:', error);
        }
    });
}

function loginUser(username, password) {
    $.ajax({
        url: 'login.php',
        type: 'POST',
        data: { username: username, password: password },
        success: function(response) {
            console.log('User logged in successfully:', response);
            loadUserNotes(); // Load user notes after successful login
        },
        error: function(error) {
            console.error('Error logging in:', error);
        }
    });
}

function loadUserNotes() {
    $.ajax({
        url: 'load_notes.php',
        type: 'GET',
        success: function(response) {
            const notes = JSON.parse(response);
            displayUserNotes(notes);
        },
        error: function(error) {
            console.error('Error loading notes:', error);
        }
    });
}

function displayUserNotes(notes) {
    const notesDiv = document.getElementById('userNotes');
    notesDiv.innerHTML = ''; // Clear previous notes
    if (notes && notes.length > 0) {
        notes.forEach(note => {
            const noteElement = document.createElement('div');
            noteElement.textContent = note.content; // Display the note content
            noteElement.className = 'note-item';
            notesDiv.appendChild(noteElement);
        });
    } else {
        notesDiv.textContent = 'No notes found for this user.';
    }
}
