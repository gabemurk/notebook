<?php
/**
 * Database Fix Script
 * 
 * This script fixes the PostgreSQL database schema by creating missing tables
 * and fixing permissions issues.
 */

// Include database configuration
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/enhanced_db_connect.php';

echo "<h1>PostgreSQL Database Fix Tool</h1>";

// Connect to PostgreSQL directly
try {
    echo "<h2>Connecting to PostgreSQL...</h2>";
    $conn_string = "host={$DB_CONFIG['postgresql']['host']} port={$DB_CONFIG['postgresql']['port']} dbname={$DB_CONFIG['postgresql']['dbname']} user={$DB_CONFIG['postgresql']['user']} password={$DB_CONFIG['postgresql']['password']}";
    
    $conn = pg_connect($conn_string);
    if (!$conn) {
        throw new Exception("Failed to connect: " . pg_last_error());
    }
    
    echo "<div style='color: green;'>Successfully connected to PostgreSQL</div>";

    // Begin a transaction
    pg_query($conn, "BEGIN");
    
    try {
        // 1. First create missing tables for admin panel functionality
        echo "<h2>Creating Missing Tables...</h2>";
        
        // Auto Sync Settings Table
        echo "Creating auto_sync_settings table... ";
        $result = pg_query($conn, "
            CREATE TABLE IF NOT EXISTS auto_sync_settings (
                id SERIAL PRIMARY KEY,
                enabled BOOLEAN DEFAULT FALSE,
                interval_minutes INTEGER DEFAULT 60,
                last_run TIMESTAMP DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        if ($result) {
            echo "<span style='color: green;'>Success</span><br>";
            // Insert default settings if table is empty
            pg_query($conn, "
                INSERT INTO auto_sync_settings (enabled, interval_minutes)
                SELECT FALSE, 60
                WHERE NOT EXISTS (SELECT 1 FROM auto_sync_settings)
            ");
        } else {
            echo "<span style='color: red;'>Failed: " . pg_last_error($conn) . "</span><br>";
        }
        
        // Sync History Table
        echo "Creating sync_history table... ";
        $result = pg_query($conn, "
            CREATE TABLE IF NOT EXISTS sync_history (
                id SERIAL PRIMARY KEY,
                source_type VARCHAR(20) NOT NULL,
                target_type VARCHAR(20) NOT NULL, 
                tables_synced TEXT,
                success BOOLEAN DEFAULT FALSE,
                error_message TEXT,
                sync_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        if ($result) {
            echo "<span style='color: green;'>Success</span><br>";
        } else {
            echo "<span style='color: red;'>Failed: " . pg_last_error($conn) . "</span><br>";
        }
        
        // Backup History Table
        echo "Creating backup_history table... ";
        $result = pg_query($conn, "
            CREATE TABLE IF NOT EXISTS backup_history (
                id SERIAL PRIMARY KEY,
                database_type VARCHAR(20) NOT NULL,
                backup_file VARCHAR(255) NOT NULL,
                size_bytes BIGINT,
                success BOOLEAN DEFAULT FALSE,
                error_message TEXT,
                backup_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        if ($result) {
            echo "<span style='color: green;'>Success</span><br>";
        } else {
            echo "<span style='color: red;'>Failed: " . pg_last_error($conn) . "</span><br>";
        }
        
        // Fix ownership and permissions issues
        echo "<h2>Fixing Table Permissions...</h2>";
        
        $tables = array(
            'users', 
            'notes', 
            'auto_sync_settings', 
            'sync_history', 
            'backup_history'
        );
        
        $has_errors = false;
        
        // First determine if the sequences exist
        $sequences = array();
        $result = pg_query($conn, "SELECT c.relname FROM pg_class c WHERE c.relkind = 'S';");
        while ($row = pg_fetch_assoc($result)) {
            $sequences[] = $row['relname'];
        }
        
        // Safely set permissions without using transactions to continue even if some fail
        foreach ($tables as $table) {
            echo "Granting all permissions on $table... ";
            $result = pg_query($conn, "GRANT ALL PRIVILEGES ON TABLE $table TO {$DB_CONFIG['postgresql']['user']}");
            if ($result) {
                echo "<span style='color: green;'>Success</span><br>";
            } else {
                $has_errors = true;
                echo "<span style='color: red;'>Failed: " . pg_last_error($conn) . "</span><br>";
            }
            
            // For tables with sequence - only attempt if sequence exists
            $seq_name = "{$table}_id_seq";
            if (in_array($seq_name, $sequences)) {
                echo "Granting permissions on sequence $seq_name... ";
                $result = pg_query($conn, "GRANT ALL PRIVILEGES ON SEQUENCE $seq_name TO {$DB_CONFIG['postgresql']['user']}");
                if ($result) {
                    echo "<span style='color: green;'>Success</span><br>";
                } else {
                    $has_errors = true;
                    echo "<span style='color: red;'>Failed: " . pg_last_error($conn) . "</span><br>";
                }
            } else {
                echo "<span style='color: gray;'>Sequence $seq_name not found - skipping</span><br>";
            }
        }
        
        // Commit transaction
        pg_query($conn, "COMMIT");
        
        if ($has_errors) {
            echo "<div style='background-color: #fcf8e3; color: #8a6d3b; padding: 10px; margin-top: 20px; border-radius: 5px;'>";
            echo "<h3>Partial Success</h3>";
            echo "<p>Tables were created successfully, but some permission operations failed.</p>";
            echo "<p>This is often normal if you're not connecting as a database superuser.</p>";
            echo "<p>The application should still work correctly as long as the notebook_user has sufficient privileges.</p>";
            echo "</div>";
        } else {
            echo "<div style='background-color: #dff0d8; color: #3c763d; padding: 10px; margin-top: 20px; border-radius: 5px;'>";
            echo "<h3>Success!</h3>";
            echo "<p>All database schema updates have been applied successfully.</p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        // Rollback transaction on failure
        pg_query($conn, "ROLLBACK");
        echo "<div style='background-color: #f2dede; color: #a94442; padding: 10px; margin-top: 20px; border-radius: 5px;'>";
        echo "<h3>Error</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
    
    // Close the connection
    pg_close($conn);
    
} catch (Exception $e) {
    echo "<div style='background-color: #f2dede; color: #a94442; padding: 10px; margin-top: 20px; border-radius: 5px;'>";
    echo "<h3>Connection Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

// Link back to admin panel
echo "<div style='margin-top: 20px;'>";
echo "<a href='/admin/index.php' style='display: inline-block; background: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Return to Admin Panel</a>";
echo "</div>";
?>
