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
    <title>Notebook - Tailwind Edition</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <!-- Tailwind CSS -->
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
        /* Additional custom styles that complement Tailwind */
        .editor {
            min-height: calc(100vh - 12rem);
            resize: none;
            outline: none;
            padding: 1rem;
            width: 100%;
        }
        
        .preview {
            min-height: calc(100vh - 12rem);
            padding: 1rem;
            overflow-y: auto;
        }
        
        .preview h1 { font-size: 2em; font-weight: bold; margin-bottom: 0.5em; }
        .preview h2 { font-size: 1.5em; font-weight: bold; margin-bottom: 0.5em; }
        .preview h3 { font-size: 1.25em; font-weight: bold; margin-bottom: 0.5em; }
        .preview p { margin-bottom: 1em; }
        .preview ul, .preview ol { margin-left: 2em; margin-bottom: 1em; }
        .preview code { background-color: #f0f0f0; padding: 0.2em 0.4em; border-radius: 0.2em; }
        .preview pre { background-color: #f0f0f0; padding: 1em; margin-bottom: 1em; border-radius: 0.4em; overflow-x: auto; }
        .preview blockquote { border-left: 4px solid #ddd; padding-left: 1em; margin-left: 0; margin-bottom: 1em; }
        .preview img { max-width: 100%; }
        .preview table { border-collapse: collapse; width: 100%; margin-bottom: 1em; }
        .preview th, .preview td { border: 1px solid #ddd; padding: 0.5em; }
        .preview th { background-color: #f0f0f0; }
        
        /* Status Dots */
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-dot.online { background-color: #10b981; }
        .status-dot.warning { background-color: #f59e0b; }
        .status-dot.offline { background-color: #ef4444; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200">
    <div class="flex flex-col h-screen">
        <!-- Header -->
        <header class="bg-white dark:bg-gray-800 shadow-md px-4 py-3 flex items-center justify-between">
            <div class="flex items-center">
                <h1 class="text-xl font-bold text-primary-700 dark:text-primary-300">Notebook - Tailwind Edition</h1>
            </div>
            
            <!-- Search Bar -->
            <div class="relative flex-1 max-w-xl mx-4" id="searchContainer">
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="Search notes..." 
                           class="w-full px-4 py-2 pr-10 rounded-lg border dark:border-gray-700 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <button id="clearSearch" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <div id="searchResults" class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 rounded-lg shadow-lg border dark:border-gray-700 overflow-hidden hidden"></div>
            </div>
            
            <!-- Database Status -->
            <div class="flex items-center px-3 py-1 rounded bg-gray-100 dark:bg-gray-700" id="dbStatus">
                <?php if ($dbStatus['pg_connected'] && $dbStatus['sqlite_connected']): ?>
                    <span class="status-dot online"></span>
                    <span class="text-sm">Both DBs Connected</span>
                <?php elseif ($dbStatus['pg_connected']): ?>
                    <span class="status-dot online"></span>
                    <span class="text-sm">PostgreSQL Only</span>
                <?php elseif ($dbStatus['sqlite_connected']): ?>
                    <span class="status-dot warning"></span>
                    <span class="text-sm">SQLite Only</span>
                <?php else: ?>
                    <span class="status-dot offline"></span>
                    <span class="text-sm">No DB Connection</span>
                <?php endif; ?>
                <a href="/admin/index.php" class="ml-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" title="Admin Dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                </a>
            </div>
        </header>
        
        <!-- Main Content -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar -->
            <aside class="w-64 bg-white dark:bg-gray-800 shadow-md flex flex-col border-r dark:border-gray-700 transition-all duration-300 transform lg:translate-x-0"
                   id="sidebar">
                <div class="p-4 border-b dark:border-gray-700">
                    <h2 class="text-lg font-semibold">Your Notes</h2>
                </div>
                <div class="flex-1 overflow-y-auto p-4" id="userNotes">
                    <div class="flex items-center justify-center h-full text-gray-500">
                        <span>Loading notes...</span>
                    </div>
                </div>
                <div class="p-4 border-t dark:border-gray-700">
                    <button id="newNoteBtn2" class="w-full py-2 px-4 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                        + New Note
                    </button>
                </div>
            </aside>
            
            <!-- Mobile Sidebar Toggle -->
            <button id="sidebarToggle" class="fixed z-40 bottom-4 left-4 p-2 rounded-full bg-primary-600 text-white shadow-lg lg:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            
            <!-- Editor Area -->
            <main class="flex-1 flex flex-col overflow-hidden">
                <!-- Tabs -->
                <div class="flex bg-gray-200 dark:bg-gray-700 px-4 pt-2">
                    <button id="editorTab" class="py-2 px-4 font-medium rounded-t-lg mr-1 bg-white dark:bg-gray-800">
                        Editor
                    </button>
                    <button id="previewTab" class="py-2 px-4 font-medium rounded-t-lg bg-gray-100 dark:bg-gray-600">
                        Preview
                    </button>
                </div>
                
                <!-- Editor/Preview Container -->
                <div class="flex-1 overflow-hidden bg-white dark:bg-gray-800" id="markdownView">
                    <div id="editorContainer" class="h-full bg-white dark:bg-gray-800">
                        <textarea id="editor" class="editor border-0 dark:bg-gray-800 dark:text-white" placeholder="Start writing here..."></textarea>
                    </div>
                    
                    <div id="previewContainer" class="hidden h-full bg-white dark:bg-gray-800">
                        <div id="preview" class="preview dark:text-white"></div>
                    </div>
                </div>
                
                <!-- Status Bar -->
                <div class="flex justify-between items-center py-2 px-4 bg-gray-100 dark:bg-gray-700 border-t dark:border-gray-600">
                    <div class="flex items-center" id="saveStatus">
                        <span class="status-dot online"></span>
                        <span class="text-sm">Ready to save</span>
                    </div>
                    <button id="saveButton" class="py-1 px-4 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded transition-colors">
                        Save
                    </button>
                </div>
            </main>
        </div>
    </div>
    
    <!-- New Note Modal -->
    <div id="newNoteModal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="flex justify-between items-center border-b dark:border-gray-700 p-4">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Create New Note</h3>
                <button id="closeModalBtn" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label for="noteTitle" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                    <input type="text" id="noteTitle" class="w-full px-3 py-2 border dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div class="mb-4">
                    <label for="noteCategory" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                    <input type="text" id="noteCategory" class="w-full px-3 py-2 border dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div class="mb-4">
                    <label for="noteTemplate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template</label>
                    <select id="noteTemplate" class="w-full px-3 py-2 border dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="blank">Blank Note</option>
                        <option value="basic">Basic Structure</option>
                        <option value="meeting">Meeting Notes</option>
                        <option value="todo">To-Do List</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end p-4 border-t dark:border-gray-700 space-x-3">
                <button id="cancelNoteBtn" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                    Cancel
                </button>
                <button id="createNoteBtn" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                    Create Note
                </button>
            </div>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notificationContainer" class="fixed bottom-4 right-4 z-50"></div>
    
    <!-- JavaScript Dependencies -->
    <script src="/js/tailwind-app.js"></script>
    <script src="/js/tailwind-search.js"></script>
</body>
</html>
