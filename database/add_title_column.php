<?php
// Add title column to notes table in both databases
require_once __DIR__ . '/db.php';

// Start output buffering to capture any errors
ob_start();

// Log the migration start
error_log("Starting migration to add title column to notes table");

try {
    // Get database manager
    $db_manager = get_db_manager();
    
    // Check PostgreSQL schema
    $pg_connection = $db_manager->getSpecificConnection(DB_TYPE_POSTGRESQL);
    if ($pg_connection) {
        // Check if title column exists in PostgreSQL
        $check_query = "SELECT column_name FROM information_schema.columns WHERE table_name = 'notes' AND column_name = 'title'";
        $result = pg_query($pg_connection, $check_query);
        
        if (pg_num_rows($result) == 0) {
            // Column doesn't exist, add it
            error_log("Adding title column to PostgreSQL notes table");
            $alter_query = "ALTER TABLE notes ADD COLUMN title TEXT DEFAULT 'Untitled Note'";
            pg_query($pg_connection, $alter_query);
            echo "Added title column to PostgreSQL notes table<br>";
        } else {
            echo "Title column already exists in PostgreSQL notes table<br>";
        }
    } else {
        echo "Could not connect to PostgreSQL database<br>";
    }
    
    // Check SQLite schema
    $sqlite_connection = $db_manager->getSpecificConnection(DB_TYPE_SQLITE);
    if ($sqlite_connection) {
        // Check if title column exists in SQLite
        $check_query = "PRAGMA table_info(notes)";
        $stmt = $sqlite_connection->query($check_query);
        $has_title = false;
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['name'] == 'title') {
                $has_title = true;
                break;
            }
        }
        
        if (!$has_title) {
            // Column doesn't exist, add it
            error_log("Adding title column to SQLite notes table");
            $alter_query = "ALTER TABLE notes ADD COLUMN title TEXT DEFAULT 'Untitled Note'";
            $sqlite_connection->exec($alter_query);
            echo "Added title column to SQLite notes table<br>";
        } else {
            echo "Title column already exists in SQLite notes table<br>";
        }
    } else {
        echo "Could not connect to SQLite database<br>";
    }
    
    echo "<p>Migration completed successfully!</p>";
    
} catch (Exception $e) {
    $error = ob_get_clean();
    echo "<p>Error during migration: " . $e->getMessage() . "</p>";
    if ($error) {
        echo "<p>Additional output: " . $error . "</p>";
    }
    error_log("Migration error: " . $e->getMessage());
    exit;
}

// Capture any output
$output = ob_get_clean();
echo $output;

error_log("Migration completed");
echo "<p><a href='/'>Return to application</a></p>";
?>
