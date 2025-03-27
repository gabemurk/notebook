/**
 * Enhanced Markdown Editor Toolbar
 * Provides rich editing features for the markdown editor
 */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get references to toolbar buttons and editors
    const toolbarButtons = document.querySelectorAll('.toolbar-btn');
    const editor = document.getElementById('editor');
    const splitEditor = document.getElementById('splitEditor');
    
    // Initialize tooltips
    const tooltips = {};
    
    // Markdown patterns for common formatting
    const markdownPatterns = {
        bold: {
            placeholder: 'strong text',
            pattern: '**$1**',
            action: surroundSelectedText
        },
        italic: {
            placeholder: 'emphasized text',
            pattern: '*$1*',
            action: surroundSelectedText
        },
        heading: {
            placeholder: 'Heading',
            custom: function(editor) {
                const selection = getSelection(editor);
                const text = selection.text;
                const level = promptHeadingLevel();
                
                if (level) {
                    const hashes = '#'.repeat(level);
                    const newText = `${hashes} ${text || 'Heading'}`;
                    replaceSelection(editor, selection, newText);
                }
            }
        },
        link: {
            placeholder: 'link text',
            custom: function(editor) {
                const selection = getSelection(editor);
                const text = selection.text || 'link text';
                const url = prompt('Enter URL:', 'https://');
                
                if (url) {
                    const newText = `[${text}](${url})`;
                    replaceSelection(editor, selection, newText);
                }
            }
        },
        image: {
            placeholder: 'image description',
            custom: function(editor) {
                const selection = getSelection(editor);
                const text = selection.text || 'image description';
                const url = prompt('Enter image URL:', 'https://');
                
                if (url) {
                    const newText = `![${text}](${url})`;
                    replaceSelection(editor, selection, newText);
                }
            }
        },
        list: {
            custom: function(editor) {
                const listType = prompt('List type (bullet/numbered):', 'bullet');
                const selection = getSelection(editor);
                const lines = selection.text.split('\n');
                
                let newText = '';
                if (listType && listType.toLowerCase().startsWith('b')) {
                    // Bullet list
                    newText = lines.map(line => `* ${line}`).join('\n');
                } else {
                    // Numbered list
                    newText = lines.map((line, i) => `${i + 1}. ${line}`).join('\n');
                }
                
                replaceSelection(editor, selection, newText || '* List item');
            }
        },
        code: {
            placeholder: 'code',
            custom: function(editor) {
                const selection = getSelection(editor);
                const text = selection.text;
                
                // Determine if this should be inline code or a code block
                if (text.includes('\n')) {
                    // Code block - ask for language
                    const language = prompt('Programming language (optional):', 'javascript');
                    const fence = '```' + (language || '');
                    const newText = `${fence}\n${text}\n\`\`\``;
                    replaceSelection(editor, selection, newText);
                } else {
                    // Inline code
                    const newText = `\`${text || 'code'}\``;
                    replaceSelection(editor, selection, newText);
                }
            }
        },
        quote: {
            custom: function(editor) {
                const selection = getSelection(editor);
                const lines = selection.text.split('\n');
                const newText = lines.map(line => `> ${line}`).join('\n');
                replaceSelection(editor, selection, newText || '> Blockquote');
            }
        },
        hr: {
            pattern: '\n\n---\n\n',
            action: insertAtCursor
        },
        table: {
            custom: function(editor) {
                const rows = parseInt(prompt('Number of rows:', '3'), 10) || 3;
                const cols = parseInt(prompt('Number of columns:', '3'), 10) || 3;
                
                // Generate table markdown
                let tableMarkdown = '| ' + Array(cols).fill('Header').join(' | ') + ' |\n';
                tableMarkdown += '| ' + Array(cols).fill('---').join(' | ') + ' |\n';
                
                for (let i = 0; i < rows - 1; i++) {
                    tableMarkdown += '| ' + Array(cols).fill('Cell').join(' | ') + ' |\n';
                }
                
                insertAtCursor(editor, tableMarkdown);
            }
        },
        checkbox: {
            custom: function(editor) {
                const checked = confirm('Create checked task item?');
                const text = checked ? '- [x] Task item' : '- [ ] Task item';
                insertAtCursor(editor, text);
            }
        }
    };
    
    // Set up click handlers for toolbar buttons
    toolbarButtons.forEach(button => {
        const action = button.getAttribute('data-action');
        
        button.addEventListener('click', function() {
            // Determine which editor is active
            let activeEditor;
            const splitViewActive = document.querySelector('.split-view-container.active-subview');
            
            if (splitViewActive) {
                activeEditor = document.getElementById('splitEditor');
            } else {
                activeEditor = document.getElementById('editor');
            }
            
            // Focus the active editor
            activeEditor.focus();
            
            // Execute the action
            const pattern = markdownPatterns[action];
            if (pattern) {
                if (pattern.custom) {
                    pattern.custom(activeEditor);
                } else if (pattern.action) {
                    pattern.action(activeEditor, pattern.pattern, pattern.placeholder);
                }
                
                // Trigger the input event to update the preview
                const event = new Event('input', {
                    bubbles: true,
                    cancelable: true
                });
                activeEditor.dispatchEvent(event);
            }
        });
    });
    
    // Helper function to prompt for heading level
    function promptHeadingLevel() {
        const level = prompt('Heading level (1-6):', '2');
        const parsedLevel = parseInt(level, 10);
        
        if (!isNaN(parsedLevel) && parsedLevel >= 1 && parsedLevel <= 6) {
            return parsedLevel;
        }
        
        return null;
    }
    
    // Helper function to get selection from textarea
    function getSelection(textareaElement) {
        const start = textareaElement.selectionStart;
        const end = textareaElement.selectionEnd;
        const text = textareaElement.value.substring(start, end);
        
        return {
            start: start,
            end: end,
            text: text
        };
    }
    
    // Helper function to replace selection in textarea
    function replaceSelection(textareaElement, selection, newText) {
        const value = textareaElement.value;
        textareaElement.value = value.substring(0, selection.start) + newText + value.substring(selection.end);
        
        // Set new cursor position
        const newPosition = selection.start + newText.length;
        textareaElement.setSelectionRange(newPosition, newPosition);
    }
    
    // Helper function to surround selected text with pattern
    function surroundSelectedText(textareaElement, pattern, placeholder) {
        const selection = getSelection(textareaElement);
        const text = selection.text || placeholder;
        const newText = pattern.replace('$1', text);
        
        replaceSelection(textareaElement, selection, newText);
    }
    
    // Helper function to insert text at cursor position
    function insertAtCursor(textareaElement, text) {
        const selection = getSelection(textareaElement);
        replaceSelection(textareaElement, selection, text);
    }
    
    // Add keyboard shortcuts for common formatting
    document.addEventListener('keydown', function(e) {
        // Only handle if one of the editors is focused
        const activeElement = document.activeElement;
        if (activeElement !== editor && activeElement !== splitEditor) {
            return;
        }
        
        // Handle keyboard shortcuts (Ctrl/Cmd + key)
        if (e.ctrlKey || e.metaKey) {
            let pattern;
            
            switch (e.key.toLowerCase()) {
                case 'b':
                    pattern = markdownPatterns.bold;
                    e.preventDefault();
                    break;
                case 'i':
                    pattern = markdownPatterns.italic;
                    e.preventDefault();
                    break;
                case 'l':
                    pattern = markdownPatterns.link;
                    e.preventDefault();
                    break;
                case 'k':
                    pattern = markdownPatterns.code;
                    e.preventDefault();
                    break;
                case 'h':
                    pattern = markdownPatterns.heading;
                    e.preventDefault();
                    break;
                case 'q':
                    pattern = markdownPatterns.quote;
                    e.preventDefault();
                    break;
            }
            
            if (pattern) {
                if (pattern.custom) {
                    pattern.custom(activeElement);
                } else if (pattern.action) {
                    pattern.action(activeElement, pattern.pattern, pattern.placeholder);
                }
                
                // Trigger input event to update preview
                const event = new Event('input', {
                    bubbles: true,
                    cancelable: true
                });
                activeElement.dispatchEvent(event);
            }
        }
    });
    
    // Add smarter tab handling in the editors
    [editor, splitEditor].forEach(function(editorElement) {
        editorElement.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                
                const selection = getSelection(editorElement);
                
                // If there's a selection that spans multiple lines, indent/unindent the lines
                if (selection.text.includes('\n')) {
                    const lines = selection.text.split('\n');
                    let newText;
                    
                    if (e.shiftKey) {
                        // Unindent: remove up to 2 spaces or 1 tab from the start of each line
                        newText = lines.map(line => {
                            if (line.startsWith('  ')) return line.substring(2);
                            if (line.startsWith('\t')) return line.substring(1);
                            return line;
                        }).join('\n');
                    } else {
                        // Indent: add 2 spaces to the start of each line
                        newText = lines.map(line => '  ' + line).join('\n');
                    }
                    
                    replaceSelection(editorElement, selection, newText);
                } else {
                    // No multi-line selection, insert tab character or spaces
                    const tabString = '  '; // 2 spaces
                    insertAtCursor(editorElement, tabString);
                }
                
                // Trigger input event to update preview
                const event = new Event('input', {
                    bubbles: true,
                    cancelable: true
                });
                editorElement.dispatchEvent(event);
            }
        });
    });
    
    // Add table and checkbox buttons to the toolbar if they don't exist
    const toolbar = document.querySelector('.markdown-toolbar');
    if (toolbar) {
        if (!document.querySelector('[data-action="table"]')) {
            const tableButton = document.createElement('button');
            tableButton.className = 'toolbar-btn';
            tableButton.setAttribute('data-action', 'table');
            tableButton.setAttribute('title', 'Insert Table');
            tableButton.textContent = '☰';
            toolbar.appendChild(tableButton);
            
            // Add the event listener for the new button
            tableButton.addEventListener('click', function() {
                const activeEditor = document.querySelector('.split-view-container.active-subview') ? 
                    document.getElementById('splitEditor') : 
                    document.getElementById('editor');
                
                markdownPatterns.table.custom(activeEditor);
                
                // Trigger input event
                const event = new Event('input', { bubbles: true, cancelable: true });
                activeEditor.dispatchEvent(event);
            });
        }
        
        if (!document.querySelector('[data-action="checkbox"]')) {
            const checkboxButton = document.createElement('button');
            checkboxButton.className = 'toolbar-btn';
            checkboxButton.setAttribute('data-action', 'checkbox');
            checkboxButton.setAttribute('title', 'Task List Item');
            checkboxButton.textContent = '☑';
            toolbar.appendChild(checkboxButton);
            
            // Add the event listener for the new button
            checkboxButton.addEventListener('click', function() {
                const activeEditor = document.querySelector('.split-view-container.active-subview') ? 
                    document.getElementById('splitEditor') : 
                    document.getElementById('editor');
                
                markdownPatterns.checkbox.custom(activeEditor);
                
                // Trigger input event
                const event = new Event('input', { bubbles: true, cancelable: true });
                activeEditor.dispatchEvent(event);
            });
        }
    }
});
