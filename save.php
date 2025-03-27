<?php
/**
 * Save Note Script
 * Handles saving notes to both PostgreSQL and SQLite databases
 */

// Start output buffering to catch any unexpected output
ob_start();

// Set proper content type for JSON response
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/database/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not using authentication, assign a default user ID
    $userId = 1; // Default user ID
} else {
    $userId = $_SESSION['user_id'];
}

// Get data from POST request
$content = isset($_POST['content']) ? $_POST['content'] : '';
$title = isset($_POST['title']) ? $_POST['title'] : 'Untitled Note';
$noteId = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;

// Validate input
if (empty($content)) {
    echo json_encode([
        'success' => false,
        'message' => 'Note content cannot be empty'
    ]);
    exit;
}

try {
    // Get database manager from the global function
    $dbManager = get_db_manager();
    
    // Get current timestamp
    $now = date('Y-m-d H:i:s');
    
    // Handle create or update
    if ($noteId > 0) {
        // Update existing note
        $query = "UPDATE notes SET title = ?, content = ?, updated_at = ? WHERE id = ? AND user_id = ?";
        $params = [$title, $content, $now, $noteId, $userId];
        
        // Execute with sync to both databases
        $result = db_execute_sync($query, $params);
        
        if (!$result) {
            throw new Exception("Failed to update note");
        }
    } else {
        // Create new note
        $query = "INSERT INTO notes (user_id, title, content, created_at, updated_at) VALUES (?, ?, ?, ?, ?)";
        $params = [$userId, $title, $content, $now, $now];
        
        // Execute with sync to both databases
        $insertId = db_execute_sync($query, $params, true);
        
        if (!$insertId) {
            throw new Exception("Failed to create new note");
        }
        
        $noteId = $insertId;
    }
    
    // Return success with note ID
    echo json_encode([
        'success' => true,
        'note_id' => $noteId,
        'message' => 'Note saved successfully'
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error saving note: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// End output buffering and clean it
$output = ob_get_clean();

// If there was unexpected output, log it
if (!empty($output)) {
    error_log("Unexpected output in save.php: " . $output);
}
?>
