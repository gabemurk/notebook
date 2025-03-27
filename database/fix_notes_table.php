<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once __DIR__ . '/db.php';

// Ensure we have a database connection
$db = get_db_manager();
$db->initializeDualConnections();

echo "Starting database schema fix...\n";

// Fix PostgreSQL schema
$pg_conn = $db->getSpecificConnection(DB_TYPE_POSTGRESQL);
if ($pg_conn) {
    echo "Checking PostgreSQL schema...\n";
    
    // Simple approach to add columns safely
    $columns_to_add = [
        'title' => "VARCHAR(255) DEFAULT 'Untitled Note'",
        'category' => "VARCHAR(100) DEFAULT ''",
        'tags' => "TEXT DEFAULT ''"
    ];
    
    foreach ($columns_to_add as $column => $type) {
        // Check if column exists
        $check_query = "SELECT column_name FROM information_schema.columns 
                       WHERE table_name = 'notes' AND column_name = '$column'";
        $result = pg_query($pg_conn, $check_query);
        
        if ($result && pg_num_rows($result) == 0) {
            echo "Adding column $column to PostgreSQL notes table...\n";
            $alter_query = "ALTER TABLE notes ADD COLUMN $column $type";
            $alter_result = pg_query($pg_conn, $alter_query);
            
            if ($alter_result) {
                echo "Successfully added $column to PostgreSQL\n";
            } else {
                echo "Failed to add $column to PostgreSQL: " . pg_last_error($pg_conn) . "\n";
            }
        } else {
            echo "Column $column already exists in PostgreSQL\n";
        }
    }
}

// Fix SQLite schema
$sqlite_conn = $db->getSpecificConnection(DB_TYPE_SQLITE);
if ($sqlite_conn && $sqlite_conn instanceof PDO) {
    echo "Checking SQLite schema...\n";
    
    // Check if notes table exists
    $check_query = "SELECT name FROM sqlite_master WHERE type='table' AND name='notes'";
    $result = $sqlite_conn->query($check_query);
    $table_exists = ($result && $result->fetch());
    
    if (!$table_exists) {
        echo "Creating notes table in SQLite...\n";
        $create_query = "CREATE TABLE notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title VARCHAR(255) DEFAULT 'Untitled Note',
            content TEXT,
            category VARCHAR(100) DEFAULT '',
            tags TEXT DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sync_status INTEGER DEFAULT 0,
            sync_timestamp TIMESTAMP,
            local_modified BOOLEAN DEFAULT 0
        )";
        
        if ($sqlite_conn->exec($create_query) !== false) {
            echo "Successfully created notes table in SQLite\n";
        } else {
            echo "Failed to create notes table in SQLite: " . implode(", ", $sqlite_conn->errorInfo()) . "\n";
        }
    } else {
        // Get existing columns
        $columns_query = "PRAGMA table_info(notes)";
        $columns_result = $sqlite_conn->query($columns_query);
        $existing_columns = [];
        
        while ($col = $columns_result->fetch(PDO::FETCH_ASSOC)) {
            $existing_columns[] = $col['name'];
        }
        
        // Add missing columns
        $columns_to_add = [
            'title' => "VARCHAR(255) DEFAULT 'Untitled Note'",
            'category' => "VARCHAR(100) DEFAULT ''",
            'tags' => "TEXT DEFAULT ''"
        ];
        
        foreach ($columns_to_add as $column => $type) {
            if (!in_array($column, $existing_columns)) {
                echo "Adding column $column to SQLite notes table...\n";
                $alter_query = "ALTER TABLE notes ADD COLUMN $column $type";
                
                if ($sqlite_conn->exec($alter_query) !== false) {
                    echo "Successfully added $column to SQLite\n";
                } else {
                    echo "Failed to add $column to SQLite: " . implode(", ", $sqlite_conn->errorInfo()) . "\n";
                }
            } else {
                echo "Column $column already exists in SQLite\n";
            }
        }
    }
}

echo "Database schema fix completed\n";
?>
