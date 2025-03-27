<?php
session_start(); // Start the session
include '../database/index.php'; // Include the database package

// Set content type to JSON
header('Content-Type: application/json');

// Default response
$response = [
    'success' => false,
    'message' => 'An error occurred'
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in';
    echo json_encode($response);
    exit;
}

// Check if content parameter exists
if (!isset($_POST['content'])) {
    $response['message'] = 'No content provided';
    echo json_encode($response);
    exit;
}

try {
    // Get the database manager instance
    $db = get_db_manager();
    
    // Get content from AJAX request
    $content = $_POST['content'];
    
    // Get user ID from session
    $user_id = $_SESSION['user_id'];
    
    // Initialize dual connections to both PostgreSQL and SQLite
    if (!$db->hasDualConnections()) {
        $db->initializeDualConnections();
    }
    
    // Check if we have dual connections available
    $has_dual_connections = $db->hasDualConnections();
    
    // Check if the user already has a note (using the primary database)
    $check_result = db_execute(
        "SELECT id FROM notes WHERE user_id = :user_id LIMIT 1",
        ['user_id' => $user_id]
    );
    
    // Create a database-neutral timestamp function
    $timestamp_function = get_db_type() === DB_TYPE_POSTGRESQL ? "NOW()" : "CURRENT_TIMESTAMP";
    
    if (!empty($check_result)) {
        // User has an existing note, update it in both databases simultaneously
        $note_id = $check_result[0]['id'];
        
        // The query will be executed on both PostgreSQL and SQLite simultaneously
        $result = db_execute_sync(
            "UPDATE notes SET content = :content, updated_at = $timestamp_function WHERE id = :id",
            ['content' => $content, 'id' => $note_id],
            false // Don't need to fetch results, just success/failure
        );
    } else {
        // User doesn't have a note, create a new one in both databases simultaneously
        $result = db_execute_sync(
            "INSERT INTO notes (content, user_id) VALUES (:content, :user_id)",
            ['content' => $content, 'user_id' => $user_id],
            false // Don't need to fetch results, just success/failure
        );
        
        // If one database succeeded but the other failed, synchronize them
        if ($result && $has_dual_connections) {
            // Sync data between databases to ensure consistency
            db_sync('pg_to_sqlite', ['notes']);
        }
    }
    
    if ($result !== false) {
        // Determine which databases the note was saved to
        $active_db = get_db_type();
        $db_status = "primary database ($active_db)";
        
        if ($has_dual_connections) {
            $db_status = "both PostgreSQL and SQLite databases";
        }
        
        $response = [
            'success' => true,
            'message' => "Note saved successfully to $db_status",
            'timestamp' => date('Y-m-d H:i:s'),
            'database_info' => [
                'primary_db' => $active_db,
                'dual_connections' => $has_dual_connections
            ]
        ];
    } else {
        $response['message'] = 'Database Error: Failed to save note';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database Exception: ' . $e->getMessage();
    $response['error_details'] = [
        'exception_type' => 'PDOException',
        'code' => $e->getCode(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ];
} catch (Exception $e) {
    $response['message'] = 'Exception: ' . $e->getMessage();
    $response['error_details'] = [
        'exception_type' => get_class($e),
        'code' => $e->getCode(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ];
}

// Return JSON response
echo json_encode($response);
?>