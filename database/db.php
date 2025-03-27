<?php
/**
 * Database Interface
 * 
 * Main entry point for the application to interact with the database
 * Provides backward compatibility with the old db_connect.php
 * Supports dual database connections with PostgreSQL as primary and SQLite as fallback
 */

// Include the enhanced database connector and utilities
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/enhanced_db_connect.php';
require_once __DIR__ . '/db_util.php';

/**
 * Get a database connection (legacy function for backward compatibility)
 * 
 * @return mixed Database connection
 */
function db_connect() {
    static $db = null;
    
    if ($db === null) {
        // Use PostgreSQL by default, but fall back to others if not available
        $db = new DatabaseManager(DB_TYPE_POSTGRESQL);
        // Initialize both PostgreSQL and SQLite connections
        $db->initializeDualConnections();
    }
    
    return $db->getConnection();
}

/**
 * Get the current database type (legacy function for backward compatibility)
 * 
 * @return string Database type
 */
function get_db_type() {
    static $db = null;
    
    if ($db === null) {
        // Use PostgreSQL by default, but fall back to others if not available
        $db = new DatabaseManager(DB_TYPE_POSTGRESQL);
    }
    
    return $db->getCurrentDbType();
}

/**
 * Get a DatabaseManager instance
 * 
 * @return DatabaseManager
 */
function get_db_manager() {
    static $db = null;
    
    if ($db === null) {
        // Use PostgreSQL by default, but fall back to others if not available
        $db = new DatabaseManager(DB_TYPE_POSTGRESQL);
        // Initialize both PostgreSQL and SQLite connections
        $db->initializeDualConnections();
    }
    
    return $db;
}

/**
 * Execute a query using the database manager
 * 
 * @param string $query SQL query
 * @param array $params Query parameters
 * @param bool $fetch Whether to fetch results
 * @return mixed Query results or success indicator
 */
function db_execute($query, $params = [], $fetch = true) {
    $db = get_db_manager();
    return $db->executeQuery($query, $params, $fetch);
}

/**
 * For backward compatibility - alias of db_connect()
 * 
 * @return mixed Database connection
 */
function getDbConnection() {
    return db_connect();
}

/**
 * Get a connection to a specific database type
 * 
 * @param string $db_type Database type to connect to
 * @return mixed Database connection or false on failure
 */
function db_connect_to($db_type) {
    $db = get_db_manager();
    return $db->getConnection($db_type);
}

/**
 * Check if both PostgreSQL and SQLite connections are available
 * 
 * @return bool True if dual connections are available
 */
function db_has_dual_connections() {
    $db = get_db_manager();
    return $db->hasDualConnections();
}

/**
 * Execute a query on both PostgreSQL and SQLite databases simultaneously
 * 
 * @param string $query SQL query to execute
 * @param array $params Parameters for the query
 * @param bool $fetchMode Whether to fetch results
 * @return array|bool Results from primary database or false on failure
 */
function db_execute_sync($query, $params = [], $fetchMode = true) {
    $db = get_db_manager();
    return $db->executeSyncQuery($query, $params, $fetchMode);
}

/**
 * Synchronize data between PostgreSQL and SQLite databases
 * 
 * @param string $direction 'pg_to_sqlite' or 'sqlite_to_pg'
 * @param array $tables Tables to synchronize (empty for all)
 * @return bool Success status
 */
function db_sync($direction = 'pg_to_sqlite', $tables = []) {
    try {
        // Get a fresh database manager to ensure connections are tested
        $db = new DatabaseManager(DB_TYPE_AUTO);
        $db->initializeDualConnections();
        
        // Verify both connections are working
        $pg_conn = $db->getSpecificConnection(DB_TYPE_POSTGRESQL);
        $sqlite_conn = $db->getSpecificConnection(DB_TYPE_SQLITE);
        
        if (!$pg_conn) {
            error_log("Sync failed: PostgreSQL not connected");
            return false;
        }
        
        if (!$sqlite_conn) {
            error_log("Sync failed: SQLite not connected");
            return false;
        }
        
        // Perform the sync operation with the validated connections
        if ($direction === 'pg_to_sqlite') {
            return $db->syncDatabases(DB_TYPE_POSTGRESQL, DB_TYPE_SQLITE, $tables);
        } else if ($direction === 'sqlite_to_pg') {
            return $db->syncDatabases(DB_TYPE_SQLITE, DB_TYPE_POSTGRESQL, $tables);
        } else {
            error_log("Sync failed: Invalid direction '$direction'");
            return false;
        }
    } catch (Exception $e) {
        error_log("Sync exception: " . $e->getMessage());
        return false;
    }
}
?>
