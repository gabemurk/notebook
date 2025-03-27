<?php
session_start();
require_once __DIR__ . '/database/enhanced_db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Get the search term
$searchTerm = isset($_POST['searchTerm']) ? trim($_POST['searchTerm']) : '';

if (empty($searchTerm)) {
    echo json_encode([]);
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'];

try {
    // Use database-agnostic function from our dual database system
    $sql = "SELECT id, title, content, created_at, updated_at FROM notes 
            WHERE user_id = ? AND (title LIKE ? OR content LIKE ?)";
    $params = [$userId, "%$searchTerm%", "%$searchTerm%"];
    
    // Use the enhanced db_execute function that handles both PostgreSQL and SQLite
    $result = db_execute($sql, $params);
    
    $notes = [];
    if ($result && is_array($result)) {
        foreach ($result as $row) {
            // Get a preview of content (first 100 characters)
            $contentPreview = strlen($row['content']) > 100 
                ? substr($row['content'], 0, 100) . '...' 
                : $row['content'];
                
            $notes[] = [
                'id' => $row['id'],
                'title' => $row['title'] ?: 'Untitled Note',
                'content' => $contentPreview,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
    }
    
    echo json_encode($notes);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
