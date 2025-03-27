<?php
/**
 * Database Utility Functions
 * 
 * Helper functions to make working with the enhanced database connection easier
 */
include_once __DIR__ . '/enhanced_db_connect.php';

/**
 * Execute a query and return the results
 * 
 * @param string $query The SQL query to execute
 * @param array $params Parameters to bind to the query
 * @param bool $fetch Whether to fetch results
 * @param bool $debug Whether to print debug information
 * @return mixed Query results or false on failure
 */
function db_query($query, $params = [], $fetch = true, $debug = false) {
    static $db = null;
    
    // Create database manager if it doesn't exist
    if ($db === null) {
        $db = new DatabaseManager();
        
        // Enable debugging mode for detailed error messages
        $db->enableDebug(true);
    }
    
    if ($debug) {
        echo "<pre>Query: $query</pre>";
        echo "<pre>Params: " . print_r($params, true) . "</pre>";
    }
    
    try {
        $result = $db->executeQuery($query, $params, $fetch);
        
        if ($debug) {
            echo "<pre>Result: " . print_r($result, true) . "</pre>";
        }
        
        return $result;
    } catch (Exception $e) {
        if ($debug) {
            echo "<pre>Error: " . $e->getMessage() . "</pre>";
        }
        return false;
    }
}

/**
 * Get the current database type
 * 
 * @return string Current database type
 */
function db_type() {
    static $db = null;
    
    // Create database manager if it doesn't exist
    if ($db === null) {
        $db = new DatabaseManager();
    }
    
    return $db->getCurrentDbType();
}

/**
 * Check if a table exists in the database
 * 
 * @param string $table_name Name of the table to check
 * @return bool True if table exists, false otherwise
 */
function table_exists($table_name) {
    $db_type = db_type();
    
    if ($db_type === DB_TYPE_SQLITE) {
        $result = db_query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name = :table_name",
            ['table_name' => $table_name]
        );
    } else if ($db_type === DB_TYPE_POSTGRESQL) {
        $result = db_query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name = :table_name",
            ['table_name' => $table_name]
        );
    } else if ($db_type === DB_TYPE_MYSQL) {
        $result = db_query(
            "SHOW TABLES LIKE :table_name",
            ['table_name' => $table_name]
        );
    } else {
        return false;
    }
    
    return !empty($result);
}

/**
 * Get information about table columns
 * 
 * @param string $table_name Name of the table
 * @return array Column information or empty array on failure
 */
function table_columns($table_name) {
    $db_type = db_type();
    
    if ($db_type === DB_TYPE_SQLITE) {
        return db_query("PRAGMA table_info({$table_name})");
    } else if ($db_type === DB_TYPE_POSTGRESQL) {
        return db_query(
            "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = :table_name",
            ['table_name' => $table_name]
        );
    } else if ($db_type === DB_TYPE_MYSQL) {
        return db_query("DESCRIBE {$table_name}");
    } else {
        return [];
    }
}

/**
 * Execute a simple database operation with debug output
 * 
 * @param string $operation_name Name of the operation for display
 * @param string $query SQL query to execute
 * @param array $params Parameters to bind to the query
 * @param bool $fetch Whether to fetch results
 * @return mixed Query results or false on failure
 */
function db_operation($operation_name, $query, $params = [], $fetch = true) {
    echo "<h3>{$operation_name}</h3>";
    
    $result = db_query($query, $params, $fetch, true);
    
    if ($result === false) {
        echo "<p style='color: red;'>Operation failed</p>";
    } else {
        echo "<p style='color: green;'>Operation successful</p>";
    }
    
    return $result;
}

/**
 * Debugging function to print out database connection details
 */
function db_debug_connection_info() {
    try {
        $db = get_db_manager();
        
        // Check PostgreSQL connection
        try {
            $pg_conn = $db->getConnection(DB_TYPE_POSTGRESQL);
            echo "<div class='debug-info'>";
            echo "<h3>PostgreSQL Connection Debug</h3>";
            echo "<p>Connection Status: " . ($pg_conn ? "Successful" : "Failed") . "</p>";
            
            if ($pg_conn) {
                // Try a simple query
                $stmt = $pg_conn->query("SELECT COUNT(*) FROM users");
                $user_count = $stmt->fetchColumn();
                echo "<p>Users Table Count: " . $user_count . "</p>";
                
                // List admin users
                $stmt = $pg_conn->query("SELECT id, username, role FROM users WHERE role = 'admin'");
                echo "<h4>Admin Users:</h4>";
                echo "<ul>";
                while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<li>ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}</li>";
                }
                echo "</ul>";
            }
            echo "</div>";
        } catch (Exception $e) {
            echo "<div class='debug-error'>";
            echo "<h3>PostgreSQL Connection Error</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
        
        // Check SQLite connection
        try {
            $sqlite_conn = $db->getConnection(DB_TYPE_SQLITE);
            echo "<div class='debug-info'>";
            echo "<h3>SQLite Connection Debug</h3>";
            echo "<p>Connection Status: " . ($sqlite_conn ? "Successful" : "Failed") . "</p>";
            
            if ($sqlite_conn) {
                // Try a simple query
                $stmt = $sqlite_conn->query("SELECT COUNT(*) FROM users");
                $user_count = $stmt->fetchColumn();
                echo "<p>Users Table Count: " . $user_count . "</p>";
                
                // List admin users
                $stmt = $sqlite_conn->query("SELECT id, username, role FROM users WHERE role = 'admin'");
                echo "<h4>Admin Users:</h4>";
                echo "<ul>";
                while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<li>ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}</li>";
                }
                echo "</ul>";
            }
            echo "</div>";
        } catch (Exception $e) {
            echo "<div class='debug-error'>";
            echo "<h3>SQLite Connection Error</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div class='debug-error'>";
        echo "<h3>General Database Error</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
}

?>
