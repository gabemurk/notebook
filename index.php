<?php
session_start();
require_once __DIR__ . '/database/enhanced_db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notebook - Simple Mode</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'primary': '#2563eb',
                        'primary-dark': '#1d4ed8',
                        'secondary': '#475569',
                        'success': '#10b981',
                        'danger': '#ef4444',
                        'warning': '#f59e0b',
                        'info': '#3b82f6',
                    }
                }
            }
        }
    </script>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'primary': '#2563eb',
                        'primary-dark': '#1d4ed8',
                        'secondary': '#475569',
                        'success': '#10b981',
                        'danger': '#ef4444',
                        'warning': '#f59e0b',
                        'info': '#3b82f6',
                    }
                }
            }
        }
    </script>
    
    <!-- Markdown Styles -->
    <link rel="stylesheet" href="/css/enhanced-markdown.css">
    <style>
        /* Custom styling that Tailwind doesn't handle */
        .markdown-editor {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
            font-size: 14px;
            outline: none;
            resize: none;
        }
        
        /* Status indicator dots */
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .status-dot.online {
            background-color: #10b981; /* green-500 */
        }
        
        .status-dot.offline {
            background-color: #ef4444; /* red-500 */
        }
        
        .status-dot.warning {
            background-color: #f59e0b; /* amber-500 */
        }
        
        /* Hide scrollbar for cleaner UI but keep functionality */
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
            line-height: 1.6;
            resize: none;
            outline: none;
            box-sizing: border-box;
            overflow-y: auto;
        }
        
        .view-controls {
            background: #252526;
            padding: 8px 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            min-height: 40px;
            box-sizing: border-box;
            border-bottom: 1px solid #333;
        }
        
        .view-controls button {
            padding: 8px 16px;
            cursor: pointer;
            border: none;
            background-color: #333;
            color: #ccc;
            border-radius: 3px;
            font-weight: 500;
            transition: all 0.2s ease;
            outline: none;
        }
        
        .view-controls button:hover {
            background-color: #444;
            color: #fff;
        }
        
        .view-controls button.active {
            background-color: #0e639c;
            color: white;
        }
        
        .view-container {
            display: none;
            width: 100%;
        }
        
        .active-view {
            display: block;
        }
        
        .mindmap-container {
            width: 100%;
            height: 500px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: auto;
            background-color: #f9f9f9;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .user-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #333;
            padding: 8px 15px;
            color: #ccc;
            z-index: 100;
        }
        
        .status-indicators {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .db-status, .save-status {
            display: flex;
            align-items: center;
            font-size: 12px;
            color: #666;
        }
        
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
            background-color: #ccc; /* Default color */
        }
        
        .status-dot.connected { background-color: #4CAF50; } /* Green for connected */
        .status-dot.disconnected { background-color: #f44336; } /* Red for disconnected */
        .status-dot.checking { background-color: #FFC107; } /* Yellow for checking */
        
        .save-status .status-icon {
            display: inline-block;
            width: 12px;
            height: 12px;
            margin-right: 5px;
            background-size: contain;
            background-repeat: no-repeat;
        }
        
        .save-status.saved .status-icon {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%234CAF50" width="24" height="24"><path d="M0 0h24v24H0z" fill="none"/><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>');
        }
        
        .save-status.saving .status-icon {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23FFC107" width="24" height="24"><path d="M0 0h24v24H0z" fill="none"/><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>');
            animation: rotate 2s linear infinite;
        }
        
        .save-status.failed .status-icon {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23f44336" width="24" height="24"><path d="M0 0h24v24H0z" fill="none"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>');
        }
        
        @keyframes rotate {
            100% { transform: rotate(360deg); }
        }
        
        .user-section button {
            padding: 8px 15px;
            margin-left: 10px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .user-section button:hover {
            background-color: #2980b9;
        }
        
        .search-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f8f8;
            border-radius: 4px;
        }
        
        .search-section input {
            padding: 8px 12px;
            width: 70%;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
        }
        
        .search-section button {
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .note-item {
            margin-bottom: 10px;
            padding: 12px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .markdown-toggle {
            margin-bottom: 10px;
        }
        
        .toggle-btn {
            padding: 5px 15px;
            margin-right: 5px;
            cursor: pointer;
            border: 1px solid #ccc;
            background-color: #f8f8f8;
            border-radius: 4px;
        }
        
        .toggle-btn.active {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        
        .markdown-subview {
            display: none;
            width: 100%;
        }
        
        .active-subview {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .preview {
            height: 100%;
            padding: 15px;
            background-color: #1e1e1e;
            overflow-y: auto;
            color: #e0e0e0;
        }
        
        .preview h1, .preview h2, .preview h3 {
            margin-top: 0.5em;
            margin-bottom: 0.5em;
            color: #e0e0e0;
        }
        
        .preview p {
            margin-bottom: 1em;
        }
        
        .preview strong {
            font-weight: 600;
            color: #fff;
        }
        
        .preview em {
            font-style: italic;
            color: #ccc;
        }
        
        .preview code {
            background-color: #2d2d2d;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
        }
        
        .preview pre {
            background-color: #2d2d2d;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        #editor {
            height: 300px;
            margin-bottom: 20px;
        }
        #searchResults {
            margin-top: 20px;
            border: 1px solid #ccc;
            padding: 10px;
        }
        
        /* Markdown Toolbar Styles */
        .markdown-toolbar {
            display: flex;
            gap: 5px;
            margin-bottom: 0;
            padding: 8px;
            background-color: #252526;
            border-bottom: 1px solid #333;
            flex-wrap: wrap;
        }
        
        .toolbar-btn {
            padding: 5px 10px;
            background-color: #333;
            color: #ccc;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .toolbar-btn:hover {
            background-color: #444;
            color: #fff;
        }
        
        .editor-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        .markdown-toggle {
            display: flex;
            background-color: #252526;
            padding: 5px 10px 0;
        }
        
        .toggle-btn {
            padding: 8px 15px;
            cursor: pointer;
            border: 1px solid #333;
            border-bottom: none;
            background-color: #2d2d2d;
            color: #ccc;
            border-radius: 4px 4px 0 0;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-right: 5px;
        }
        
        .toggle-btn.active {
            background-color: #1e1e1e;
            color: #fff;
        }
        
        .markdown-subview {
            flex: 1;
            display: none;
            height: 100%;
            overflow: auto;
        }
        
        .active-subview {
            display: block;
        }
        
        .editor-line-numbers {
            padding: 15px 10px;
            background-color: #252526;
            color: #858585;
            text-align: right;
            user-select: none;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
            font-size: 14px;
            line-height: 1.6;
            border-right: 1px solid #333;
        }
        
        .split-view {
            display: flex;
            flex: 1;
            overflow: hidden;
            height: calc(100vh - 120px); /* Adjust height to fill window minus header/toolbar */
        }
        
        .search-section, .notes-section {
            padding: 10px;
            border-bottom: 1px solid #3e3e42;
        }
        
        .search-results {
            margin-top: 10px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .search-result {
            background: #2d2d2d;
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            border-left: 3px solid #666;
        }
        
        .search-result:hover {
            background: #3a3a3a;
            border-left-color: #0078d7;
        }
        
        .result-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 4px;
            color: #e0e0e0;
        }
        
        .result-preview {
            font-size: 12px;
            color: #aaa;
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Modal Dialog Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-dialog {
            background-color: #252526;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            width: 450px;
            max-width: 90%;
            padding: 0;
            transform: translateY(-20px);
            transition: transform 0.3s;
        }
        
        .modal-overlay.active .modal-dialog {
            transform: translateY(0);
        }
        
        .modal-header {
            background-color: #333;
            padding: 15px 20px;
            border-bottom: 1px solid #444;
            border-radius: 5px 5px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            margin: 0;
            font-size: 18px;
            color: #e0e0e0;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: #999;
            font-size: 20px;
            cursor: pointer;
        }
        
        .modal-close:hover {
            color: #e0e0e0;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #444;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #e0e0e0;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            background-color: #3a3a3a;
            border: 1px solid #555;
            border-radius: 4px;
            color: #e0e0e0;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0078d7;
            box-shadow: 0 0 0 2px rgba(0, 120, 215, 0.2);
        }
        
        .btn-cancel {
            background-color: #555;
            color: #e0e0e0;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: #0078d7;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            background-color: #0086f0;
        }
        
        .search-section h2, .notes-section h2 {
            font-size: 16px;
            margin: 5px 0 10px 0;
        }
        
        #searchInput {
            width: calc(100% - 10px);
            margin-bottom: 8px;
            background: #333;
            border: 1px solid #555;
            color: #e0e0e0;
            padding: 6px;
            border-radius: 4px;
        }
        
        #searchButton {
            width: 100%;
            margin-bottom: 10px;
        }
        
        .note-item {
            background: #333;
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            border-left: 3px solid #666;
        }
        
        .note-item:hover {
            background: #444;
            border-left-color: #0078d7;
        }
        
        .note-item.active {
            background: #3c3c3c;
            border-left-color: #0078d7;
        }
    </style>
    
    <!-- SIMPLIFIED MODE - Enhanced markdown removed -->
</head>
<body class="bg-gray-900 text-gray-100 dark:bg-gray-900 dark:text-gray-100 overflow-hidden">
    <div class="flex flex-col h-screen overflow-hidden">
        <!-- Notification Container -->
        <div id="notification-container" class="fixed top-4 right-4 z-50 w-72"></div>
        <div class="user-section">
            <div class="status-indicators">
                <div class="db-status" id="dbStatus">
                    <span class="status-dot"></span>
                    <span class="status-text">DB: Checking...</span>
                </div>
                <div class="save-status" id="saveStatus">
                    <span class="status-icon"></span>
                    <span class="status-text">Not saved</span>
                </div>
            </div>
            <span>Welcome, <span id="userDisplayName">Guest</span></span>
            <button id="logoutButton">Logout</button>
            <button id="loginPageButton">Login/Register</button>
        </div>
        
        <div class="view-controls">
            <h1>Notebook</h1>
            <div>
                <button id="markdownViewBtn" class="active">Markdown View</button>
                <button id="mindmapViewBtn">Mind Map View</button>
                <button id="saveButton">Save Note</button>
                <button id="newNoteBtn">New Note</button>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex flex-1 overflow-hidden bg-gray-900">
            <!-- Sidebar for notes navigation and search -->
            <div id="sidebar" class="w-64 bg-gray-800 border-r border-gray-700 flex-shrink-0 transform transition-transform duration-300 ease-in-out overflow-hidden lg:translate-x-0 -translate-x-full lg:static fixed h-full z-30">
                <!-- Search Functionality -->
                <div class="search-section">
                    <h2>Search Notes</h2>
                    <input type="text" id="searchInput" placeholder="Enter search term">
                    <button id="searchButton">Search</button>
                    <div id="searchResults" class="search-results"></div>
                </div>
                
                <!-- User Notes -->
                <div class="notes-section">
                    <h2>Your Notes</h2>
                    <button id="newNoteBtn2" class="block-btn">+ New Note</button>
                    <div id="userNotes">
                        <!-- Notes will be loaded dynamically via loadNotes() -->
                        <div class="loading">Loading notes...</div>
                    </div>
                </div>
            </div>
            
            <!-- Editor wrapper -->
            <div class="editor-wrapper">
                <div id="markdownView" class="view-container active-view">
                    <div class="markdown-toggle">
                        <button id="editorToggleBtn" class="toggle-btn active">Editor</button>
                        <button id="previewToggleBtn" class="toggle-btn">Preview</button>
                        <button id="splitViewBtn" class="toggle-btn">Split View</button>
                        <!-- Screen reader announcement for view changes -->
                        <div id="view-announcement" class="sr-only" aria-live="polite"></div>
                    </div>
                    <div class="editor-container" id="editorViewContainer">
                        <div id="editorContainer" class="markdown-subview active-subview">
                            <div class="markdown-toolbar">
                                <button class="toolbar-btn" data-action="bold" title="Bold">B</button>
                                <button class="toolbar-btn" data-action="italic" title="Italic">I</button>
                                <button class="toolbar-btn" data-action="heading" title="Heading">H</button>
                                <button class="toolbar-btn" data-action="link" title="Link">🔗</button>
                                <button class="toolbar-btn" data-action="image" title="Image">🖼️</button>
                                <button class="toolbar-btn" data-action="list" title="List">•</button>
                                <button class="toolbar-btn" data-action="code" title="Code">{ }</button>
                                <button class="toolbar-btn" data-action="quote" title="Quote">"</button>
                                <button class="toolbar-btn" data-action="hr" title="Horizontal Rule">―</button>
                            </div>
                            <div class="split-view">
                                <div class="editor-line-numbers" id="lineNumbers">1</div>
                                <textarea id="editor" class="markdown-editor" placeholder="Write your markdown here..."></textarea>
                            </div>
                        </div>
                        <div id="previewContainer" class="markdown-subview">
                            <div id="preview" class="preview"></div>
                        </div>
                        <div id="splitViewContainer" class="markdown-subview">
                            <div style="display: flex; height: 100%;">
                                <div style="flex: 1; display: flex; flex-direction: column;">
                                    <div class="markdown-toolbar">
                                        <button class="toolbar-btn" data-action="bold" title="Bold">B</button>
                                        <button class="toolbar-btn" data-action="italic" title="Italic">I</button>
                                        <button class="toolbar-btn" data-action="heading" title="Heading">H</button>
                                        <button class="toolbar-btn" data-action="code" title="Code">{ }</button>
                                    </div>
                                    <div class="split-view" style="flex: 1;">
                                        <div class="editor-line-numbers" id="splitLineNumbers">1</div>
                                        <textarea id="splitEditor" class="markdown-editor" placeholder="Write your markdown here..."></textarea>
                                    </div>
                                </div>
                                <div style="flex: 1;">
                                    <div id="splitPreview" class="preview"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="mindmapView" class="view-container">
                    <div id="mindmap" class="mindmap-container"></div>
                </div>
            </div>
    
            <!-- Sidebar toggle button -->
            <button class="sidebar-toggle" id="sidebarToggle">☰</button>
        </div> <!-- End of main-content -->
    </div> <!-- End of app-container -->
    
    <!-- New Note Dialog Modal -->
    <div class="modal-overlay" id="newNoteModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">Create New Note</h3>
                <button class="modal-close" id="closeModalBtn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="noteTitle">Title</label>
                    <input type="text" class="form-control" id="noteTitle" placeholder="Enter note title">
                </div>
                <div class="form-group">
                    <label for="noteCategory">Category (optional)</label>
                    <input type="text" class="form-control" id="noteCategory" placeholder="Enter category">
                </div>
                <div class="form-group">
                    <label for="noteTemplate">Template</label>
                    <select class="form-control" id="noteTemplate">
                        <option value="blank">Blank Note</option>
                        <option value="basic">Basic Structure</option>
                        <option value="meeting">Meeting Notes</option>
                        <option value="todo">To-Do List</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" id="cancelNoteBtn">Cancel</button>
                <button class="btn-primary" id="createNoteBtn">Create Note</button>
            </div>
        </div>
    </div>

    <!-- Include only D3.js for our custom mind map implementation -->
    <script src="https://d3js.org/d3.v7.min.js"></script>
        <!-- Custom mind map implementation with improved visualization -->
    <script>
      // Mind map functionality removed in simplified version
      window.addEventListener('load', function() {
        // Basic initialization only
          
          try {
            // Clear the container first
            container.innerHTML = '';
            
            // Add a loading indicator
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'mind-map-loading';
            loadingDiv.innerHTML = '<div class="spinner"></div> Generating mind map...';
            container.appendChild(loadingDiv);
            
            // Use setTimeout to allow the loading indicator to render
            setTimeout(() => {
              try {
                // Parse the markdown to create a hierarchical structure
                const data = parseMarkdownToHierarchy(markdown);
                
                // Remove loading indicator
                container.removeChild(loadingDiv);
                
                // Check if we have meaningful data to display
                if (data.children.length === 0) {
                  container.innerHTML = '<div class="mind-map-empty">No headings found to create mind map.<br>Add headings with # to visualize structure.</div>';
                  return;
                }
                
                // Create the mind map visualization
                createMindMap(data, container);
              } catch (error) {
                container.innerHTML = '<div class="mind-map-error">Error rendering mind map: ' + error.message + '</div>';
                console.error('Mind map error:', error);
              }
            }, 50);
          } catch (error) {
            console.error('Mind map initialization error:', error);
            container.innerHTML = '<div class="mind-map-error">Error initializing mind map: ' + error.message + '</div>';
          }
        };
        
        // Function to parse markdown into a hierarchical structure
        function parseMarkdownToHierarchy(markdown) {
          // Get the title from the first significant line or heading
          let title = 'Mind Map';
          const lines = markdown.split('\n');
          
          // Try to find a title (first heading or first non-empty line)
          for (const line of lines) {
            const headingMatch = line.match(/^#\s+(.+)$/);
            if (headingMatch) {
              title = headingMatch[1].trim();
              break;
            } else if (line.trim()) {
              // Use first non-empty line as fallback
              title = line.trim();
              // Limit length
              if (title.length > 30) title = title.substring(0, 27) + '...';
              break;
            }
          }
          
          // Create root node with the extracted title
          const root = {
            name: title,
            type: 'root',
            children: []
          };
          
          // Track nodes at each heading level
          const nodeStack = [root];
          let prevLevel = 0;
          
          // Process each line for headings
          lines.forEach((line, index) => {
            // Check if line is a heading
            const match = line.match(/^(#+)\s+(.+)$/);
            if (match) {
              const level = match[1].length;
              const text = match[2].trim();
              
              // Create new node with metadata
              const node = {
                name: text,
                type: 'heading',
                level: level,
                lineNumber: index,
                children: []
              };
              
              // Add bullet points under this heading if available
              let bulletPoints = [];
              let i = index + 1;
              while (i < lines.length) {
                const nextLine = lines[i];
                // Stop if we hit another heading
                if (nextLine.match(/^#+\s+/)) break;
                
                // Check for bullet points
                const bulletMatch = nextLine.match(/^\s*[*-]\s+(.+)$/);
                if (bulletMatch) {
                  bulletPoints.push(bulletMatch[1].trim());
                }
                i++;
              }
              
              // If there are bullet points, add them as children
              if (bulletPoints.length > 0) {
                node.hasBullets = true;
                bulletPoints.forEach((point, idx) => {
                  node.children.push({
                    name: point,
                    type: 'bullet',
                    parent: node,
                    index: idx
                  });
                });
              }
              
              // Adjust the stack based on heading level
              if (level > prevLevel) {
                // Child of previous node
                if (nodeStack.length > 0) {
                  nodeStack[nodeStack.length - 1].children.push(node);
                }
              } else {
                // Go back up the tree
                while (nodeStack.length > level) {
                  nodeStack.pop();
                }
                // Add to parent
                if (nodeStack.length > 0) {
                  nodeStack[nodeStack.length - 1].children.push(node);
                } else {
                  // If we've popped all nodes, add to root
                  root.children.push(node);
                }
              }
              
              // Push this node to the stack
              nodeStack.push(node);
              prevLevel = level;
            }
          });
          
          return root;
        }
        
        // Function to create the mind map visualization
        function createMindMap(data, container) {
          // Set up dimensions
          const width = container.offsetWidth;
          const height = Math.max(500, data.children.length * 50);
          const margin = { top: 40, right: 150, bottom: 40, left: 150 };
          
          // Create the SVG element with zoom/pan capability
          const svg = d3.select(container)
            .append("svg")
            .attr("width", width)
            .attr("height", height)
            .call(d3.zoom().on("zoom", function(event) {
              g.attr("transform", event.transform);
            }))
            .append("g");
          
          // Add instruction text
          svg.append("text")
            .attr("x", width / 2)
            .attr("y", 20)
            .attr("text-anchor", "middle")
            .style("font-size", "12px")
            .style("fill", "#999")
            .text("Scroll to zoom, drag to pan");
          
          // Create a container for the tree
          const g = svg.append("g")
            .attr("transform", `translate(${margin.left},${height/2})`);
          
          // Use tree layout with larger spacing
          const treeLayout = d3.tree()
            .size([height - margin.top - margin.bottom, width - margin.left - margin.right - 80])
            .separation((a, b) => (a.parent === b.parent ? 1.2 : 1.8));
          
          // Convert the data to D3 hierarchy
          const root = d3.hierarchy(data);
          
          // Count visible descendants for better spacing
          root.count();
          
          // Assign positions to nodes
          const treeData = treeLayout(root);
          
          // Define a curve for the links
          const linkGenerator = d3.linkHorizontal()
            .x(d => d.y)
            .y(d => d.x);
          
          // Add links between nodes with transition
          const links = g.selectAll(".link")
            .data(treeData.links())
            .enter()
            .append("path")
            .attr("class", "link")
            .attr("fill", "none")
            .attr("stroke", d => d.target.data.type === 'bullet' ? "#aaa" : "#666")
            .attr("stroke-width", d => d.target.data.type === 'bullet' ? 1 : 1.5)
            .attr("stroke-dasharray", d => d.target.data.type === 'bullet' ? "3,3" : "none")
            .attr("d", linkGenerator);
          
          // Add a subtle animation to the links
          links.each(function(d) {
            const length = this.getTotalLength();
            d3.select(this)
              .attr("stroke-dasharray", `${length} ${length}`)
              .attr("stroke-dashoffset", length)
              .transition()
              .duration(500)
              .delay(d.source.depth * 100)
              .attr("stroke-dashoffset", 0);
          });
          
          // Add node groups
          const nodes = g.selectAll(".node")
            .data(treeData.descendants())
            .enter()
            .append("g")
            .attr("class", d => `node node-${d.data.type}`)
            .attr("transform", d => `translate(${d.y},${d.x})`);
          
          // Add background to nodes
          nodes.append("rect")
            .attr("rx", 6)
            .attr("ry", 6)
            .attr("x", d => d.children ? -8 : 8)
            .attr("y", -15)
            .attr("width", d => {
              // Calculate width based on text length
              const textLength = d.data.name.length;
              return d.data.type === 'bullet' ? textLength * 6 + 10 : textLength * 7 + 20;
            })
            .attr("height", 30)
            .attr("fill", d => {
              if (d.data.type === 'root') return "#3f51b5";
              if (d.data.type === 'bullet') return "#f5f5f5";
              
              // For headings, use color based on level
              const colors = ["#4CAF50", "#2196F3", "#9C27B0", "#FF9800", "#607D8B"];
              return colors[Math.min(d.depth - 1, colors.length - 1)];
            })
            .attr("opacity", d => d.data.type === 'bullet' ? 0.7 : 0.9)
            .attr("transform", d => {
              const width = d.data.type === 'bullet' ? d.data.name.length * 6 + 10 : d.data.name.length * 7 + 20;
              return d.children ? `translate(-${width}, 0)` : `translate(0, 0)`;
            })
            .style("filter", "drop-shadow(2px 2px 2px rgba(0,0,0,0.2))");
          
          // Add text to nodes
          nodes.append("text")
            .attr("dy", "0.31em")
            .attr("x", d => d.children ? -15 : 15)
            .attr("text-anchor", d => d.children ? "end" : "start")
            .text(d => d.data.name)
            .style("font-size", d => d.data.type === 'root' ? "16px" : d.data.type === 'bullet' ? "12px" : "14px")
            .style("font-weight", d => d.data.type === 'bullet' ? "normal" : "bold")
            .style("fill", d => d.data.type === 'root' || d.depth === 1 ? "#fff" : d.data.type === 'bullet' ? "#333" : "#fff")
            .attr("transform", d => {
              const offset = d.children ? (d.data.name.length * 7) : 0;
              return `translate(-${offset}, 5)`;
            })
            .style("pointer-events", "none");
          
          // Add hover effect and click handling
          nodes.selectAll("rect")
            .on("mouseover", function() { 
              d3.select(this).transition().duration(300).attr("opacity", 1); 
            })
            .on("mouseout", function(e, d) { 
              d3.select(this).transition().duration(300).attr("opacity", d.data.type === 'bullet' ? 0.7 : 0.9); 
            });
        }
      });
    </script>
    
    <!-- Add mind map specific styles -->
    <style>
      .mind-map-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        color: #666;
        height: 100px;
      }
      
      .spinner {
        border: 3px solid rgba(0, 0, 0, 0.1);
        border-radius: 50%;
        border-top: 3px solid #3498db;
        width: 20px;
        height: 20px;
        margin-right: 10px;
        animation: spin 1s linear infinite;
      }
      
      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }
      
      .mind-map-empty {
        padding: 30px;
        text-align: center;
        color: #666;
        background: #f5f5f5;
        border-radius: 4px;
        margin: 20px 0;
      }
      
      .mind-map-error {
        padding: 20px;
        color: #721c24;
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        margin: 20px 0;
      }
    </style>
    
    <!-- Include our notes fix JavaScript file -->
    <script src="fix_notes.js"></script>
    
    <!-- Include the external JavaScript file -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const editor = document.getElementById('editor');
            const splitEditor = document.getElementById('splitEditor');
            const preview = document.getElementById('preview');
            
            // Check database status and load notes on page load
            checkDatabaseStatus();
            // Load notes from the database
            loadNotes();
            
            // Define global loadNote function so it's accessible everywhere
            window.loadNote = function(noteId) {
                console.log('Loading note ID:', noteId);
                // Show loading indicator
                if (typeof showNotification === 'function') {
                    showNotification('Loading note...', 'info');
                }
                
                fetch('load_notes.php?note_id=' + noteId)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Note data received:', data);
                        if (data.success && data.note) {
                            // Set current note id for saving
                            if (typeof window.currentNoteId !== 'undefined') {
                                window.currentNoteId = noteId;
                            }
                            
                            // Update editor content and title
                            const editor = document.getElementById('editor');
                            const splitEditor = document.getElementById('splitEditor');
                            const titleInput = document.getElementById('titleInput');
                            
                            if (editor) editor.value = data.note.content || '';
                            if (splitEditor) splitEditor.value = data.note.content || '';
                            if (titleInput) titleInput.value = data.note.title || 'Untitled Note';
                            
                            // Update preview if needed
                            updatePreview();
                            
                            // Clear error message if any
                            showNotification('Note loaded successfully', 'success');
                        } else {
                            showNotification('Error loading note: ' + (data.message || 'Unknown error'), 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading note:', error);
                        showNotification('Error loading note: ' + error.message, 'error');
                    });
            };
            
            // Add click handlers to the hardcoded notes
            document.addEventListener('DOMContentLoaded', function() {
                // Add click event listeners to all note items in the sidebar
                document.querySelectorAll('.note-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const noteId = this.getAttribute('data-id');
                        console.log('Clicked note ID:', noteId);
                        
                        // Remove active class from all note items
                        document.querySelectorAll('.note-item').forEach(i => {
                            i.classList.remove('active');
                        });
                        
                        // Add active class to clicked note item
                        this.classList.add('active');
                        
                        // Load the note content using the global function
                        window.loadNote(noteId);
                    });
                });
            });
            
            // Function to manually display test notes (keeping for reference)
            function displayTestNotes() {
                const userNotesElement = document.getElementById('userNotes');
                if (!userNotesElement) {
                    console.error('Error: userNotes element not found');
                    return;
                }
                
                // Clear the container
                userNotesElement.innerHTML = '';
                
                // Create test notes array manually
                const testNotes = [
                    { id: 7, title: 'Test Note', updated_at: '2025-03-27 01:58' },
                    { id: 8, title: 'Test note 1', updated_at: '2025-03-27 01:58' },
                    { id: 9, title: 'Test note 2', updated_at: '2025-03-27 01:58' },
                    { id: 10, title: 'Test note 3', updated_at: '2025-03-27 01:58' }
                ];
                
                console.log('Displaying test notes:', testNotes);
                
                // Create list of notes
                testNotes.forEach(note => {
                    // Create note item element
                    const noteItem = document.createElement('div');
                    noteItem.className = 'note-item';
                    noteItem.setAttribute('data-id', note.id);
                    noteItem.innerHTML = `
                        <div class="note-title">${note.title}</div>
                        <div class="note-date">${note.updated_at}</div>
                    `;
                    
                    // Add click event to load note
                    noteItem.addEventListener('click', function() {
                        document.querySelectorAll('.note-item').forEach(item => {
                            item.classList.remove('active');
                        });
                        this.classList.add('active');
                        loadNote(note.id);
                    });
                    
                    userNotesElement.appendChild(noteItem);
                });
            }
            const splitPreview = document.getElementById('splitPreview');
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const newNoteBtn = document.getElementById('newNoteBtn');
            const newNoteBtn2 = document.getElementById('newNoteBtn2');
            const editorToggleBtn = document.getElementById('editorToggleBtn');
            const previewToggleBtn = document.getElementById('previewToggleBtn');
            const splitViewBtn = document.getElementById('splitViewBtn');
            const editorContainer = document.getElementById('editorContainer');
            const previewContainer = document.getElementById('previewContainer');
            const splitViewContainer = document.getElementById('splitViewContainer');
            const lineNumbers = document.getElementById('lineNumbers');
            const splitLineNumbers = document.getElementById('splitLineNumbers');
            const saveButton = document.getElementById('saveButton');
            const markdownViewBtn = document.getElementById('markdownViewBtn');
            const mindmapViewBtn = document.getElementById('mindmapViewBtn');
            
            // Function to update preview
            function updatePreview() {
                // Safely get references to all necessary elements
                const editorContainer = document.getElementById('editorContainer');
                const previewContainer = document.getElementById('previewContainer');
                const splitViewContainer = document.getElementById('splitViewContainer');
                const editor = document.getElementById('editor');
                const splitEditor = document.getElementById('splitEditor');
                const preview = document.getElementById('preview');
                const splitPreview = document.getElementById('splitPreview');
                
                // Safety check
                if (!editor || !splitEditor || !preview || !splitPreview) {
                    console.error('Missing editor or preview elements');
                    return;
                }
                
                // First, ensure editors are synchronized
                if (splitViewContainer && splitViewContainer.style.display === 'block') {
                    // In split view, sync from split editor to main editor
                    editor.value = splitEditor.value;
                } else if (editorContainer && editorContainer.style.display === 'block') {
                    // In editor view, sync from main editor to split editor
                    splitEditor.value = editor.value;
                }
                
                // Get content from the appropriate editor
                let activeEditor = (splitViewContainer && splitViewContainer.style.display === 'block') ? splitEditor : editor;
                const content = activeEditor.value;
                console.log('Updating markdown preview, content length:', content.length);
                
                // Show loading indicator
                preview.innerHTML = '<div class="loading">Processing markdown...</div>';
                splitPreview.innerHTML = '<div class="loading">Processing markdown...</div>';
                
                // Get current note ID if available
                let currentNoteId = null;
                const currentNoteIdElem = document.getElementById('currentNoteId');
                if (currentNoteIdElem) {
                    currentNoteId = currentNoteIdElem.value || null;
                }
                
                // Use server-side markdown parser for more reliable rendering
                fetch('/parse_markdown.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'markdown': content,
                        'save': 'false',
                        'note_id': currentNoteId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update both preview areas with the parsed HTML
                        preview.innerHTML = data.html;
                        splitPreview.innerHTML = data.html;
                        console.log('Preview updated with server-side parser');
                    } else {
                        console.error('Server parsing failed, falling back to client-side parser');
                        // Fallback to client-side parser
                        const markdownParser = new EnhancedMarkdown({
                            highlight: true,
                            linkNewWindow: true,
                            tables: true,
                            tasklists: true
                        });
                        
                        const html = markdownParser.parse(content);
                        preview.innerHTML = html;
                        splitPreview.innerHTML = html;
                    }
                })
                .catch(error => {
                    console.error('Error parsing markdown:', error);
                    // Fallback to client-side parser
                    const markdownParser = new EnhancedMarkdown({
                        highlight: true,
                        linkNewWindow: true,
                        tables: true,
                        tasklists: true
                    });
                    
                    const html = markdownParser.parse(content);
                    preview.innerHTML = html;
                    splitPreview.innerHTML = html;
                });
            }
            
            // Sync split editor with main editor
            function syncEditors() {
                splitEditor.value = editor.value;
            }
            
            // Function to update line numbers
            function updateLineNumbers() {
                const lines = editor.value.split('\n').length;
                let lineNumbersHTML = '';
                for (let i = 1; i <= lines; i++) {
                    lineNumbersHTML += i + '<br>';
                }
                lineNumbers.innerHTML = lineNumbersHTML;
                splitLineNumbers.innerHTML = lineNumbersHTML;
            }
            
            // Event listeners for both editors
            editor.addEventListener('input', function() {
                updatePreview();
                updateLineNumbers();
                syncEditors();
            });
            
            // Single event listener for split editor to enable bidirectional editing
            splitEditor.addEventListener('input', function() {
                // Sync the main editor with split editor content
                editor.value = splitEditor.value;
                // Update the preview and line numbers
                updatePreview();
                updateLineNumbers();
            });
            
            // Simple and reliable view switching without transitions
            function switchView(viewType) {
                console.log('Switching view to:', viewType);
                
                // Get all containers and buttons
                const editorContainer = document.getElementById('editorContainer');
                const previewContainer = document.getElementById('previewContainer');
                const splitViewContainer = document.getElementById('splitViewContainer');
                const editorToggleBtn = document.getElementById('editorToggleBtn');
                const previewToggleBtn = document.getElementById('previewToggleBtn');
                const splitViewBtn = document.getElementById('splitViewBtn');
                const editor = document.getElementById('editor');
                const splitEditor = document.getElementById('splitEditor');
                
                if (!editorContainer || !previewContainer || !splitViewContainer) {
                    console.error('Missing view containers');
                    return;
                }
                
                if (!editor || !splitEditor) {
                    console.error('Missing editor elements');
                    return;
                }
                
                // Remove active class from all buttons
                editorToggleBtn.classList.remove('active');
                previewToggleBtn.classList.remove('active');
                splitViewBtn.classList.remove('active');
                
                // Sync editors to preserve content
                if (editor.value || splitEditor.value) {
                    const content = editor.value || splitEditor.value;
                    editor.value = content;
                    splitEditor.value = content;
                }
                
                // Hide all containers
                editorContainer.style.display = 'none';
                previewContainer.style.display = 'none';
                splitViewContainer.style.display = 'none';
                
                // Basic editor styling - applied every time
                const editorStyles = {
                    display: 'block',
                    visibility: 'visible',
                    width: '100%',
                    height: 'auto',
                    minHeight: '400px',
                    padding: '15px',
                    color: '#333',
                    backgroundColor: '#f9f9f9',
                    border: '1px solid #ccc',
                    borderRadius: '4px',
                    fontFamily: 'Consolas, monospace',
                    fontSize: '16px',
                    lineHeight: '1.6',
                    boxSizing: 'border-box'
                };
                
                // Apply styling to both editors
                Object.assign(editor.style, editorStyles);
                Object.assign(splitEditor.style, editorStyles);
                
                // Show the appropriate container and activate the correct button
                if (viewType === 'editor') {
                    editorContainer.style.display = 'block';
                    editorToggleBtn.classList.add('active');
                    setTimeout(() => {
                        editor.focus();
                        console.log('Editor focused, content length:', editor.value.length);
                    }, 50);
                } else if (viewType === 'preview') {
                    previewContainer.style.display = 'block';
                    previewToggleBtn.classList.add('active');
                    // Update preview content
                    if (typeof updatePreview === 'function') {
                        updatePreview();
                    }
                } else { // Default to split view
                    splitViewContainer.style.display = 'block';
                    splitViewBtn.classList.add('active');
                    setTimeout(() => {
                        splitEditor.focus();
                        console.log('Split editor focused, content length:', splitEditor.value.length);
                    }, 50);
                    // Update preview content
                    if (typeof updatePreview === 'function') {
                        updatePreview();
                    }
                }
                
                // Save user preference
                localStorage.setItem('preferredView', viewType);
                
                // Screen reader announcement
                const announcement = document.getElementById('view-announcement');
                if (announcement) {
                    announcement.textContent = `Switched to ${viewType} view`;
                }
                
                console.log('View switched successfully to:', viewType);
            }
                
                // Store the current view preference in localStorage
                localStorage.setItem('preferredView', viewType);
                
                // Announce the view change for screen readers
                const announcement = document.getElementById('view-announcement');
                if (announcement) {
                    announcement.textContent = `Switched to ${viewType} view`;
                }
                
                // Debugging output
                console.log('View switch complete. Active containers:', {
                    editor: editorContainer.style.display,
                    preview: previewContainer.style.display,
                    split: splitViewContainer.style.display
                });
            }
            
            // Add event listeners for view switching
            editorToggleBtn.addEventListener('click', function() {
                switchView('editor');
            });
            
            previewToggleBtn.addEventListener('click', function() {
                switchView('preview');
            });
            
            splitViewBtn.addEventListener('click', function() {
                switchView('split');
            });
            
            // Add screen reader only style
            const style = document.createElement('style');
            style.textContent = `
                .sr-only {
                    position: absolute;
                    width: 1px;
                    height: 1px;
                    padding: 0;
                    margin: -1px;
                    overflow: hidden;
                    clip: rect(0, 0, 0, 0);
                    white-space: nowrap;
                    border-width: 0;
                }
            `;
            document.head.appendChild(style);
            
            // ULTRA-SIMPLIFIED MARKDOWN EDITOR
            // Absolute minimum functionality to ensure it works
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Setting up ULTRA-BASIC markdown editor...');
                
                // Get only essential elements
                const editor = document.getElementById('editor');
                const preview = document.getElementById('preview');
                const previewBtn = document.getElementById('previewToggleBtn');
                const editorBtn = document.getElementById('editorToggleBtn');
                const editorContainer = document.getElementById('editorContainer');
                const previewContainer = document.getElementById('previewContainer');
                
                // Basic editor styles
                if (editor) {
                    editor.style.width = '100%';
                    editor.style.height = '500px';
                    editor.style.padding = '15px';
                    editor.style.border = '3px solid #000';
                    editor.style.fontSize = '18px';
                    editor.style.fontFamily = 'monospace';
                    editor.style.color = '#000';
                    editor.style.backgroundColor = '#fff';
                    editor.style.display = 'block';
                    editor.style.visibility = 'visible';
                    
                    // Set some sample content
                    if (!editor.value) {
                        editor.value = '# Simple Markdown Editor\n\nThis is a basic version with minimal features.\n\n- List item\n- Another item\n\nNo fancy stuff here.';
                    }
                } else {
                    console.error('Editor element not found!');
                }
                
                // Basic preview styles
                if (preview) {
                    preview.style.padding = '15px';
                    preview.style.border = '1px solid #ccc';
                    preview.style.minHeight = '500px';
                    preview.style.backgroundColor = '#fff';
                    preview.style.color = '#000';
                }
                
                // Show editor by default
                if (editorContainer) editorContainer.style.display = 'block';
                if (previewContainer) previewContainer.style.display = 'none';
                
                // Basic tab styling
                if (editorBtn) {
                    editorBtn.style.backgroundColor = '#007bff';
                    editorBtn.style.color = 'white';
                    editorBtn.style.fontWeight = 'bold';
                }
                
                // SIMPLIFIED preview function - just basic HTML conversion
                window.updatePreview = function() {
                    if (!preview || !editor) return;
                    
                    // Get markdown content
                    const markdown = editor.value;
                    
                    // Extremely basic markdown to HTML
                    let html = markdown
                        // Escape HTML first for security
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        // Basic heading
                        .replace(/^# (.*)/gm, '<h1>$1</h1>')
                        // Line breaks
                        .replace(/\n/g, '<br>');
                    
                    // Set HTML content
                    preview.innerHTML = html;
                    console.log('Basic preview updated');
                };
                
                // Basic tab switching
                if (editorBtn) {
                    editorBtn.onclick = function() {
                        if (editorContainer) editorContainer.style.display = 'block';
                        if (previewContainer) previewContainer.style.display = 'none';
                        editorBtn.style.backgroundColor = '#007bff';
                        editorBtn.style.color = 'white';
                        if (previewBtn) {
                            previewBtn.style.backgroundColor = '#f5f5f5';
                            previewBtn.style.color = '#333';
                        }
                        editor.focus();
                    };
                }
                
                if (previewBtn) {
                    previewBtn.onclick = function() {
                        if (editorContainer) editorContainer.style.display = 'none';
                        if (previewContainer) previewContainer.style.display = 'block';
                        previewBtn.style.backgroundColor = '#007bff';
                        previewBtn.style.color = 'white';
                        if (editorBtn) {
                            editorBtn.style.backgroundColor = '#f5f5f5';
                            editorBtn.style.color = '#333';
                        }
                        window.updatePreview();
                    };
                }
                
                // Initial preview update
                window.updatePreview();
                
                // Set initial content
                if (editor && !editor.value) {
                    editor.value = '# Ultra Simple Editor\n\nThis is a bare-bones version for testing.\n\nType here to see it work.';
                }
                
                // Initial preview update
                window.updatePreview();
                
                console.log('Ultra-basic markdown editor setup complete - functionality minimized');
            });
            
            /* Basic CSS for editor tabs */
            .toggle-btn {
                color: #333 !important;
                }
                
                .toggle-btn:last-child {
                    border-right: none !important;
                }
                
                .toggle-btn.active {
                    background-color: #007bff !important;
                    color: white !important;
                    font-weight: bold !important;
                }
                
                /* Fix preview containers */
                #preview, #splitPreview {
                    padding: 15px !important;
                    background-color: #fff !important;
                    border: 1px solid #ddd !important;
                    border-radius: 4px !important;
                    font-size: 16px !important;
                    line-height: 1.7 !important;
                    color: #333 !important;
                    min-height: 400px !important;
                    overflow: auto !important;
                }
                
                /* Ensure all view containers are properly styled */
                #editorContainer, #previewContainer, #splitViewContainer {
                    display: none;
                    width: 100% !important;
                }
                
                /* Fix split view layout */
                #splitViewContainer {
                    display: flex !important;
                    flex-direction: row !important;
                    gap: 20px !important;
                }
                
                #splitEditorWrapper, #splitPreviewWrapper {
                    flex: 1 !important;
                    min-width: 0 !important;
                }
            `;
            document.head.appendChild(fixedEditorStyles);
            
            // Set the appropriate view
            switchView(preferredView);
            
            // Update content
            updateLineNumbers();
            updatePreview();
            syncEditors();
            
            // Force content to display properly
            editor.style.display = 'block';
            splitEditor.style.display = 'block';
            
            // Log editor state
            console.log('Editor state:', {
                value: editor.value,
                display: editor.style.display,
                visibility: editor.style.visibility,
                height: editor.style.height
            });
            
            // Make sure split view works properly by reapplying the view
            setTimeout(() => switchView(preferredView), 100);
            
            // Sidebar toggle
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
            });
            
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            const searchButton = document.getElementById('searchButton');
            
            // Function to search notes
            function searchNotes() {
                const searchTerm = searchInput.value.trim();
                if (!searchTerm) {
                    document.getElementById('searchResults').innerHTML = '<div class="no-results">Please enter a search term</div>';
                    return;
                }
                
                document.getElementById('searchResults').innerHTML = '<div class="loading">Searching...</div>';
                
                // AJAX request to search.php
                $.ajax({
                    url: 'search.php',
                    method: 'POST',
                    data: { searchTerm: searchTerm },
                    success: function(response) {
                        try {
                            const results = typeof response === 'string' ? JSON.parse(response) : response;
                            displaySearchResults(results);
                        } catch (e) {
                            console.error('Error parsing search results:', e);
                            document.getElementById('searchResults').innerHTML = '<div class="error">Error searching notes</div>';
                        }
                    },
                    error: function() {
                        console.error('Search request failed');
                        document.getElementById('searchResults').innerHTML = '<div class="error">Error connecting to server</div>';
                    }
                });
            }
            
            // Function to display search results
            function displaySearchResults(results) {
                const resultsDiv = document.getElementById('searchResults');
                resultsDiv.innerHTML = '';
                
                if (!results || results.length === 0) {
                    resultsDiv.innerHTML = '<div class="no-results">No notes found</div>';
                    return;
                }
                
                results.forEach(note => {
                    const resultItem = document.createElement('div');
                    resultItem.className = 'search-result';
                    resultItem.dataset.noteId = note.id;
                    
                    const resultTitle = document.createElement('div');
                    resultTitle.className = 'result-title';
                    resultTitle.textContent = note.title || 'Untitled Note';
                    
                    const resultPreview = document.createElement('div');
                    resultPreview.className = 'result-preview';
                    resultPreview.textContent = note.content;
                    
                    resultItem.appendChild(resultTitle);
                    resultItem.appendChild(resultPreview);
                    
                    // Add click handler to load the note
                    resultItem.addEventListener('click', function() {
                        loadNote(note.id);
                    });
                    
                    resultsDiv.appendChild(resultItem);
                });
            }
            
            // Function to load a specific note
            function loadNote(noteId) {
                $.ajax({
                    url: 'load_notes.php',
                    method: 'GET',
                    data: { note_id: noteId },
                    success: function(response) {
                        try {
                            const note = typeof response === 'string' ? JSON.parse(response) : response;
                            if (note && note.content) {
                                // Set the note content in editor
                                editor.value = note.content;
                                splitEditor.value = note.content;
                                
                                // Update preview
                                updatePreview();
                                updateLineNumbers();
                                
                                // Update current note ID
                                currentNoteId = noteId;
                                
                                // Update URL without reloading
                                const newUrl = window.location.pathname + '?note=' + noteId;
                                history.pushState({noteId: noteId}, '', newUrl);
                                
                                // Highlight the active note in the list
                                document.querySelectorAll('.note-item').forEach(item => {
                                    item.classList.remove('active');
                                    if (item.dataset.noteId == noteId) {
                                        item.classList.add('active');
                                    }
                                });
                            }
                        } catch (e) {
                            console.error('Error parsing note data:', e);
                        }
                    },
                    error: function() {
                        console.error('Failed to load note');
                    }
                });
            }
            
            // Add event listeners for search
            if (searchButton) {
                searchButton.addEventListener('click', searchNotes);
            }
            
            // Login/Register button
            const loginPageButton = document.getElementById('loginPageButton');
            if (loginPageButton) {
                loginPageButton.addEventListener('click', function() {
                    window.location.href = 'login.php';
                });
            }
            
            // Logout button
            const logoutButton = document.getElementById('logoutButton');
            if (logoutButton) {
                logoutButton.addEventListener('click', function() {
                    // AJAX request to logout
                    $.ajax({
                        url: 'logout.php',
                        method: 'POST',
                        success: function(response) {
                            // Reload the page after successful logout
                            window.location.reload();
                        }
                    });
                });
            }
            
            // Update UI based on login status
            const userDisplayName = document.getElementById('userDisplayName');
            if (userDisplayName) {
                <?php if (isset($_SESSION['username'])) { ?>
                    userDisplayName.textContent = '<?php echo htmlspecialchars($_SESSION["username"], ENT_QUOTES, 'UTF-8'); ?>';
                    if (loginPageButton) loginPageButton.style.display = 'none';
                    if (logoutButton) logoutButton.style.display = 'inline-block';
                <?php } else { ?>
                    userDisplayName.textContent = 'Guest';
                    if (loginPageButton) loginPageButton.style.display = 'inline-block';
                    if (logoutButton) logoutButton.style.display = 'none';
                <?php } ?>
            }
            
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        searchNotes();
                    }
                });
            }
            
            // New note buttons
            if (newNoteBtn) {
                newNoteBtn.addEventListener('click', showNewNoteModal);
            }
            
            if (newNoteBtn2) {
                newNoteBtn2.addEventListener('click', showNewNoteModal);
            }
            
            // Modal dialog event listeners
            document.getElementById('closeModalBtn').addEventListener('click', hideNewNoteModal);
            document.getElementById('cancelNoteBtn').addEventListener('click', hideNewNoteModal);
            document.getElementById('createNoteBtn').addEventListener('click', createNewNote);
            
            // Close modal when clicking outside
            document.getElementById('newNoteModal').addEventListener('click', function(event) {
                if (event.target === this) {
                    hideNewNoteModal();
                }
            });
            
            // Handle keyboard events for modal
            document.addEventListener('keydown', function(event) {
                if (document.getElementById('newNoteModal').classList.contains('active')) {
                    if (event.key === 'Escape') {
                        hideNewNoteModal();
                    } else if (event.key === 'Enter' && event.ctrlKey) {
                        createNewNote();
                    }
                }
            });
            
            // Show new note modal
            function showNewNoteModal() {
                document.getElementById('newNoteModal').classList.add('active');
                document.getElementById('noteTitle').focus();
            }
            
            // Hide new note modal
            function hideNewNoteModal() {
                document.getElementById('newNoteModal').classList.remove('active');
                document.getElementById('noteTitle').value = '';
                document.getElementById('noteCategory').value = '';
                document.getElementById('noteTemplate').value = 'blank';
            }
            
            // Function to load notes from server and display in sidebar
            function loadNotes() {
                // Check if userNotes element exists
                const userNotesElement = document.getElementById('userNotes');
                if (!userNotesElement) {
                    console.error('Error: userNotes element not found');
                    return;
                }
                
                // Show loading indicator in notes list
                userNotesElement.innerHTML = '<div class="loading">Loading notes...</div>';
                
                // Make AJAX request to get notes - add cache-busting parameter
                fetch('load_notes.php?' + new Date().getTime())
                    .then(response => {
                        // Check if response is ok
                        if (!response.ok) {
                            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                        }
                        
                        // Handle empty responses
                        return response.text().then(text => {
                            if (!text || !text.trim()) {
                                throw new Error('Empty response from server');
                            }
                            
                            try {
                                const data = JSON.parse(text);
                                console.log('Notes data received:', data);
                                return data;
                            } catch (e) {
                                console.error('JSON parse error:', e, 'Response text:', text);
                                throw new Error('Invalid JSON response from server');
                            }
                        });
                    })
                    .then(data => {
                        // Process the json data
                        if (data && data.success) {
                            // Display only notes for the current user - these are filtered by the server
                            displayNotes(data.notes || []);
                            
                            // Show notification about note count
                            const noteCount = data.notes ? data.notes.length : 0;
                            if (noteCount > 0) {
                                if (typeof showNotification === 'function') {
                                    showNotification(`Loaded ${noteCount} note${noteCount !== 1 ? 's' : ''}`, 'success', 2000); // Auto-hide after 2 seconds
                                }
                            }
                            
                            // Log debug info if available
                            if (data.debug) {
                                console.log('Note loading debug info:', data.debug);
                            }
                        } else {
                            // Check again if the element still exists
                            if (document.getElementById('userNotes')) {
                                const msg = data && data.message ? data.message : 'Unknown error';
                                document.getElementById('userNotes').innerHTML = '<div class="error">Error: ' + msg + '</div>';
                                if (typeof showNotification === 'function') {
                                    showNotification('Failed to load notes: ' + msg, 'error');
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading notes:', error);
                        // Check again if the element still exists
                        if (document.getElementById('userNotes')) {
                            document.getElementById('userNotes').innerHTML = '<div class="error">Error: ' + error.message + '</div>';
                            if (typeof showNotification === 'function') {
                                showNotification('Error loading notes: ' + error.message, 'error');
                            }
                        }
                    });
            }
            
            // Function to display notes in sidebar
            function displayNotes(notes) {
                const notesListElem = document.getElementById('userNotes');
                if (!notesListElem) {
                    console.error('Error: userNotes element not found in displayNotes');
                    return;
                }
                
                notesListElem.innerHTML = '';
                
                if (!notes || notes.length === 0) {
                    notesListElem.innerHTML = '<div class="no-notes">No notes found</div>';
                    return;
                }
                
                // Create list of notes
                notes.forEach(note => {
                    // Try to extract title from stored content
                    let title = note.title || 'Untitled Note';
                    
                    // Check if content is a JSON string with embedded metadata 
                    try {
                        const contentObj = JSON.parse(note.content || note.preview);
                        if (contentObj && contentObj.metadata && contentObj.metadata.title) {
                            title = contentObj.metadata.title;
                        }
                    } catch (e) {
                        // Not JSON, use existing title
                    }
                    
                    // Create note item element
                    const noteItem = document.createElement('div');
                    noteItem.className = 'note-item';
                    noteItem.setAttribute('data-id', note.id);
                    noteItem.innerHTML = `
                        <div class="note-title">${title}</div>
                        <div class="note-date">${note.updated_at || note.created_at}</div>
                    `;
                    
                    // Add click event to load note
                    noteItem.addEventListener('click', function() {
                        document.querySelectorAll('.note-item').forEach(item => {
                            item.classList.remove('active');
                        });
                        this.classList.add('active');
                        loadNote(note.id);
                    });
                    
                    notesListElem.appendChild(noteItem);
                });
            }
            
            // Function to load a specific note
            function loadNote(noteId) {
                // Show loading indicator
                if (typeof showNotification === 'function') {
                    showNotification('Loading note...', 'info');
                }
                
                fetch('load_notes.php?note_id=' + noteId)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.note) {
                            // Set note content in editor
                            let content = data.note.content;
                            
                            // Check if content is a JSON string with embedded metadata
                            try {
                                const contentObj = JSON.parse(content);
                                if (contentObj && contentObj.content) {
                                    // This is our JSON with embedded metadata format
                                    content = contentObj.content;
                                }
                            } catch (e) {
                                // Not JSON, use as is
                                console.log('Note content is not in JSON format, using as is');
                            }
                            
                            // Check if editor and splitEditor exist
                            const editorElem = document.getElementById('editor');
                            const splitEditorElem = document.getElementById('splitEditor');
                            
                            if (editorElem) {
                                editorElem.value = content;
                            } else {
                                console.error('Editor element not found');
                            }
                            
                            if (splitEditorElem) {
                                splitEditorElem.value = content;
                            } else {
                                console.error('Split editor element not found');
                            }
                            
                                            // Proceed with standard content update process
                            // Update editor content
                            const editorElem = document.getElementById('editor');
                            const splitEditorElem = document.getElementById('splitEditor');
                            const previewElem = document.getElementById('preview');
                            const splitPreviewElem = document.getElementById('splitPreview');
                            
                            if (editorElem && splitEditorElem) {
                                editorElem.value = content;
                                splitEditorElem.value = content;
                            } else {
                                console.error('Editor elements not found');
                            }
                            
                            // Update preview with server-side parser
                            if (previewElem && splitPreviewElem) {
                                // Show loading indicators
                                previewElem.innerHTML = '<div class="loading">Processing markdown...</div>';
                                splitPreviewElem.innerHTML = '<div class="loading">Processing markdown...</div>';
                                
                                // Use server-side markdown parser for reliable rendering
                                fetch('/parse_markdown.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: new URLSearchParams({
                                        'markdown': content,
                                        'save': 'false',
                                        'note_id': data.note.id
                                    })
                                })
                                .then(response => response.json())
                                .then(parseData => {
                                    if (parseData.success) {
                                        // Update both preview areas with the parsed HTML
                                        previewElem.innerHTML = parseData.html;
                                        splitPreviewElem.innerHTML = parseData.html;
                                        console.log('Successfully rendered markdown with server-side parser');
                                    } else {
                                        console.error('Server parsing failed:', parseData.message);
                                        // Fallback to client-side parser
                                        const markdownParser = new EnhancedMarkdown({
                                            highlight: true,
                                            linkNewWindow: true,
                                            tables: true,
                                            tasklists: true
                                        });
                                        
                                        const html = markdownParser.parse(content);
                                        previewElem.innerHTML = html;
                                        splitPreviewElem.innerHTML = html;
                                    }
                                })
                                .catch(error => {
                                    console.error('Error using server-side markdown parser:', error);
                                    // Fallback to client-side parser
                                    const markdownParser = new EnhancedMarkdown({
                                        highlight: true,
                                        linkNewWindow: true,
                                        tables: true,
                                        tasklists: true
                                    });
                                    
                                    const html = markdownParser.parse(content);
                                    previewElem.innerHTML = html;
                                    splitPreviewElem.innerHTML = html;
                                });
                            } else {
                                console.error('Preview elements not found');
                            }
                            
                            // Update line numbers if function exists
                            if (typeof updateLineNumbers === 'function') {
                                updateLineNumbers();
                            }
                            
                            // Force view refresh
                            setTimeout(() => {
                                const currentView = localStorage.getItem('preferredView') || 'split';
                                switchView(currentView);
                            }, 100);
                            
                            if (typeof updateLineNumbers === 'function') {
                                updateLineNumbers();
                            }
                            
                            // Set current note ID
                            window.currentNoteId = data.note.id;
                            
                            // Create hidden input for note ID if it doesn't exist
                            if (!document.getElementById('currentNoteId')) {
                                const noteIdInput = document.createElement('input');
                                noteIdInput.type = 'hidden';
                                noteIdInput.id = 'currentNoteId';
                                document.body.appendChild(noteIdInput);
                            }
                            
                            const currentNoteIdElem = document.getElementById('currentNoteId');
                            if (currentNoteIdElem) {
                                currentNoteIdElem.value = data.note.id;
                            }
                            
                            // Update URL with note ID without page reload
                            try {
                                const url = new URL(window.location.href);
                                url.searchParams.set('note_id', data.note.id);
                                window.history.pushState({}, '', url);
                            } catch (e) {
                                console.error('Error updating URL:', e);
                            }
                            
                            // Show success notification
                            if (typeof showNotification === 'function') {
                                showNotification('Note loaded successfully', 'success');
                            }
                        } else {
                            if (typeof showNotification === 'function') {
                                showNotification('Error loading note: ' + (data.message || 'Unknown error'), 'error');
                            } else {
                                console.error('Error loading note:', data.message || 'Unknown error');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading note:', error);
                        if (typeof showNotification === 'function') {
                            showNotification('Error connecting to server: ' + error.message, 'error');
                        }
                    });
            }
            
            // Create new note from modal data
            function createNewNote() {
                const title = document.getElementById('noteTitle').value.trim();
                const category = document.getElementById('noteCategory').value.trim();
                const template = document.getElementById('noteTemplate').value;
                
                // Generate content based on template
                let content = '';
                
                if (title) {
                    content = `# ${title}\n\n`;
                } else {
                    content = '# New Note\n\n';
                }
                
                if (category) {
                    content += `Category: ${category}\n\n`;
                }
                
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
                splitEditor.value = content;
                updatePreview();
                updateLineNumbers();
                
                // Reset currentNoteId to create a new note
                currentNoteId = 0;
                
                // Remove active class from all note items
                document.querySelectorAll('.note-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Hide modal
                hideNewNoteModal();
                
                // Focus editor
                editor.focus();
                
                // Save the new note
                saveNote();
            }
            
            // Ensure editor takes full height
            function adjustEditorHeight() {
                const viewContainer = document.querySelector('.view-container.active-view');
                if (viewContainer) {
                    const availableHeight = window.innerHeight - viewContainer.offsetTop;
                    viewContainer.style.height = availableHeight + 'px';
                }
            }
            
            // Call on load and window resize
            adjustEditorHeight();
            window.addEventListener('resize', adjustEditorHeight);
            
            // Load notes when page loads
            loadNotes();
            
            // Markdown toolbar functionality
            const toolbarButtons = document.querySelectorAll('.toolbar-btn');
            toolbarButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const action = this.getAttribute('data-action');
                    const activeEditor = splitViewContainer.classList.contains('active-subview') ? splitEditor : editor;
                    const start = activeEditor.selectionStart;
                    const end = activeEditor.selectionEnd;
                    const selectedText = activeEditor.value.substring(start, end);
                    
                    let insertText = '';
                    switch(action) {
                        case 'bold':
                            insertText = '**' + selectedText + '**';
                            break;
                        case 'italic':
                            insertText = '*' + selectedText + '*';
                            break;
                        case 'heading':
                            insertText = '# ' + selectedText;
                            break;
                        case 'link':
                            insertText = '[' + (selectedText || 'link text') + '](url)';
                            break;
                        case 'image':
                            insertText = '![' + (selectedText || 'alt text') + '](image-url)';
                            break;
                        case 'list':
                            insertText = '* ' + selectedText;
                            break;
                        case 'code':
                            insertText = '`' + selectedText + '`';
                            break;
                        case 'quote':
                            insertText = '> ' + selectedText;
                            break;
                        case 'hr':
                            insertText = '\n---\n';
                            break;
                    }
                    
                    // Insert the text
                    activeEditor.focus();
                    document.execCommand('insertText', false, insertText);
                    
                    // Sync editors and update preview
                    if (activeEditor === editor) {
                        syncEditors();
                    } else {
                        editor.value = splitEditor.value;
                    }
                    updatePreview();
                    updateLineNumbers();
                });
            });
            
            // Enhanced Save functionality
            saveButton.addEventListener('click', function() {
                saveNote();
            });
            
            // Save keyboard shortcut - Ctrl+S or Cmd+S
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault(); // Prevent browser save dialog
                    saveNote();
                }
            });
            
            // Extract metadata from content
            function extractMetadata(content) {
                const metadata = {
                    title: '',
                    category: '',
                    tags: []
                };
                
                // Try to extract title from first heading
                const titleMatch = content.match(/^#\s+(.+)$/m);
                if (titleMatch) {
                    metadata.title = titleMatch[1].trim();
                } else {
                    // Extract from first line if no heading
                    const lines = content.split('\n');
                    if (lines.length > 0 && lines[0].trim()) {
                        metadata.title = lines[0].trim();
                        // Limit title length
                        if (metadata.title.length > 50) {
                            metadata.title = metadata.title.substring(0, 47) + '...';
                        }
                    } else {
                        metadata.title = 'Untitled Note';
                    }
                }
                
                // Look for category and tags in metadata section
                const metadataRegex = /^---\s*\n([\s\S]*?)\n---/m;
                const metadataMatch = content.match(metadataRegex);
                
                if (metadataMatch) {
                    const metadataContent = metadataMatch[1];
                    
                    // Extract category
                    const categoryMatch = metadataContent.match(/category\s*:\s*(.+)$/m);
                    if (categoryMatch) {
                        metadata.category = categoryMatch[1].trim();
                    }
                    
                    // Extract tags
                    const tagsMatch = metadataContent.match(/tags\s*:\s*(.+)$/m);
                    if (tagsMatch) {
                        const tagsString = tagsMatch[1].trim();
                        // Handle comma-separated or array format
                        if (tagsString.startsWith('[') && tagsString.endsWith(']')) {
                            // Array format: [tag1, tag2, tag3]
                            metadata.tags = tagsString.slice(1, -1).split(',').map(tag => tag.trim());
                        } else {
                            // Comma-separated format: tag1, tag2, tag3
                            metadata.tags = tagsString.split(',').map(tag => tag.trim());
                        }
                    }
                }
                
                return metadata;
            }
            
            // Centralized save function
            function saveNote() {
                // Ultra simplified save function
                console.log('SAVE NOTE: Ultra simplified version');
                
                // Show saving indicator
                const originalText = saveButton.textContent;
                saveButton.textContent = 'Saving...';
                saveButton.disabled = true;
                
                // Get editor reference safely
                const editorElem = document.getElementById('editor');
                if (!editorElem) {
                    console.error('Editor element not found');
                    saveButton.textContent = originalText;
                    saveButton.disabled = false;
                    showNotification('Error: Editor not found', 'error');
                    return;
                }
                
                const content = editorElem.value;
                
                // Get current note ID if available
                let noteId = '0';
                const currentNoteIdElem = document.getElementById('currentNoteId');
                if (currentNoteIdElem) {
                    noteId = currentNoteIdElem.value;
                }
                
                // Extract metadata safely
                let metadata = { title: '', category: '', tags: [] };
                try {
                    metadata = extractMetadata(content);
                } catch (e) {
                    console.error('Error extracting metadata:', e);
                    // Continue with empty metadata
                }
                
                // Prepare form data with all metadata
                const formData = new FormData();
                formData.append('content', content);
                formData.append('note_id', noteId);
                formData.append('title', metadata.title || '');
                formData.append('category', metadata.category || '');
                formData.append('tags', (metadata.tags || []).join(','));
                
                // Send to server with enhanced metadata
                fetch('save_note.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    // Reset save button if it still exists
                    const saveButtonElem = document.getElementById('saveButton');
                    if (saveButtonElem) {
                        saveButtonElem.textContent = originalText;
                        saveButtonElem.disabled = false;
                    }
                    
                    if (data.success) {
                        // Update note ID if it was a new note
                        if (data.note_id && (!noteId || noteId === '0')) {
                            // Create hidden input for note ID if it doesn't exist
                            if (!document.getElementById('currentNoteId')) {
                                try {
                                    const noteIdInput = document.createElement('input');
                                    noteIdInput.type = 'hidden';
                                    noteIdInput.id = 'currentNoteId';
                                    document.body.appendChild(noteIdInput);
                                } catch (e) {
                                    console.error('Error creating currentNoteId element:', e);
                                }
                            }
                            
                            // Set the note ID value safely
                            const currentNoteIdElement = document.getElementById('currentNoteId');
                            if (currentNoteIdElement) {
                                currentNoteIdElement.value = data.note_id;
                                
                                // Update the global variable for reference elsewhere
                                window.currentNoteId = data.note_id;
                                
                                // Update URL with note ID without page reload
                                try {
                                    const url = new URL(window.location.href);
                                    url.searchParams.set('note_id', data.note_id);
                                    window.history.pushState({}, '', url);
                                } catch (e) {
                                    console.error('Error updating URL:', e);
                                }
                            }
                        }
                        
                        // Show success message
                        if (typeof showNotification === 'function') {
                            showNotification('Note saved successfully!', 'success');
                        }
                        
                        // Refresh sidebar if we have a loadNotes function
                        if (typeof loadNotes === 'function') {
                            try {
                                loadNotes();
                            } catch (e) {
                                console.error('Error refreshing notes list:', e);
                            }
                        }
                    } else {
                        if (typeof showNotification === 'function') {
                            showNotification('Failed to save note: ' + (data.message || 'Unknown error'), 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Save error:', error);
                    
                    // Reset save button if it still exists
                    const saveButtonElem = document.getElementById('saveButton');
                    if (saveButtonElem) {
                        saveButtonElem.textContent = originalText;
                        saveButtonElem.disabled = false;
                    }
                    
                    if (typeof showNotification === 'function') {
                        showNotification('An error occurred while saving the note: ' + error.message, 'error');
                    }
                });
            }
            
            // Function to check database status
            function checkDatabaseStatus() {
                const dbStatusElem = document.getElementById('dbStatus');
                if (!dbStatusElem) return;
                
                const statusDot = dbStatusElem.querySelector('.status-dot');
                const statusText = dbStatusElem.querySelector('.status-text');
                
                if (!statusDot || !statusText) return;
                
                // Show checking status
                statusText.textContent = 'DB: Checking...';
                statusDot.style.backgroundColor = '#f0ad4e'; // Yellow while checking
                
                fetch('check_db_status.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            statusDot.style.backgroundColor = '#5cb85c'; // Green
                            statusText.textContent = 'DB: ' + data.message;
                        } else {
                            statusDot.style.backgroundColor = '#d9534f'; // Red
                            statusText.textContent = 'DB: Disconnected';
                        }
                    })
                    .catch(error => {
                        console.error('Error checking DB status:', error);
                        statusDot.style.backgroundColor = '#d9534f'; // Red
                        statusText.textContent = 'DB: Error';
                    });
                
                // Check status again after 30 seconds
                setTimeout(checkDatabaseStatus, 30000);
            }
            
            // Define loadNotes function so it can be called from saveNote
            function loadNotes() {
                const userNotesElement = document.getElementById('userNotes');
                if (!userNotesElement) {
                    console.error('Error: userNotes element not found');
                    return;
                }
                userNotesElement.innerHTML = '<div class="loading">Loading notes...</div>';
                console.log('Fetching notes from load_notes.php...');
                fetch('load_notes.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        console.log('Response received from load_notes.php');
                        return response.text().then(text => {
                            console.log('Response text:', text);
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('JSON parse error:', e);
                                throw new Error('Invalid JSON response');
                            }
                        });
                    })
                    .then(data => {
                        // Process the json data
                        console.log('Parsed data:', data);
                        if (data && data.success) {
                            console.log(`Found ${data.notes ? data.notes.length : 0} notes`);  
                            console.log('Note data:', JSON.stringify(data.notes));
                            if (data.debug) {
                                console.log('Debug info:', data.debug);
                            }
                            displayNotes(data.notes || []);
                        } else {
                            // Check again if the element still exists
                            if (document.getElementById('userNotes')) {
                                const msg = data && data.message ? data.message : 'Unknown error';
                                console.error('Error loading notes:', msg);
                                document.getElementById('userNotes').innerHTML = '<div class="error">Error: ' + msg + '</div>';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading notes:', error);
                        // Check again if the element still exists
                        if (document.getElementById('userNotes')) {
                            document.getElementById('userNotes').innerHTML = '<div class="error">Error: ' + error.message + '</div>';
                        }
                    });
            }
            
            // Initial load of notes
            loadNotes();
            
            // Notification function
            function showNotification(message, type = 'info') {
                // Create notification container if it doesn't exist
                let notifContainer = document.getElementById('notification-container');
                if (!notifContainer) {
                    notifContainer = document.createElement('div');
                    notifContainer.id = 'notification-container';
                    notifContainer.style.position = 'fixed';
                    notifContainer.style.bottom = '20px';
                    notifContainer.style.right = '20px';
                    notifContainer.style.zIndex = '1000';
                    document.body.appendChild(notifContainer);
                }
                
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.innerHTML = `
                    <div class="notification-content">
                        <span class="notification-message">${message}</span>
                        <button class="notification-close">&times;</button>
                    </div>
                `;
                
                // Style the notification
                notification.style.padding = '10px 15px';
                notification.style.marginBottom = '10px';
                notification.style.borderRadius = '4px';
                notification.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
                notification.style.backgroundColor = type === 'success' ? '#4CAF50' : 
                                                    type === 'error' ? '#f44336' : 
                                                    type === 'warning' ? '#ff9800' : '#2196F3';
                notification.style.color = '#fff';
                notification.style.transition = 'all 0.3s ease';
                notification.style.maxWidth = '300px';
                
                // Add close button functionality
                const closeBtn = notification.querySelector('.notification-close');
                closeBtn.style.marginLeft = '10px';
                closeBtn.style.background = 'transparent';
                closeBtn.style.border = 'none';
                closeBtn.style.color = 'white';
                closeBtn.style.cursor = 'pointer';
                
                closeBtn.addEventListener('click', function() {
                    notifContainer.removeChild(notification);
                });
                
                // Add to container
                notifContainer.appendChild(notification);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode === notifContainer) {
                        notifContainer.removeChild(notification);
                    }
                }, 5000);
            }
        });
    </script>
</body>
</html>