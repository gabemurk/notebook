<?php
// Simple markdown test page that works with our dual database system
session_start();

// Fake a user session if not logged in (for testing only)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Markdown Parser Test</title>
    <link rel="stylesheet" href="/css/enhanced-markdown.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
        }
        .test-header {
            background-color: #f5f5f5;
            padding: 10px 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .test-area {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .editor-column, .preview-column {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        .column-header {
            background-color: #e9e9e9;
            padding: 10px 15px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
        }
        textarea {
            width: 100%;
            min-height: 500px;
            padding: 15px;
            border: none;
            resize: vertical;
            font-family: monospace;
            font-size: 14px;
            box-sizing: border-box;
        }
        .preview-content {
            min-height: 500px;
            padding: 15px;
            overflow: auto;
        }
        .toolbar {
            padding: 10px;
            background-color: #f0f0f0;
            border-bottom: 1px solid #ddd;
            display: flex;
            gap: 5px;
        }
        button {
            padding: 5px 10px;
            cursor: pointer;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
        }
        button:hover {
            background-color: #45a049;
        }
        .status {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f8f8;
            border-radius: 4px;
        }
        .info {
            background-color: #e7f3fe;
            border-left: 6px solid #2196F3;
            padding: 10px;
            margin: 10px 0;
        }
        .test-sample {
            padding: 10px;
            margin: 10px 0;
            background-color: #f0f0f0;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="test-header">
            <h1>Markdown Parser Test</h1>
            <p>Test the server-side markdown parser with our dual database system</p>
            <div class="info">
                <p><strong>Note:</strong> This test interface allows you to verify the server-side markdown parsing functionality.</p>
            </div>
        </div>
        
        <div class="test-samples">
            <h3>Test Samples (Click to load)</h3>
            <div class="test-sample" id="sample1">
                Basic Formatting (Headers, Bold, Italic, Lists)
            </div>
            <div class="test-sample" id="sample2">
                Advanced Features (Tables, Task Lists, Code Blocks)
            </div>
            <div class="test-sample" id="sample3">
                Complex Example (Mixed Elements)
            </div>
        </div>
        
        <div class="test-area">
            <div class="editor-column">
                <div class="column-header">Markdown Editor</div>
                <div class="toolbar">
                    <button id="parseMd">Parse Markdown</button>
                    <button id="clearMd">Clear</button>
                </div>
                <textarea id="markdownInput" placeholder="Enter markdown here..."></textarea>
            </div>
            <div class="preview-column">
                <div class="column-header">Preview</div>
                <div id="markdownPreview" class="preview-content">
                    <p>Preview will appear here...</p>
                </div>
            </div>
        </div>
        
        <div class="status" id="status">
            Status: Ready
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const markdownInput = document.getElementById('markdownInput');
            const markdownPreview = document.getElementById('markdownPreview');
            const parseBtn = document.getElementById('parseMd');
            const clearBtn = document.getElementById('clearMd');
            const statusElement = document.getElementById('status');
            
            // Sample markdown content
            const samples = {
                sample1: `# Basic Markdown Test
                
## Text Formatting

This is **bold text** and this is *italic text*.
You can also use __underscores__ for bold and _underscores_ for italic.

## Lists

### Unordered List
* Item 1
* Item 2
  * Nested item 2.1
  * Nested item 2.2
* Item 3

### Ordered List
1. First item
2. Second item
3. Third item

## Links and Images

[Link to Google](https://www.google.com)

![Sample Image](https://via.placeholder.com/150)

## Blockquotes

> This is a blockquote
> It can span multiple lines

## Horizontal Rule

---

`,
                sample2: `# Advanced Markdown Features

## Tables

| Header 1 | Header 2 | Header 3 |
|----------|:--------:|---------:|
| Left     | Center   | Right    |
| Cell     | Cell     | Cell     |
| Cell     | Cell     | Cell     |

## Task Lists

- [ ] Uncompleted task
- [x] Completed task
- [ ] Another task
- [x] Final task

## Code Blocks

\`\`\`javascript
// This is a code block with syntax highlighting
function greet(name) {
  console.log(\`Hello, \${name}!\`);
}
greet('World');
\`\`\`

Inline code: \`const x = 10;\`

## Superscript and Subscript

Water: H~2~O
E=mc^2^

## Highlighted Text

This is ==highlighted text==

`,
                sample3: `# Complete Markdown Example

## Text Formatting

This document shows **bold**, *italic*, ~~strikethrough~~, ==highlighted==, H~2~O (subscript), and E=mc^2^ (superscript).

## Lists with Tasks

### Project Tasks
- [x] Create project structure
- [ ] Implement core features
   1. Feature A
   2. Feature B
   3. Feature C
- [x] Write documentation
- [ ] Deploy to production

## Code with Syntax Highlighting

\`\`\`php
<?php
// Database connection function
function connectToDatabase() {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=mydb', 'username', 'password');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
?>
\`\`\`

## Complex Table

| Feature | Client-side | Server-side | Pros | Cons |
|---------|:----------:|:-----------:|:-----|-----:|
| Parsing | Yes | Yes | Fast for user | Browser dependent |
| Storage | IndexedDB | PostgreSQL/SQLite | Offline capability | Sync complexity |
| Security | Low | High | Simple | Needs careful validation |
| Performance | Varies | Consistent | No server load | More reliable |

## Blockquote with Lists

> ### Key Features
> - Dual database support
> - Markdown parsing
> - User authentication
>
> *This system provides reliability and performance.*

## Image and Link

![Markdown Logo](https://markdown-here.com/img/icon256.png)

Visit our [documentation site](https://example.com) for more information.

---

Created on 2025-03-27 by Your Name
`
            };
            
            // Load sample content
            document.getElementById('sample1').addEventListener('click', function() {
                markdownInput.value = samples.sample1;
            });
            
            document.getElementById('sample2').addEventListener('click', function() {
                markdownInput.value = samples.sample2;
            });
            
            document.getElementById('sample3').addEventListener('click', function() {
                markdownInput.value = samples.sample3;
            });
            
            // Parse button click handler
            parseBtn.addEventListener('click', function() {
                const markdown = markdownInput.value;
                statusElement.textContent = 'Status: Parsing...';
                
                // Call our server-side parser
                fetch('/parse_markdown.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'markdown': markdown,
                        'save': 'false'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        markdownPreview.innerHTML = data.html;
                        statusElement.textContent = 'Status: Parsing successful';
                    } else {
                        markdownPreview.innerHTML = `<div class="error">${data.message}</div>`;
                        statusElement.textContent = 'Status: Parsing failed';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    markdownPreview.innerHTML = '<div class="error">Error parsing markdown</div>';
                    statusElement.textContent = 'Status: Error - ' + error.message;
                });
            });
            
            // Clear button click handler
            clearBtn.addEventListener('click', function() {
                markdownInput.value = '';
                markdownPreview.innerHTML = '<p>Preview will appear here...</p>';
                statusElement.textContent = 'Status: Cleared';
            });
            
            // Auto-parse on input with debounce
            let debounceTimer;
            markdownInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    if (markdownInput.value.trim() !== '') {
                        parseBtn.click();
                    }
                }, 1000);
            });
        });
    </script>
</body>
</html>
