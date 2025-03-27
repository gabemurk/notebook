<?php
session_start();
require_once __DIR__ . '/database/enhanced_db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'postgres_status' => false,
    'sqlite_status' => false,
    'message' => ''
];

try {
    // Get database manager instance
    $dbManager = new DatabaseManager();
    
    // Check PostgreSQL connection
    $pgStatus = $dbManager->isPgConnected();
    $response['postgres_status'] = $pgStatus;
    
    // Check SQLite connection
    $sqliteStatus = $dbManager->isSqliteConnected();
    $response['sqlite_status'] = $sqliteStatus;
    
    // Set overall status based on database configuration
    if ($pgStatus || $sqliteStatus) {
        $response['success'] = true;
        $response['message'] = 'Connected to database';
        
        if ($pgStatus && $sqliteStatus) {
            $response['message'] = 'Connected to both databases';
        } elseif ($pgStatus) {
            $response['message'] = 'Connected to PostgreSQL only';
        } elseif ($sqliteStatus) {
            $response['message'] = 'Connected to SQLite only';
        }
    } else {
        $response['message'] = 'No database connections available';
    }
} catch (Exception $e) {
    $response['message'] = 'Error checking database status: ' . $e->getMessage();
}

// Return JSON response
echo json_encode($response);
?>
