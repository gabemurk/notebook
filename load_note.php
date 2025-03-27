<?php
/**
 * Load Individual Note Script
 * Loads a specific note by ID from the dual database system
 */

ini_set('display_errors', 0); // Don't display PHP errors to browser
error_reporting(E_ALL); // Still log all errors

// Start output buffering to catch any unexpected output
ob_start();

// Set proper content type for JSON response
header('Content-Type: application/json');

// Initialize default response
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'content' => '',
    'title' => ''
];

try {
    // Include the dual database connection
    require_once 'database/db.php';
    
    // Start or resume session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verify user is logged in or set a default user_id for demo/testing
    if (!isset($_SESSION['user_id'])) {
        // For demo purposes, use a default user ID if not logged in
        $_SESSION['user_id'] = 1; // Default demo user
    }
    
    // Get note_id from query parameters
    $noteId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($noteId <= 0) {
        throw new Exception("Invalid note ID");
    }
    
    // Get database manager from the global function
    $dbManager = get_db_manager();
    
    // Query to get the note
    $query = "SELECT id, title, content, updated_at FROM notes WHERE id = ? AND user_id = ?";
    $params = [$noteId, $_SESSION['user_id']];
    
    // Execute the query
    $note = $dbManager->executeQuery($query, $params, true);
    
    if (empty($note)) {
        throw new Exception("Note not found");
    }
    
    // Ensure we have proper title
    if (empty($note['title'])) {
        $note['title'] = 'Untitled Note';
    }
    
    // Determine if we have a single row or an array of rows
    if (isset($note[0])) {
        // We got an indexed array of rows, use the first one
        $noteData = $note[0];
    } else {
        // We got a single row as associative array
        $noteData = $note;
    }
    
    // Update response data
    $response = [
        'success' => true,
        'message' => 'Note loaded successfully',
        'id' => $noteData['id'],
        'title' => $noteData['title'] ?: 'Untitled Note',
        'content' => $noteData['content'],
        'updated_at' => $noteData['updated_at']
    ];
    
} catch (Exception $e) {
    // Log the error
    error_log("Error loading note: " . $e->getMessage());
    
    // Update the error message
    $response['success'] = false;
    $response['message'] = $e->getMessage();
} finally {
    // Clear output buffer to make sure we don't have any unwanted output
    // that would corrupt the JSON
    $output = ob_get_clean();
    
    // If there was unexpected output, log it
    if (!empty($output)) {
        error_log("Unexpected output in load_note.php: " . $output);
    }
    
    // Return the JSON response
    echo json_encode($response, JSON_PRETTY_PRINT);
}
?>
