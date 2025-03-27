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
    </style>
</head>
<body class="bg-gray-900 text-gray-100 dark:bg-gray-900 dark:text-gray-100 overflow-hidden">
    <div class="flex flex-col h-screen overflow-hidden">
        <!-- Notification Container -->
        <div id="notification-container" class="fixed top-4 right-4 z-50 w-72"></div>
        
        <!-- Header Bar -->
        <header class="bg-gray-800 border-b border-gray-700 p-3 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <h1 class="text-xl font-semibold">Notebook</h1>
                <span class="text-xs px-2 py-1 bg-blue-600 rounded">Simple Mode</span>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Database Status Indicator -->
                <div id="dbStatus" class="flex items-center text-sm bg-gray-700 px-2 py-1 rounded">
                    <span class="status-dot"></span>
                    <span>Checking DB...</span>
                </div>
                
                <!-- Save Status Indicator -->
                <div id="saveStatus" class="flex items-center text-sm bg-gray-700 px-2 py-1 rounded">
                    <span class="mr-1">💾</span>
                    <span>Ready</span>
                </div>
                
                <!-- New Note Button -->
                <button id="newNoteBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                    New Note
                </button>
                
                <!-- Login/Account Section -->
                <div class="relative" id="userSection">
                    <div class="flex items-center space-x-1 cursor-pointer" id="userMenuToggle">
                        <span id="userDisplayName" class="text-sm"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') : 'Guest'; ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                    
                    <!-- User Dropdown Menu -->
                    <div id="userMenu" class="absolute right-0 mt-2 w-48 bg-gray-800 border border-gray-700 rounded shadow-lg py-1 hidden z-10">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="/admin/" class="block px-4 py-2 text-sm hover:bg-gray-700">Admin Panel</a>
                            <a href="/polished.php" class="block px-4 py-2 text-sm hover:bg-gray-700">Polished UI</a>
                            <a href="/tailwind-notebook.php" class="block px-4 py-2 text-sm hover:bg-gray-700">Tailwind UI</a>
                            <a href="/logout.php" id="logoutButton" class="block px-4 py-2 text-sm hover:bg-gray-700 text-red-400">Logout</a>
                        <?php else: ?>
                            <a href="/login.php" id="loginPageButton" class="block px-4 py-2 text-sm hover:bg-gray-700">Login</a>
                            <a href="/register.php" class="block px-4 py-2 text-sm hover:bg-gray-700">Register</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Content -->
        <div class="flex flex-1 overflow-hidden bg-gray-900">
            <!-- Sidebar for notes navigation and search -->
            <div id="sidebar" class="w-64 bg-gray-800 border-r border-gray-700 flex-shrink-0 transform transition-transform duration-300 ease-in-out overflow-hidden lg:translate-x-0 -translate-x-full lg:static fixed h-full z-30">
                <!-- Search Functionality -->
                <div class="p-3 border-b border-gray-700 relative">
                    <div class="relative">
                        <input type="text" id="searchInput" placeholder="Search notes..." class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button id="clearSearch" class="absolute right-2 top-2 text-gray-400 hover:text-gray-200">
                            &times;
                        </button>
                    </div>
                    <div id="searchResults" class="absolute left-0 right-0 bg-gray-800 border border-gray-700 mt-1 rounded shadow-lg z-10 max-h-64 overflow-y-auto hidden mx-3"></div>
                </div>
                
                <!-- Notes List -->
                <div class="flex-1 overflow-hidden flex flex-col">
                    <div class="flex items-center justify-between p-3 border-b border-gray-700">
                        <h2 class="text-sm font-medium">Your Notes</h2>
                        <button id="newNoteBtn2" class="bg-blue-600 hover:bg-blue-700 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm transition-colors">+</button>
                    </div>
                    <div id="userNotes" class="flex-1 overflow-y-auto hide-scrollbar"></div>
                </div>
            </div>
            
            <!-- Main Editor Area -->
            <div class="flex-1 flex flex-col overflow-hidden relative">
                <!-- Mobile Sidebar Toggle -->
                <button id="sidebarToggle" class="lg:hidden absolute top-2 left-2 z-20 bg-gray-700 hover:bg-gray-600 text-white p-2 rounded-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                
                <div id="markdownView" class="flex-1 flex flex-col overflow-hidden bg-gray-900">
                    <!-- View Toggle Tabs -->
                    <div class="bg-gray-800 border-b border-gray-700 flex">
                        <button id="editorToggleBtn" class="px-4 py-2 text-sm font-medium text-blue-500 border-b-2 border-blue-500">Editor</button>
                        <button id="previewToggleBtn" class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-300">Preview</button>
                    </div>
                    
                    <!-- Editor Container -->
                    <div id="editorContainer" class="flex-1 overflow-hidden flex relative">
                        <div id="lineNumbers" class="p-4 text-right pr-2 text-gray-500 font-mono text-xs select-none bg-gray-800 overflow-y-hidden"></div>
                        <textarea id="editor" class="markdown-editor flex-1 p-4 bg-gray-900 text-gray-200 outline-none resize-none" placeholder="# Start typing your note here..."></textarea>
                    </div>
                    
                    <!-- Preview Container -->
                    <div id="previewContainer" class="flex-1 overflow-auto p-4 bg-gray-900 hidden">
                        <div id="preview" class="markdown-content max-w-4xl mx-auto"></div>
                    </div>
                </div>
                
                <!-- Save Button - Fixed at Bottom -->
                <div class="bg-gray-800 border-t border-gray-700 p-3 flex justify-end">
                    <button id="saveButton" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm transition-colors">
                        Save Note
                    </button>
                </div>
            </div>
        </div>
    </div> <!-- End of main container -->
    
    <!-- New Note Dialog Modal -->
    <div id="newNoteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <!-- Modal Header -->
            <div class="flex justify-between items-center border-b border-gray-700 p-4">
                <h3 class="text-lg font-medium">Create New Note</h3>
                <button id="closeModalBtn" class="text-gray-400 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-4 space-y-4">
                <div>
                    <label for="noteTitle" class="block text-sm font-medium text-gray-300 mb-1">Title:</label>
                    <input type="text" id="noteTitle" placeholder="Note Title" class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="noteCategory" class="block text-sm font-medium text-gray-300 mb-1">Category (optional):</label>
                    <input type="text" id="noteCategory" placeholder="e.g., Work, Personal" class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="noteTemplate" class="block text-sm font-medium text-gray-300 mb-1">Template:</label>
                    <select id="noteTemplate" class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="blank">Blank Note</option>
                        <option value="basic">Basic Note</option>
                        <option value="meeting">Meeting Notes</option>
                        <option value="todo">To-Do List</option>
                    </select>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="border-t border-gray-700 p-4 flex justify-end space-x-3">
                <button id="cancelNoteBtn" class="px-4 py-2 bg-gray-600 hover:bg-gray-500 text-white rounded text-sm transition-colors">
                    Cancel
                </button>
                <button id="createNoteBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm transition-colors">
                    Create
                </button>
            </div>
        </div>
    </div>
