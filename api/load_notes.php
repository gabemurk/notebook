<?php
include '../database/index.php'; // Include the database package
session_start(); // Start the session

// Set content type to JSON
header('Content-Type: application/json');

// Default response
$response = [
    'success' => false,
    'message' => 'Failed to load notes',
    'notes' => []
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in';
    echo json_encode($response);
    exit;
}

try {
    // Get the database manager instance
    $db = get_db_manager();
    
    // Get user ID from session
    $user_id = $_SESSION['user_id'];
    
    // Initialize dual connections if not already connected
    if (!$db->hasDualConnections()) {
        $db->initializeDualConnections();
    }
    
    // Check which connections we have available
    $has_dual_connections = $db->hasDualConnections();
    $current_db_type = get_db_type();
    
    // First, try to load notes from the primary database
    $notes = db_execute(
        "SELECT * FROM notes WHERE user_id = :user_id ORDER BY updated_at DESC",
        ['user_id' => $user_id]
    );
    
    // If we have dual connections and notes were found in the primary database,
    // make sure the secondary database is synchronized
    if ($has_dual_connections && !empty($notes)) {
        // Check if we need to synchronize data between databases
        // Get the counts from both databases to see if they're in sync
        $primary_count = count($notes);
        
        // Get count from the secondary database
        $secondary_db_type = ($current_db_type === DB_TYPE_POSTGRESQL) ? DB_TYPE_SQLITE : DB_TYPE_POSTGRESQL;
        $secondary_conn = $db->getConnection($secondary_db_type);
        
        if ($secondary_conn) {
            // Temporarily switch to the secondary database
            $orig_type = $GLOBALS['active_db_type'];
            $GLOBALS['active_db_type'] = $secondary_db_type;
            
            $secondary_count_result = db_execute(
                "SELECT COUNT(*) as count FROM notes WHERE user_id = :user_id",
                ['user_id' => $user_id]
            );
            
            // Switch back to the original database
            $GLOBALS['active_db_type'] = $orig_type;
            
            $secondary_count = $secondary_count_result[0]['count'];
            
            // If counts don't match, synchronize the databases
            if ($primary_count != $secondary_count) {
                if ($current_db_type === DB_TYPE_POSTGRESQL) {
                    db_sync('pg_to_sqlite', ['notes']);
                } else {
                    db_sync('sqlite_to_pg', ['notes']);
                }
            }
        }
    }
    
    if ($notes !== false) {
        $response = [
            'success' => true,
            'message' => 'Notes loaded successfully from ' . $current_db_type,
            'notes' => is_array($notes) ? $notes : [],
            'database_info' => [
                'primary_db' => $current_db_type,
                'dual_connections' => $has_dual_connections
            ]
        ];
    } else {
        $response['message'] = 'Database Error: Failed to load notes';
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