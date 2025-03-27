<?php
/**
 * Create Database Stats Table
 * 
 * This script creates the missing database_stats table needed for the admin panel.
 */

// Include database configuration
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/enhanced_db_connect.php';

echo "<h1>Creating Database Stats Table</h1>";

// Connect to PostgreSQL directly
try {
    echo "<h2>Connecting to PostgreSQL...</h2>";
    $conn_string = "host={$DB_CONFIG['postgresql']['host']} port={$DB_CONFIG['postgresql']['port']} dbname={$DB_CONFIG['postgresql']['dbname']} user={$DB_CONFIG['postgresql']['user']} password={$DB_CONFIG['postgresql']['password']}";
    
    $conn = pg_connect($conn_string);
    if (!$conn) {
        throw new Exception("Failed to connect: " . pg_last_error());
    }
    
    echo "<div style='color: green;'>Successfully connected to PostgreSQL</div>";

    // Create database_stats table
    echo "<h2>Creating database_stats table...</h2>";
    
    $result = pg_query($conn, "
        CREATE TABLE IF NOT EXISTS database_stats (
            id SERIAL PRIMARY KEY,
            db_type VARCHAR(20) NOT NULL,
            total_users INTEGER DEFAULT 0,
            total_notes INTEGER DEFAULT 0,
            disk_usage_bytes BIGINT DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    if ($result) {
        echo "<span style='color: green;'>Successfully created database_stats table</span><br>";
        
        // Grant permissions
        echo "Setting permissions on database_stats table...<br>";
        $result = pg_query($conn, "GRANT ALL PRIVILEGES ON TABLE database_stats TO {$DB_CONFIG['postgresql']['user']}");
        
        if ($result) {
            echo "<span style='color: green;'>Successfully granted permissions</span><br>";
        } else {
            echo "<span style='color: red;'>Failed to grant permissions: " . pg_last_error($conn) . "</span><br>";
        }
        
        // Grant permissions on sequence
        echo "Setting permissions on database_stats_id_seq sequence...<br>";
        $result = pg_query($conn, "GRANT ALL PRIVILEGES ON SEQUENCE database_stats_id_seq TO {$DB_CONFIG['postgresql']['user']}");
        
        if ($result) {
            echo "<span style='color: green;'>Successfully granted sequence permissions</span><br>";
        } else {
            echo "<span style='color: red;'>Failed to grant sequence permissions: " . pg_last_error($conn) . "</span><br>";
        }
        
        // Insert initial stats
        echo "Inserting initial statistics...<br>";
        
        // Count users
        $count_users = pg_query($conn, "SELECT COUNT(*) FROM users");
        $user_count = 0;
        if ($count_users) {
            $user_count = pg_fetch_result($count_users, 0, 0);
        }
        
        // Count notes
        $count_notes = pg_query($conn, "SELECT COUNT(*) FROM notes");
        $note_count = 0;
        if ($count_notes) {
            $note_count = pg_fetch_result($count_notes, 0, 0);
        }
        
        // Insert PostgreSQL stats
        $insert_pg = pg_query($conn, "
            INSERT INTO database_stats (db_type, total_users, total_notes, last_updated)
            VALUES ('postgresql', $user_count, $note_count, CURRENT_TIMESTAMP)
            ON CONFLICT (id) WHERE db_type = 'postgresql'
            DO UPDATE SET 
                total_users = EXCLUDED.total_users,
                total_notes = EXCLUDED.total_notes,
                last_updated = CURRENT_TIMESTAMP
        ");
        
        if ($insert_pg) {
            echo "<span style='color: green;'>Successfully inserted PostgreSQL stats</span><br>";
        } else {
            echo "<span style='color: red;'>Failed to insert PostgreSQL stats: " . pg_last_error($conn) . "</span><br>";
        }
        
        // Insert SQLite stats (placeholders)
        $insert_sqlite = pg_query($conn, "
            INSERT INTO database_stats (db_type, total_users, total_notes, last_updated)
            VALUES ('sqlite', 0, 0, CURRENT_TIMESTAMP)
            ON CONFLICT (id) WHERE db_type = 'sqlite'
            DO UPDATE SET 
                last_updated = CURRENT_TIMESTAMP
        ");
        
        if ($insert_sqlite) {
            echo "<span style='color: green;'>Successfully inserted SQLite stats</span><br>";
        } else {
            echo "<span style='color: red;'>Failed to insert SQLite stats: " . pg_last_error($conn) . "</span><br>";
        }
        
    } else {
        echo "<span style='color: red;'>Failed to create database_stats table: " . pg_last_error($conn) . "</span><br>";
    }
    
    echo "<div style='background-color: #dff0d8; color: #3c763d; padding: 10px; margin-top: 20px; border-radius: 5px;'>";
    echo "<h3>Success!</h3>";
    echo "<p>The database_stats table has been created and initialized.</p>";
    echo "</div>";
    
    // Close the connection
    pg_close($conn);
    
} catch (Exception $e) {
    echo "<div style='background-color: #f2dede; color: #a94442; padding: 10px; margin-top: 20px; border-radius: 5px;'>";
    echo "<h3>Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

// Link back to admin panel
echo "<div style='margin-top: 20px;'>";
echo "<a href='/admin/index.php' style='display: inline-block; background: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Return to Admin Panel</a>";
echo "</div>";
?>
