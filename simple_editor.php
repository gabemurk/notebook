<?php
session_start();
require_once __DIR__ . '/database/enhanced_db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultra Simple Markdown Editor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .tabs {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 1px solid #ccc;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background: #eee;
            border: 1px solid #ccc;
            border-bottom: none;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
        }
        .tab.active {
            background: #007bff;
            color: white;
            font-weight: bold;
        }
        .editor {
            width: 100%;
            height: 400px;
            padding: 15px;
            border: 3px solid #000;
            font-size: 18px;
            font-family: monospace;
            resize: vertical;
            box-sizing: border-box;
            margin-bottom: 20px;
            color: #000;
            background-color: #fff;
        }
        .preview {
            border: 1px solid #ccc;
            min-height: 400px;
            padding: 15px;
            background: white;
            color: #000;
        }
        .content {
            display: none;
        }
        .content.active {
            display: block;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        .button {
            padding: 10px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ultra Simple Markdown Editor</h1>
        
        <div class="action-buttons">
            <button id="newNoteBtn" class="button">+ New Note</button>
            <button id="saveButton" class="button">Save Note</button>
        </div>
        
        <div class="tabs">
            <div class="tab active" id="editorTab">Editor</div>
            <div class="tab" id="previewTab">Preview</div>
        </div>
        
        <div class="content active" id="editorContent">
            <textarea id="editor" class="editor" placeholder="Type your markdown here...">
# Welcome to the Ultra Simple Editor

This is a bare-bones version with no fancy features.

- Just plain text editing
- Simple preview
- No distractions
            </textarea>
            
            <button id="saveButton" class="button">Save Note</button>
        </div>
        
        <div class="content" id="previewContent">
            <div id="preview" class="preview"></div>
        </div>
        
        <div class="footer">
            <p>Ultra Simple Markdown Editor - Created for testing</p>
            <p>Database Status: <span id="dbStatus" class="status-text">Checking...</span></p>
        </div>
        
        <script>
        // Function to check and display database status
        function checkDbStatus() {
            fetch('/simple_db_check.php?format=json')
                .then(response => response.json())
                .then(data => {
                    const dbStatusElement = document.getElementById('dbStatus');
                    if (data.postgresql && data.postgresql.status) {
                        dbStatusElement.textContent = 'PostgreSQL Connected';
                        dbStatusElement.style.color = '#155724';
                        dbStatusElement.style.backgroundColor = '#d4edda';
                        dbStatusElement.style.padding = '3px 8px';
                        dbStatusElement.style.borderRadius = '4px';
                    } else if (data.sqlite && data.sqlite.status) {
                        dbStatusElement.textContent = 'SQLite Connected (Fallback)';
                        dbStatusElement.style.color = '#856404';
                        dbStatusElement.style.backgroundColor = '#fff3cd';
                        dbStatusElement.style.padding = '3px 8px';
                        dbStatusElement.style.borderRadius = '4px';
                    } else {
                        dbStatusElement.textContent = 'Database Disconnected';
                        dbStatusElement.style.color = '#721c24';
                        dbStatusElement.style.backgroundColor = '#f8d7da';
                        dbStatusElement.style.padding = '3px 8px';
                        dbStatusElement.style.borderRadius = '4px';
                    }
                })
                .catch(error => {
                    const dbStatusElement = document.getElementById('dbStatus');
                    dbStatusElement.textContent = 'Error checking DB status';
                    dbStatusElement.style.color = '#721c24';
                });
        }
        
        // Check DB status when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(checkDbStatus, 500); // Slight delay to ensure page is ready
        });
        </script>
    </div>
    
    <!-- New Note Modal -->
    <div id="newNoteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background-color: white; padding: 20px; border-radius: 5px; width: 80%; max-width: 500px; color: #333;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">Create New Note</h3>
                <button id="closeModalBtn" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            <div>
                <div style="margin-bottom: 15px;">
                    <label for="noteTitle" style="display: block; margin-bottom: 5px;">Title</label>
                    <input type="text" id="noteTitle" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="Enter note title">
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="noteCategory" style="display: block; margin-bottom: 5px;">Category (optional)</label>
                    <input type="text" id="noteCategory" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="Enter category">
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="noteTemplate" style="display: block; margin-bottom: 5px;">Template</label>
                    <select id="noteTemplate" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="blank">Blank Note</option>
                        <option value="basic">Basic Structure</option>
                        <option value="meeting">Meeting Notes</option>
                        <option value="todo">To-Do List</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                <button id="cancelNoteBtn" style="margin-right: 10px; padding: 8px 15px; background-color: #f1f1f1; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button id="createNoteBtn" style="padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Create Note</button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get elements
            const editor = document.getElementById('editor');
            const preview = document.getElementById('preview');
            const editorTab = document.getElementById('editorTab');
            const previewTab = document.getElementById('previewTab');
            const editorContent = document.getElementById('editorContent');
            const previewContent = document.getElementById('previewContent');
            const saveButton = document.getElementById('saveButton');
            
            // New Note Modal elements
            const newNoteModal = document.getElementById('newNoteModal');
            const newNoteBtn = document.getElementById('newNoteBtn');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const cancelNoteBtn = document.getElementById('cancelNoteBtn');
            const createNoteBtn = document.getElementById('createNoteBtn');
            
            // Simple tab switching
            editorTab.addEventListener('click', function() {
                editorTab.classList.add('active');
                previewTab.classList.remove('active');
                editorContent.classList.add('active');
                previewContent.classList.remove('active');
            });
            
            previewTab.addEventListener('click', function() {
                previewTab.classList.add('active');
                editorTab.classList.remove('active');
                previewContent.classList.add('active');
                editorContent.classList.remove('active');
                updatePreview();
            });
            
            // Ultra basic markdown converter
            function updatePreview() {
                // Get the markdown content
                const markdown = editor.value;
                
                // Convert markdown to HTML (very basic conversion)
                let html = markdown
                    // Escape HTML
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    // Headers
                    .replace(/^# (.*)/gm, '<h1>$1</h1>')
                    .replace(/^## (.*)/gm, '<h2>$1</h2>')
                    // Lists
                    .replace(/^- (.*)/gm, '<li>$1</li>')
                    // Line breaks
                    .replace(/\n/g, '<br>');
                
                // Update the preview
                preview.innerHTML = html;
                console.log('Preview updated');
            }
            
            // Update preview when switching to preview tab
            previewTab.addEventListener('click', updatePreview);
            
            // Simple save function
            saveButton.addEventListener('click', function() {
                // Get the current note content
                const content = editor.value;
                if (!content.trim()) {
                    alert('Cannot save empty note!');
                    return;
                }
                
                // Extract title from first heading or use default
                const titleMatch = content.match(/^# (.+)$/m);
                const title = titleMatch ? titleMatch[1] : 'Untitled Note';
                
                // Show saving indicator
                const originalText = saveButton.textContent;
                saveButton.textContent = 'Saving...';
                saveButton.disabled = true;
                
                // Send to server
                fetch('save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'content': content,
                        'title': title,
                        'note_id': document.getElementById('currentNoteId')?.value || '0'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update note ID if this was a new note
                        if (document.getElementById('currentNoteId')) {
                            document.getElementById('currentNoteId').value = data.note_id;
                        } else {
                            // Create hidden input for note ID
                            const noteIdInput = document.createElement('input');
                            noteIdInput.type = 'hidden';
                            noteIdInput.id = 'currentNoteId';
                            noteIdInput.value = data.note_id;
                            document.body.appendChild(noteIdInput);
                        }
                        
                        alert('Note saved successfully!');
                    } else {
                        alert('Error saving note: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error saving note:', error);
                    alert('Error connecting to server. Please try again.');
                })
                .finally(() => {
                    // Reset button
                    saveButton.textContent = originalText;
                    saveButton.disabled = false;
                });
            });
            
            // New Note Modal Functions
            // Show modal
            function showNewNoteModal() {
                newNoteModal.style.display = 'flex';
                document.getElementById('noteTitle').focus();
            }
            
            // Hide modal
            function hideNewNoteModal() {
                newNoteModal.style.display = 'none';
                document.getElementById('noteTitle').value = '';
                document.getElementById('noteCategory').value = '';
                document.getElementById('noteTemplate').value = 'blank';
            }
            
            // Create new note
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
                if (document.getElementById('currentNoteId')) {
                    document.getElementById('currentNoteId').value = '0';
                } else {
                    const noteIdInput = document.createElement('input');
                    noteIdInput.type = 'hidden';
                    noteIdInput.id = 'currentNoteId';
                    noteIdInput.value = '0';
                    document.body.appendChild(noteIdInput);
                }
                
                // Hide modal
                hideNewNoteModal();
                
                // Focus editor
                editor.focus();
            }
            
            // Attach event listeners for new note functionality
            if (newNoteBtn) {
                newNoteBtn.addEventListener('click', showNewNoteModal);
            }
            
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', hideNewNoteModal);
            }
            
            if (cancelNoteBtn) {
                cancelNoteBtn.addEventListener('click', hideNewNoteModal);
            }
            
            if (createNoteBtn) {
                createNoteBtn.addEventListener('click', createNewNote);
            }
            
            // Close modal when clicking outside
            newNoteModal.addEventListener('click', function(event) {
                if (event.target === this) {
                    hideNewNoteModal();
                }
            });
            
            // Handle keyboard events for modal
            document.addEventListener('keydown', function(event) {
                if (newNoteModal.style.display === 'flex') {
                    if (event.key === 'Escape') {
                        hideNewNoteModal();
                    } else if (event.key === 'Enter' && event.ctrlKey) {
                        createNewNote();
                    }
                }
            });
            
            // Initialize
            updatePreview();
            console.log('Ultra simple editor initialized with new note functionality');
        });
    </script>
</body>
</html>
