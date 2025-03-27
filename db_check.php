<?php
/**
 * Database Connection Diagnostic Tool
 * Shows detailed status of PostgreSQL and SQLite connections
 */

// Start output buffering to catch early errors
ob_start();

// Include database files
require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/database/enhanced_db_connect.php';
require_once __DIR__ . '/database/db_util.php';

// Function to display database status
function check_db_status() {
    $results = [
        'postgresql' => [
            'status' => false,
            'message' => 'Not tested',
            'details' => []
        ],
        'sqlite' => [
            'status' => false,
            'message' => 'Not tested',
            'details' => []
        ]
    ];
    
    try {
        // Create database manager instance
        $db = new DatabaseManager(DB_TYPE_AUTO);
        
        // Test PostgreSQL connection
        try {
            $pgConn = $db->getConnection(DB_TYPE_POSTGRESQL);
            if ($pgConn) {
                $results['postgresql']['status'] = true;
                $results['postgresql']['message'] = 'Connected successfully';
                // Get PostgreSQL version
                $version = $db->executeQuery("SELECT version()", [], true, DB_TYPE_POSTGRESQL);
                $results['postgresql']['details']['version'] = $version[0]['version'] ?? 'Unknown';
                // Check tables
                $tables = $db->executeQuery("SELECT table_name FROM information_schema.tables WHERE table_schema='public'", [], true, DB_TYPE_POSTGRESQL);
                $results['postgresql']['details']['tables'] = array_column($tables, 'table_name');
            } else {
                $results['postgresql']['message'] = 'Connection failed';
            }
        } catch (Exception $e) {
            $results['postgresql']['message'] = 'Error: ' . $e->getMessage();
        }
        
        // Test SQLite connection
        try {
            $sqliteConn = $db->getConnection(DB_TYPE_SQLITE);
            if ($sqliteConn) {
                $results['sqlite']['status'] = true;
                $results['sqlite']['message'] = 'Connected successfully';
                // Get SQLite version
                $version = $db->executeQuery("SELECT sqlite_version()", [], true, DB_TYPE_SQLITE);
                $results['sqlite']['details']['version'] = $version[0]['sqlite_version()'] ?? 'Unknown';
                // Check tables
                $tables = $db->executeQuery("SELECT name FROM sqlite_master WHERE type='table'", [], true, DB_TYPE_SQLITE);
                $results['sqlite']['details']['tables'] = array_column($tables, 'name');
            } else {
                $results['sqlite']['message'] = 'Connection failed';
            }
        } catch (Exception $e) {
            $results['sqlite']['message'] = 'Error: ' . $e->getMessage();
        }
        
        // Check current active database
        $results['active_database'] = $GLOBALS['active_db_type'] ?? 'None';
    } catch (Exception $e) {
        $results['error'] = 'General error: ' . $e->getMessage();
    }
    
    return $results;
}

// Get early errors
$early_errors = ob_get_clean();

// Set content type to JSON
header('Content-Type: application/json');

// Run the check and output results
$results = check_db_status();

// Add early errors if any
if ($early_errors) {
    $results['early_errors'] = $early_errors;
}

echo json_encode($results, JSON_PRETTY_PRINT);
