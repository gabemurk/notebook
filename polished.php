<?php
session_start();

// Include the database connection
require_once __DIR__ . '/database/db.php';

// Include table initialization to ensure all necessary tables exist
require_once __DIR__ . '/database/ensure_tables.php';

// Check if user is logged in or use demo user
if (!isset($_SESSION['user_id'])) {
    // For demo purposes, use a default user ID
    $_SESSION['user_id'] = 1; // Default demo user
}

// Check database status silently
function get_db_status() {
    try {
        $dbManager = get_db_manager();
        
        // Check PostgreSQL connection
        $pgConnected = false;
        try {
            $pgConn = $dbManager->getConnection(DB_TYPE_POSTGRESQL);
            if ($pgConn) {
                // Try a simple query to verify connection
                $result = $dbManager->executeQuery("SELECT 1 as check_value", [], true, DB_TYPE_POSTGRESQL);
                $pgConnected = !empty($result);
            }
        } catch (Exception $e) {
            error_log("PostgreSQL connection test failed: " . $e->getMessage());
        }
        
        // Check SQLite connection
        $sqliteConnected = false;
        try {
            $sqliteConn = $dbManager->getConnection(DB_TYPE_SQLITE);
            if ($sqliteConn) {
                // Try a simple query to verify connection
                $result = $dbManager->executeQuery("SELECT 1 as check_value", [], true, DB_TYPE_SQLITE);
                $sqliteConnected = !empty($result);
            }
        } catch (Exception $e) {
            error_log("SQLite connection test failed: " . $e->getMessage());
        }
        
        return [
            'pg_connected' => $pgConnected,
            'sqlite_connected' => $sqliteConnected,
            'status' => ($pgConnected || $sqliteConnected) ? 'connected' : 'disconnected'
        ];
    } catch (Exception $e) {
        error_log("Error checking DB status: " . $e->getMessage());
        return [
            'pg_connected' => false,
            'sqlite_connected' => false,
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

// Get current status
$dbStatus = get_db_status();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notebook - Polished Edition</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Base Styles */
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-bg: #1f2937;
            --medium-bg: #374151;
            --light-bg: #4b5563;
            --dark-text: #111827;
            --light-text: #f3f4f6;
            --border-color: #6b7280;
            --sidebar-width: 300px;
            --header-height: 60px;
            --border-radius: 6px;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            line-height: 1.6;
            color: var(--light-text);
            background-color: var(--dark-bg);
            overflow-x: hidden;
        }
        
        /* Layout */
        .app-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100vw;
        }
        
        .header {
            background-color: var(--medium-bg);
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 10;
        }
        
        .header-title {
            font-weight: 600;
            font-size: 1.2rem;
            margin-right: auto;
        }
        
        .main-content {
            display: flex;
            flex-grow: 1;
            overflow: hidden;
            position: relative;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--medium-bg);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
            z-index: 5;
        }
        
        .sidebar-collapsed .sidebar {
            transform: translateX(-100%);
        }
        
        .search-section {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .search-input {
            width: 100%;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            background-color: var(--light-bg);
            color: var(--light-text);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.3);
        }
        
        .notes-list {
            flex-grow: 1;
            overflow-y: auto;
            padding: 10px;
        }
        
        .note-item {
            padding: 12px;
            margin-bottom: 8px;
            border-radius: var(--border-radius);
            background-color: var(--light-bg);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .note-item:hover {
            background-color: rgba(75, 85, 99, 0.8);
        }
        
        .note-item.active {
            background-color: var(--primary-color);
        }
        
        .note-title {
            font-weight: 500;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .note-date {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        .sidebar-buttons {
            padding: 15px;
            display: flex;
            border-top: 1px solid var(--border-color);
        }
        
        .block-btn {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: var(--border-radius);
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .block-btn:hover {
            background-color: var(--secondary-color);
        }
        
        /* Editor */
        .editor-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .editor-tabs {
            background-color: var(--medium-bg);
            display: flex;
            padding: 10px 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .editor-tab {
            padding: 10px 15px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            background-color: var(--light-bg);
            margin-right: 5px;
            cursor: pointer;
            color: var(--light-text);
            opacity: 0.7;
            transition: all 0.2s ease;
        }
        
        .editor-tab.active {
            background-color: var(--dark-bg);
            opacity: 1;
        }
        
        .view-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .editor-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 0;
        }
        
        .editor {
            flex-grow: 1;
            padding: 15px;
            background-color: var(--dark-bg);
            color: var(--light-text);
            border: none;
            font-family: 'Menlo', 'Monaco', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            resize: none;
        }
        
        .editor:focus {
            outline: none;
        }
        
        .preview-container {
            flex-grow: 1;
            overflow: auto;
            padding: 20px;
            background-color: var(--dark-bg);
            display: none;
        }
        
        .preview {
            max-width: 900px;
            margin: 0 auto;
            color: var(--light-text);
            line-height: 1.7;
        }
        
        .preview h1 {
            font-size: 2rem;
            color: white;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .preview h2 {
            font-size: 1.5rem;
            color: #e0e0e0;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .preview h3 {
            font-size: 1.2rem;
            margin-top: 1.2rem;
            margin-bottom: 0.8rem;
        }
        
        .preview p {
            margin-bottom: 1rem;
        }
        
        .preview ul, .preview ol {
            margin-bottom: 1rem;
            padding-left: 2rem;
        }
        
        .preview li {
            margin-bottom: 0.5rem;
        }

        /* Status Bar */
        .status-bar {
            height: 30px;
            background-color: var(--medium-bg);
            display: flex;
            align-items: center;
            padding: 0 15px;
            font-size: 0.8rem;
            color: #a0aec0;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            margin-right: 15px;
        }
        
        .status-dot {
            height: 8px;
            width: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .status-dot.online {
            background-color: var(--success-color);
        }
        
        .status-dot.offline {
            background-color: var(--danger-color);
        }
        
        .status-dot.syncing, .status-dot.warning {
            background-color: var(--warning-color);
        }
        
        /* Header Status */
        .status-indicator {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
        }
        
        .admin-link {
            margin-left: 10px;
            text-decoration: none;
            font-size: 1.2rem;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }
        
        .admin-link:hover {
            opacity: 1;
        }
        
        /* Search Styles */
        .search-wrapper {
            position: relative;
            width: 250px;
            margin: 0 20px;
        }
        
        .search-input-container {
            display: flex;
            align-items: center;
            background-color: var(--light-bg);
            border-radius: var(--border-radius);
            padding: 0 10px;
        }
        
        #searchInput {
            background: transparent;
            border: none;
            padding: 8px 5px;
            color: var(--light-text);
            width: 100%;
            font-size: 0.9rem;
        }
        
        #searchInput:focus {
            outline: none;
        }
        
        .clear-search {
            background: none;
            border: none;
            color: #aaa;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0 5px;
        }
        
        .clear-search:hover {
            color: var(--light-text);
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: var(--medium-bg);
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            max-height: 300px;
            overflow-y: auto;
            z-index: 100;
            display: none;
        }
        
        .search-wrapper.active .search-results {
            display: block;
        }
        
        .search-result-item {
            padding: 10px 15px;
            border-bottom: 1px solid var(--light-bg);
            cursor: pointer;
        }
        
        .search-result-item:hover {
            background-color: var(--light-bg);
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .search-result-title {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .search-result-date {
            font-size: 0.8rem;
            color: #aaa;
        }
        
        .search-loading,
        .no-results,
        .search-error {
            padding: 15px;
            text-align: center;
            color: #aaa;
            font-size: 0.9rem;
        }
        
        .search-error {
            color: var(--danger-color);
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-dialog {
            background-color: var(--medium-bg);
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .modal-header {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-title {
            font-weight: 600;
            color: var(--light-text);
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--light-text);
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        
        .modal-close:hover {
            opacity: 1;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            display: flex;
            justify-content: flex-end;
            border-top: 1px solid var(--border-color);
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            background-color: var(--light-bg);
            color: var(--light-text);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        /* Buttons */
        .btn {
            padding: 8px 15px;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-cancel {
            background-color: var(--light-bg);
            color: var(--light-text);
            margin-right: 10px;
        }
        
        .btn-cancel:hover {
            background-color: #606060;
        }
        
        /* Sidebar Toggle */
        .sidebar-toggle {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 6;
            background-color: var(--medium-bg);
            color: var(--light-text);
            border: none;
            border-radius: var(--border-radius);
            width: 40px;
            height: 40px;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            opacity: 0;
            transform: translateX(var(--sidebar-width));
            transition: opacity 0.3s, transform 0.3s;
        }
        
        .sidebar-collapsed .sidebar-toggle {
            opacity: 1;
            transform: translateX(0);
        }
        
        /* Notifications */
        #notification-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .notification {
            margin-bottom: 10px;
            padding: 15px;
            border-radius: var(--border-radius);
            width: 300px;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease-out forwards;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .notification.success {
            background-color: var(--success-color);
        }
        
        .notification.error {
            background-color: var(--danger-color);
        }
        
        .notification.warning {
            background-color: var(--warning-color);
        }
        
        .notification.info {
            background-color: var(--primary-color);
        }
        
        .notification-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        
        .notification-close:hover {
            opacity: 1;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: absolute;
                height: 100%;
                transform: translateX(-100%);
            }
            
            .sidebar-toggle {
                opacity: 1;
                transform: translateX(0);
            }
            
            .sidebar-visible .sidebar {
                transform: translateX(0);
            }
            
            .modal-dialog {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <div class="header">
            <div class="header-title">Notebook - Polished Edition</div>
            
            <!-- Search Bar -->
            <div class="search-wrapper" id="searchContainer">
                <div class="search-input-container">
                    <input type="text" id="searchInput" placeholder="Search notes..." autocomplete="off">
                    <button id="clearSearch" class="clear-search" title="Clear search">&times;</button>
                </div>
                <div class="search-results" id="searchResults"></div>
            </div>
            
            <div class="status-indicator" id="dbStatus">
                <?php if ($dbStatus['pg_connected'] && $dbStatus['sqlite_connected']): ?>
                    <span class="status-dot online"></span>
                    <span class="status-text">Both Databases Connected</span>
                <?php elseif ($dbStatus['pg_connected']): ?>
                    <span class="status-dot online"></span>
                    <span class="status-text">PostgreSQL Only</span>
                <?php elseif ($dbStatus['sqlite_connected']): ?>
                    <span class="status-dot warning"></span>
                    <span class="status-text">SQLite Only (Fallback)</span>
                <?php else: ?>
                    <span class="status-dot offline"></span>
                    <span class="status-text">No Database Connection</span>
                <?php endif; ?>
                <a href="/admin/index.php" class="admin-link" title="Admin Dashboard">⚙️</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Sidebar -->
            <div class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <h3>Your Notes</h3>
                </div>
                <div class="notes-list" id="userNotes">
                    <div class="loading">Loading notes...</div>
                </div>
                <div class="sidebar-buttons">
                    <button id="newNoteBtn2" class="block-btn">+ New Note</button>
                </div>
            </div>
            
            <!-- Editor Area -->
            <div class="editor-wrapper">
                <div class="editor-tabs">
                    <div class="editor-tab active" id="editorTab">Editor</div>
                    <div class="editor-tab" id="previewTab">Preview</div>
                </div>
                
                <div id="markdownView" class="view-container">
                    <div id="editorContainer" class="editor-container">
                        <textarea id="editor" class="editor" placeholder="Start writing here..."></textarea>
                    </div>
                    
                    <div id="previewContainer" class="preview-container">
                        <div id="preview" class="preview"></div>
                    </div>
                </div>
                
                <!-- Status Bar -->
                <div class="status-bar">
                    <div class="status-item" id="saveStatus">
                        <span class="status-dot online"></span>
                        <span>Ready to save</span>
                    </div>
                    <div class="status-item">
                        <button id="saveButton" class="btn btn-primary">Save</button>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar Toggle Button -->
            <button class="sidebar-toggle" id="sidebarToggle">☰</button>
        </div>
    </div>
    
    <!-- New Note Modal -->
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
                <button class="btn btn-cancel" id="cancelNoteBtn">Cancel</button>
                <button class="btn btn-primary" id="createNoteBtn">Create Note</button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript for the app -->
    <script src="/js/polished-app.js"></script>
    <script src="/js/polished-search.js"></script>
</body>
</html>
