<?php
require_once __DIR__ . '/db.php';

// Initialize the database manager for dual connections
$db = get_db_manager();
$db->initializeDualConnections();

// Function to run migration SQL file
function run_migration_file($file_path) {
    echo "Running migration: " . basename($file_path) . "\n";
    
    if (!file_exists($file_path)) {
        echo "Migration file not found: $file_path\n";
        return false;
    }
    
    // Read and execute the SQL file
    $sql = file_get_contents($file_path);
    $queries = explode(';', $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                db_execute($query, [], false);
            } catch (Exception $e) {
                echo "Error executing query: " . $e->getMessage() . "\n";
                echo "Query: " . $query . "\n\n";
            }
        }
    }
    
    return true;
}

// Run each migration file in sequence
run_migration_file(__DIR__ . '/migrations/create_admin_tables.sql');
run_migration_file(__DIR__ . '/migrations/add_title_column.sql');

// Add SQLite-specific migrations since SQLite doesn't support all PostgreSQL syntax
try {
    $sqlite_conn = $db->getSpecificConnection(DB_TYPE_SQLITE);
    if ($sqlite_conn && $sqlite_conn instanceof PDO) {
        echo "Running SQLite-specific migrations...\n";
        
        // Check if notes table exists
        $check = $sqlite_conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notes'");
        $tableExists = ($check && $check->fetch());
        
        if (!$tableExists) {
            // Create notes table for SQLite
            $create_table = "CREATE TABLE IF NOT EXISTS notes (
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
            $sqlite_conn->exec($create_table);
            echo "Created notes table in SQLite\n";
        } else {
            // Add columns if they don't exist
            $columns = ['title', 'category', 'tags'];
            $existing_columns = [];
            
            $cols_result = $sqlite_conn->query("PRAGMA table_info(notes)");
            while ($col = $cols_result->fetch(PDO::FETCH_ASSOC)) {
                $existing_columns[] = $col['name'];
            }
            
            foreach ($columns as $column) {
                if (!in_array($column, $existing_columns)) {
                    switch ($column) {
                        case 'title':
                            $sqlite_conn->exec("ALTER TABLE notes ADD COLUMN title VARCHAR(255) DEFAULT 'Untitled Note'");
                            break;
                        case 'category':
                            $sqlite_conn->exec("ALTER TABLE notes ADD COLUMN category VARCHAR(100) DEFAULT ''");
                            break;
                        case 'tags':
                            $sqlite_conn->exec("ALTER TABLE notes ADD COLUMN tags TEXT DEFAULT ''");
                            break;
                    }
                    echo "Added $column column to SQLite notes table\n";
                }
            }
        }
    }
} catch (Exception $e) {
    echo "SQLite migration error: " . $e->getMessage() . "\n";
}

echo "Migrations completed successfully!\n";
