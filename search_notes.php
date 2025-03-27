<?php
/**
 * Search Notes Script
 * Searches notes by title and content using the dual database system
 */

ini_set('display_errors', 0); // Don't display PHP errors to browser
error_reporting(E_ALL); // Still log all errors

// Set proper content type for JSON response
header('Content-Type: application/json');

// Initialize default response
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'notes' => []
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
    
    // Get search query
    $searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($searchQuery)) {
        throw new Exception("Search query is empty");
    }
    
    // Create database connection using the enhanced connector
    $dbManager = get_db_manager();
    
    // Search pattern with wildcards
    $searchPattern = '%' . $searchQuery . '%';
    
    // Query to search for notes by title or content
    $query = "SELECT id, title, content, updated_at FROM notes WHERE user_id = ? AND (title LIKE ? OR content LIKE ?) ORDER BY updated_at DESC";
    $params = [$_SESSION['user_id'], $searchPattern, $searchPattern];
    
    // Execute the query
    $notes = $dbManager->executeQuery($query, $params, true);
    
    // Format the notes for the response
    $formattedNotes = [];
    foreach ($notes as $note) {
        $formattedNotes[] = [
            'id' => $note['id'],
            'title' => $note['title'],
            'updated_at' => date('Y-m-d H:i', strtotime($note['updated_at']))
        ];
    }
    
    // Update the response
    $response['success'] = true;
    $response['message'] = 'Search completed successfully';
    $response['notes'] = $formattedNotes;
    
} catch (Exception $e) {
    // Log the error
    error_log("Error searching notes: " . $e->getMessage());
    
    // Update the error message
    $response['message'] = $e->getMessage();
}

// Return the JSON response
echo json_encode($response);
?>
