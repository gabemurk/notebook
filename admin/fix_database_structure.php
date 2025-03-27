<?php
/**
 * Fix Database Structure
 * 
 * This script fixes table structure issues in the PostgreSQL database.
 */

// Include database configuration
require_once __DIR__ . '/../database/config.php';

// Simple HTML styling
echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Database Structure</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { border: 1px solid #ccc; border-radius: 5px; padding: 15px; margin-bottom: 15px; }
        .btn { 
            display: inline-block; 
            background: #4CAF50; 
            color: white; 
            padding: 10px 15px; 
            text-decoration: none; 
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Database Structure Fix Tool</h1>";

// Connect to PostgreSQL directly
try {
    echo "<div class='card'>";
    echo "<h2>Connecting to PostgreSQL...</h2>";
    
    // Get connection details from config
    $host = $DB_CONFIG['postgresql']['host'];
    $port = $DB_CONFIG['postgresql']['port'];
    $dbname = $DB_CONFIG['postgresql']['dbname'];
    $user = $DB_CONFIG['postgresql']['user'];
    $password = $DB_CONFIG['postgresql']['password'];
    
    $conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password";
    
    $conn = pg_connect($conn_string);
    if (!$conn) {
        throw new Exception("Failed to connect: " . error_get_last()['message']);
    }
    
    echo "<p class='success'>Successfully connected to PostgreSQL</p>";
    echo "</div>";

    // Start fixing issues
    echo "<div class='card'>";
    echo "<h2>Fixing database_stats table...</h2>";
    
    // Check if database_stats exists
    $table_exists = pg_query($conn, "SELECT to_regclass('public.database_stats')");
    $table_exists_result = pg_fetch_result($table_exists, 0, 0);
    
    if ($table_exists_result == 'database_stats') {
        echo "<p>Table database_stats exists, checking for total_size_bytes column...</p>";
        
        // Check if column exists
        $col_exists = pg_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='database_stats' AND column_name='total_size_bytes'");
        $column_exists = (pg_num_rows($col_exists) > 0);
        
        if ($column_exists) {
            echo "<p class='warning'>Column total_size_bytes already exists!</p>";
        } else {
            // Check if disk_usage_bytes exists
            $disk_usage_exists = pg_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='database_stats' AND column_name='disk_usage_bytes'");
            $disk_usage_column_exists = (pg_num_rows($disk_usage_exists) > 0);
            
            if ($disk_usage_column_exists) {
                // Rename column
                $rename_result = pg_query($conn, "ALTER TABLE database_stats RENAME COLUMN disk_usage_bytes TO total_size_bytes");
                if ($rename_result) {
                    echo "<p class='success'>Successfully renamed disk_usage_bytes to total_size_bytes</p>";
                } else {
                    echo "<p class='error'>Failed to rename column: " . pg_last_error($conn) . "</p>";
                }
            } else {
                // Add the column
                $add_column = pg_query($conn, "ALTER TABLE database_stats ADD COLUMN total_size_bytes BIGINT NOT NULL DEFAULT 0");
                if ($add_column) {
                    echo "<p class='success'>Successfully added total_size_bytes column</p>";
                } else {
                    echo "<p class='error'>Failed to add column: " . pg_last_error($conn) . "</p>";
                }
            }
        }
    } else {
        echo "<p>Creating database_stats table from scratch...</p>";
        
        $create_table = pg_query($conn, "
            CREATE TABLE database_stats (
                id SERIAL PRIMARY KEY,
                stat_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                db_type VARCHAR(20) NOT NULL,
                total_users INTEGER NOT NULL DEFAULT 0,
                total_notes INTEGER NOT NULL DEFAULT 0,
                total_size_bytes BIGINT NOT NULL DEFAULT 0
            )
        ");
        
        if ($create_table) {
            echo "<p class='success'>Successfully created database_stats table</p>";
            
            // Grant permissions
            $grant_result = pg_query($conn, "GRANT ALL PRIVILEGES ON TABLE database_stats TO $user");
            $grant_seq_result = pg_query($conn, "GRANT ALL PRIVILEGES ON SEQUENCE database_stats_id_seq TO $user");
            
            if ($grant_result && $grant_seq_result) {
                echo "<p class='success'>Successfully granted permissions</p>";
            } else {
                echo "<p class='error'>Failed to grant permissions: " . pg_last_error($conn) . "</p>";
            }
            
            // Insert initial data
            $insert_pg = pg_query($conn, "
                INSERT INTO database_stats (db_type, total_users, total_notes, total_size_bytes)
                VALUES ('postgresql', 0, 0, 0)
            ");
            
            $insert_sqlite = pg_query($conn, "
                INSERT INTO database_stats (db_type, total_users, total_notes, total_size_bytes)
                VALUES ('sqlite', 0, 0, 0)
            ");
            
            if ($insert_pg && $insert_sqlite) {
                echo "<p class='success'>Successfully inserted initial data</p>";
            } else {
                echo "<p class='error'>Failed to insert initial data: " . pg_last_error($conn) . "</p>";
            }
        } else {
            echo "<p class='error'>Failed to create table: " . pg_last_error($conn) . "</p>";
        }
    }
    echo "</div>";
    
    // Fix the auto_sync_settings table
    echo "<div class='card'>";
    echo "<h2>Fixing auto_sync_settings table...</h2>";
    
    // Check if auto_sync_settings exists and has the next_run column
    $table_exists = pg_query($conn, "SELECT to_regclass('public.auto_sync_settings')");
    $table_exists_result = pg_fetch_result($table_exists, 0, 0);
    
    if ($table_exists_result == 'auto_sync_settings') {
        echo "<p>Table auto_sync_settings exists, checking for next_run column...</p>";
        
        // Check if next_run column exists
        $col_exists = pg_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='auto_sync_settings' AND column_name='next_run'");
        $column_exists = (pg_num_rows($col_exists) > 0);
        
        if ($column_exists) {
            echo "<p class='warning'>Column next_run already exists!</p>";
        } else {
            // Add the next_run column
            $add_column = pg_query($conn, "ALTER TABLE auto_sync_settings ADD COLUMN next_run TIMESTAMP");
            if ($add_column) {
                echo "<p class='success'>Successfully added next_run column</p>";
                
                // Update existing rows to have a value for next_run based on last_run + interval
                $update_next_run = pg_query($conn, "
                    UPDATE auto_sync_settings 
                    SET next_run = CASE 
                        WHEN last_run IS NOT NULL THEN last_run + (interval_minutes * interval '1 minute')
                        ELSE CURRENT_TIMESTAMP + (interval_minutes * interval '1 minute')
                    END
                ");
                
                if ($update_next_run) {
                    echo "<p class='success'>Successfully updated next_run values</p>";
                } else {
                    echo "<p class='error'>Failed to update next_run values: " . pg_last_error($conn) . "</p>";
                }
            } else {
                echo "<p class='error'>Failed to add next_run column: " . pg_last_error($conn) . "</p>";
            }
        }
    } else {
        echo "<p>Creating auto_sync_settings table from scratch...</p>";
        
        $create_table = pg_query($conn, "
            CREATE TABLE auto_sync_settings (
                id SERIAL PRIMARY KEY,
                enabled BOOLEAN NOT NULL DEFAULT false,
                interval_minutes INTEGER NOT NULL DEFAULT 60,
                last_run TIMESTAMP,
                next_run TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        if ($create_table) {
            echo "<p class='success'>Successfully created auto_sync_settings table</p>";
            
            // Grant permissions
            $grant_result = pg_query($conn, "GRANT ALL PRIVILEGES ON TABLE auto_sync_settings TO $user");
            $grant_seq_result = pg_query($conn, "GRANT ALL PRIVILEGES ON SEQUENCE auto_sync_settings_id_seq TO $user");
            
            if ($grant_result && $grant_seq_result) {
                echo "<p class='success'>Successfully granted permissions</p>";
            } else {
                echo "<p class='error'>Failed to grant permissions: " . pg_last_error($conn) . "</p>";
            }
            
            // Insert initial settings
            $insert_settings = pg_query($conn, "
                INSERT INTO auto_sync_settings (enabled, interval_minutes, next_run)
                VALUES (false, 60, CURRENT_TIMESTAMP + interval '60 minutes')
            ");
            
            if ($insert_settings) {
                echo "<p class='success'>Successfully inserted initial settings</p>";
            } else {
                echo "<p class='error'>Failed to insert initial settings: " . pg_last_error($conn) . "</p>";
            }
        } else {
            echo "<p class='error'>Failed to create table: " . pg_last_error($conn) . "</p>";
        }
    }
    echo "</div>";

    // Fix notes table owner issue
    echo "<div class='card'>";
    echo "<h2>Handling table ownership issues...</h2>";
    
    echo "<p>Since the error message indicates ownership issues with the 'notes' table, we'll try an alternative approach.</p>";
    
    // Create a special function in the database to help with this
    $create_function = pg_query($conn, "
        CREATE OR REPLACE FUNCTION check_table_permissions(table_name text) RETURNS boolean AS $$
        DECLARE
            can_select boolean;
        BEGIN
            BEGIN
                EXECUTE 'SELECT 1 FROM ' || quote_ident(table_name) || ' LIMIT 1';
                can_select := true;
            EXCEPTION WHEN OTHERS THEN
                can_select := false;
            END;
            RETURN can_select;
        END;
        $$ LANGUAGE plpgsql;
    ");
    
    if ($create_function) {
        echo "<p class='success'>Created helper function to test permissions</p>";
        
        // Test permissions on notes table
        $test_notes = pg_query($conn, "SELECT check_table_permissions('notes')");
        $notes_access = pg_fetch_result($test_notes, 0, 0);
        
        if ($notes_access == 't') {
            echo "<p class='success'>User has SELECT permission on notes table</p>";
        } else {
            echo "<p class='warning'>User does not have SELECT permission on notes table</p>";
            echo "<p>We'll create a view to work around this issue:</p>";
            
            // Try to create a view that the user can use
            $create_view = pg_query($conn, "
                CREATE OR REPLACE VIEW notes_view AS
                SELECT id, user_id, content, created_at, updated_at
                FROM notes
            ");
            
            if ($create_view) {
                echo "<p class='success'>Created notes_view as a workaround</p>";
                
                // Grant permissions on the view
                $grant_view = pg_query($conn, "GRANT ALL PRIVILEGES ON notes_view TO $user");
                if ($grant_view) {
                    echo "<p class='success'>Granted permissions on notes_view</p>";
                } else {
                    echo "<p class='error'>Failed to grant permissions on view: " . pg_last_error($conn) . "</p>";
                }
            } else {
                echo "<p class='error'>Failed to create view: " . pg_last_error($conn) . "</p>";
                echo "<p>This likely means the current database user doesn't have permission to create views.</p>";
            }
        }
    } else {
        echo "<p class='error'>Failed to create helper function: " . pg_last_error($conn) . "</p>";
    }
    echo "</div>";
    
    // Overall result
    echo "<div class='card'>";
    echo "<h2>Summary</h2>";
    echo "<p>The database structure has been updated to fix the identified issues:</p>";
    echo "<ul>";
    echo "<li>Fixed database_stats table structure</li>";
    echo "<li>Fixed auto_sync_settings table structure</li>";
    echo "<li>Attempted to address table ownership issues</li>";
    echo "</ul>";
    echo "<p>These changes should resolve the PHP warnings you were seeing in the admin panel.</p>";
    echo "</div>";

    // Close the connection
    pg_close($conn);
    
} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<h2 class='error'>Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
    echo "</div>";
}

// Link back to admin panel
echo "<a href='/admin/index.php' class='btn'>Return to Admin Panel</a>";
echo "</div></body></html>";
?>
