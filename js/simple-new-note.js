/**
 * Simple New Note Functionality
 * This script handles the creation of new notes in the simplified editor.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const newNoteModal = document.getElementById('newNoteModal');
    const newNoteBtn = document.getElementById('newNoteBtn');
    const newNoteBtn2 = document.getElementById('newNoteBtn2');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelNoteBtn = document.getElementById('cancelNoteBtn');
    const createNoteBtn = document.getElementById('createNoteBtn');
    
    // Editor elements
    const editor = document.getElementById('editor');
    
    // Show modal when New Note button is clicked
    function showNewNoteModal() {
        console.log('Showing new note modal');
        if (newNoteModal) {
            newNoteModal.style.display = 'flex';
            
            // Focus on title input
            const titleInput = document.getElementById('noteTitle');
            if (titleInput) {
                titleInput.focus();
            }
        } else {
            console.error('New note modal not found');
        }
    }
    
    // Hide modal
    function hideNewNoteModal() {
        console.log('Hiding new note modal');
        if (newNoteModal) {
            newNoteModal.style.display = 'none';
            
            // Reset form
            const titleInput = document.getElementById('noteTitle');
            const categoryInput = document.getElementById('noteCategory');
            const templateSelect = document.getElementById('noteTemplate');
            
            if (titleInput) titleInput.value = '';
            if (categoryInput) categoryInput.value = '';
            if (templateSelect) templateSelect.value = 'blank';
        }
    }
    
    // Create new note
    function createNewNote() {
        console.log('Creating new note');
        
        // Get form values
        const title = document.getElementById('noteTitle')?.value.trim() || 'Untitled Note';
        const category = document.getElementById('noteCategory')?.value.trim() || '';
        const template = document.getElementById('noteTemplate')?.value || 'blank';
        
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
        if (editor) {
            editor.value = content;
            
            // Update preview if function exists
            if (typeof window.updatePreview === 'function') {
                window.updatePreview();
            }
        } else {
            console.error('Editor element not found');
        }
        
        // Hide modal
        hideNewNoteModal();
        
        // Save the new note if function exists
        if (typeof window.saveNote === 'function') {
            setTimeout(() => {
                window.saveNote();
            }, 100);
        }
        
        // Show confirmation
        alert('New note created! The content has been added to the editor.');
    }
    
    // Attach event listeners
    if (newNoteBtn) {
        newNoteBtn.addEventListener('click', showNewNoteModal);
    }
    
    if (newNoteBtn2) {
        newNoteBtn2.addEventListener('click', showNewNoteModal);
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
    if (newNoteModal) {
        newNoteModal.addEventListener('click', function(event) {
            if (event.target === this) {
                hideNewNoteModal();
            }
        });
    }
    
    // Handle keyboard events for modal
    document.addEventListener('keydown', function(event) {
        if (newNoteModal && newNoteModal.style.display === 'flex') {
            if (event.key === 'Escape') {
                hideNewNoteModal();
            } else if (event.key === 'Enter' && event.ctrlKey) {
                createNewNote();
            }
        }
    });
    
    console.log('Simple new note functionality initialized');
});
