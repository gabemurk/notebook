<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Enable detailed error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');
error_log("==== Save Note Request " . date('Y-m-d H:i:s') . " ====");

// Include database connection that properly initializes dual database system
require_once __DIR__ . '/database/db.php';

session_start();

// Log POST data for debugging
error_log("POST data: " . print_r($_POST, true));

// Ensure we have a database connection
$db = get_db_manager();
$db->initializeDualConnections();

// Set default response
$response = [
    'success' => false,
    'message' => 'An error occurred'
];

// Check if user is logged in - for now, allow guest notes
if (!isset($_SESSION['user_id'])) {
    // Assign a guest user ID for demo purposes
    $_SESSION['user_id'] = 1; // Guest user
    $_SESSION['username'] = 'Guest';
}

// Get the content from POST data
$content = isset($_POST['content']) ? $_POST['content'] : '';
$title = isset($_POST['title']) ? $_POST['title'] : '';

// Extract title from content if not provided
if (empty($title) && !empty($content)) {
    // Try to extract title from first heading or line
    if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
        $title = $matches[1];
    } else {
        // Get first line
        $lines = explode("\n", $content);
        $title = trim($lines[0]);
        
        // Limit title length
        if (strlen($title) > 50) {
            $title = substr($title, 0, 47) . '...';
        }
    }
}

// If still no title, use generic one
if (empty($title)) {
    $title = 'Untitled Note';
}

// Validate content
if (empty($content)) {
    $response['message'] = 'Note content cannot be empty';
    echo json_encode($response);
    exit;
}

// Check if we're updating an existing note or creating a new one
$note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;

// Get additional metadata
$category = isset($_POST['category']) ? $_POST['category'] : '';
$tags = isset($_POST['tags']) ? $_POST['tags'] : '';

try {
    if ($note_id > 0) {
        // Update existing note - use standard timestamp function that works in both databases
        $query = "UPDATE notes SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
        
        // Use the enhanced database system for dual database support
        $result = db_execute($query, [$title, $content, $note_id, $_SESSION['user_id']], false);
        
        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Note updated successfully';
            $response['note_id'] = $note_id;
        } else {
            $response['message'] = 'Failed to update note';
        }
    } else {
        // Use a simple approach that works with both database systems
        try {
            // Check if the notes table exists in SQLite and has all needed columns
            $sqlite_conn = $db->getSpecificConnection(DB_TYPE_SQLITE);
            if ($sqlite_conn && $sqlite_conn instanceof PDO) {
                // First check if table exists
                $check_table = $sqlite_conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notes'");
                $table_exists = ($check_table && $check_table->fetch());
                
                if (!$table_exists) {
                    // Create the notes table if it doesn't exist
                    error_log("Creating notes table in SQLite");
                    $create_sql = "CREATE TABLE notes (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL,
                        title VARCHAR(255) DEFAULT 'Untitled Note',
                        content TEXT,
                        category VARCHAR(100) DEFAULT '',
                        tags TEXT DEFAULT '',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        sync_status INTEGER DEFAULT 0,
                        sync_timestamp TIMESTAMP NULL,
                        local_modified BOOLEAN DEFAULT 0
                    )";
                    $sqlite_conn->exec($create_sql);
                } else {
                    // Check for missing columns and add them if needed
                    $cols = $sqlite_conn->query("PRAGMA table_info(notes)")->fetchAll(PDO::FETCH_ASSOC);
                    $column_names = array_column($cols, 'name');
                    
                    // Add missing columns if needed
                    $needed_columns = [
                        'title' => "VARCHAR(255) DEFAULT 'Untitled Note'",
                        'category' => "VARCHAR(100) DEFAULT ''",
                        'tags' => "TEXT DEFAULT ''"
                    ];
                    
                    foreach ($needed_columns as $col => $type) {
                        if (!in_array($col, $column_names)) {
                            error_log("Adding $col column to notes table");
                            $sqlite_conn->exec("ALTER TABLE notes ADD COLUMN $col $type");
                        }
                    }
                }
            }
            
            // Now perform the actual insert
            // For compatibility with existing PostgreSQL schema, only use the columns that exist
            // Store title/category/tags in content as JSON instead
            
            // Format content to include metadata since PostgreSQL doesn't have those columns yet
            $metadata = [
                'title' => $title,
                'category' => isset($_POST['category']) ? $_POST['category'] : '',
                'tags' => isset($_POST['tags']) ? $_POST['tags'] : ''
            ];
            
            // Combine the metadata with the actual content
            $full_content = json_encode([  
                'metadata' => $metadata,
                'content' => $content
            ]);
            
            error_log("Using content with embedded metadata: " . substr($full_content, 0, 100) . '...');
            
            // Use only columns that exist in both databases
            $query = "INSERT INTO notes (user_id, content, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"; 
            $result = db_execute($query, [$_SESSION['user_id'], $full_content], false);
            
            error_log("Insert result: " . var_export($result, true));
        } catch (Exception $e) {
            error_log("Error saving note: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $result = false;
        }
        
        if ($result) {
            // Try to get the last inserted ID
            $new_note_id = 0;
            
            // Try first with SQLite
            try {
                $sqlite_conn = $db->getSpecificConnection(DB_TYPE_SQLITE);
                if ($sqlite_conn && $sqlite_conn instanceof PDO) {
                    $new_note_id = $sqlite_conn->lastInsertId();
                    error_log("Got SQLite last insert ID: " . $new_note_id);
                }
            } catch (Exception $e) {
                error_log("Error getting SQLite last ID: " . $e->getMessage());
            }
            
            // If we couldn't get it, try a direct query
            if (empty($new_note_id)) {
                $query = "SELECT MAX(id) as max_id FROM notes WHERE user_id = ?";
                $id_result = db_execute($query, [$_SESSION['user_id']], true);
                error_log("ID lookup result: " . print_r($id_result, true));
                
                if (is_array($id_result) && !empty($id_result) && isset($id_result[0]['max_id'])) {
                    $new_note_id = $id_result[0]['max_id'];
                    error_log("Found note ID through MAX query: " . $new_note_id);
                } else {
                    $new_note_id = time(); // Use timestamp as fallback ID
                    error_log("Using timestamp as note ID: " . $new_note_id);
                }
            }
            
            $response['success'] = true;
            $response['message'] = 'Note saved successfully';
            $response['note_id'] = $new_note_id;
        } else {
            $response['message'] = 'Failed to save note';
        }
    }
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    // Log the error
    error_log('Save note error: ' . $e->getMessage());
}

// Final debug log
error_log("Final response: " . print_r($response, true));

// Return JSON response with proper error handling
header('Content-Type: application/json');
try {
    $json_response = json_encode($response);
    if ($json_response === false) {
        // Handle JSON encoding error
        $json_error = json_last_error_msg();
        error_log("JSON encoding error: {$json_error}");
        echo json_encode([
            'success' => false,
            'message' => 'Error encoding response: ' . $json_error
        ]);
    } else {
        echo $json_response;
    }
} catch (Exception $e) {
    error_log("Final response error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
}

error_log("==== End Save Note Request " . date('Y-m-d H:i:s') . " ====");
?>
