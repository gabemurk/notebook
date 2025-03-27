<?php
/**
 * Database Status Endpoint
 * Returns the connection status of both PostgreSQL and SQLite databases
 */

// Include the database connection file
require_once __DIR__ . '/db.php';

// Set content type to JSON
header('Content-Type: application/json');

/**
 * Get the status of both database connections
 */
function get_db_status() {
    // Get a fresh database manager instance to ensure we test connections properly
    $dbManager = get_db_manager();
    $dbManager->initializeDualConnections();
    
    $result = [
        'pg_connected' => false,
        'sqlite_connected' => false,
        'status' => 'disconnected',
        'last_sync' => null
    ];
    
    // Check PostgreSQL connection
    try {
        $pgConn = $dbManager->getConnection(DB_TYPE_POSTGRESQL);
        if ($pgConn) {
            // Try a simple query to verify connection
            $pgResult = $dbManager->executeQuery("SELECT 1 as check_value", [], true, DB_TYPE_POSTGRESQL);
            $result['pg_connected'] = !empty($pgResult);
        }
    } catch (Exception $e) {
        error_log("PostgreSQL connection test failed: " . $e->getMessage());
    }
    
    // Check SQLite connection
    try {
        $sqliteConn = $dbManager->getConnection(DB_TYPE_SQLITE);
        if ($sqliteConn) {
            // Try a simple query to verify connection
            $sqliteResult = $dbManager->executeQuery("SELECT 1 as check_value", [], true, DB_TYPE_SQLITE);
            $result['sqlite_connected'] = !empty($sqliteResult);
        }
    } catch (Exception $e) {
        error_log("SQLite connection test failed: " . $e->getMessage());
    }
    
    // Set overall status
    if ($result['pg_connected'] && $result['sqlite_connected']) {
        $result['status'] = 'dual_connected';
    } else if ($result['pg_connected']) {
        $result['status'] = 'pg_only';
    } else if ($result['sqlite_connected']) {
        $result['status'] = 'sqlite_only';
    } else {
        $result['status'] = 'disconnected';
    }
    
    // Get last sync time if available
    try {
        if ($result['sqlite_connected']) {
            $syncResult = $dbManager->executeQuery(
                "SELECT value FROM system_settings WHERE key = 'last_sync_time'", 
                [], 
                true, 
                DB_TYPE_SQLITE
            );
            
            if (!empty($syncResult) && isset($syncResult[0]['value'])) {
                $result['last_sync'] = $syncResult[0]['value'];
            }
        }
    } catch (Exception $e) {
        error_log("Error checking sync status: " . $e->getMessage());
        // This is fine - the table might not exist yet
    }
    
    return $result;
}

// Return the database status as JSON
echo json_encode(get_db_status());
