<?php
// This file is for debugging database connections
header('Content-Type: application/json');

// Include database files
require_once 'database/db.php';

// Start session if needed
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get information about database connections
$response = [
    'success' => true,
    'current_db_type' => get_db_type(),
    'dual_connections' => db_has_dual_connections(),
    'session_active' => isset($_SESSION['user_id']),
    'time' => date('Y-m-d H:i:s')
];

// Try to execute a simple query to check if the connection works
try {
    // Test a simple query
    $test_query = "SELECT 1 as test";
    $stmt = db_execute($test_query);
    
    if ($stmt) {
        $response['query_test'] = 'success';
    } else {
        $response['query_test'] = 'failed';
    }
} catch (Exception $e) {
    $response['query_test'] = 'error';
    $response['error_message'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
