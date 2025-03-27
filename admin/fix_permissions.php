<?php
/**
 * PostgreSQL Table Permission Fix Tool
 * 
 * This script fixes ownership and permission issues for the notebook_user
 * on PostgreSQL tables, focused on fixing the "must be owner of table notes" error.
 */

// Include database configuration
require_once __DIR__ . '/../database/config.php';

// Simple HTML styling
echo "<!DOCTYPE html>
<html>
<head>
    <title>PostgreSQL Permission Fix</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6;
            margin: 0; 
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre {
            background: #f8f8f8;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
        }
        .btn { 
            display: inline-block; 
            background: #4CAF50; 
            color: white; 
            padding: 10px 15px; 
            text-decoration: none; 
            border-radius: 4px;
            margin-top: 20px;
        }
        h1, h2 {
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        details {
            margin: 10px 0;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        summary {
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>PostgreSQL Permission Fix Tool</h1>";

try {
    // Function to execute and log SQL commands
    function execute_sql($conn, $sql, $description) {
        echo "<p>Attempting: $description...</p>";
        $result = @pg_query($conn, $sql);
        
        if ($result) {
            echo "<p class='success'>✅ Success: $description</p>";
            return true;
        } else {
            echo "<p class='error'>❌ Error: " . pg_last_error($conn) . "</p>";
            return false;
        }
    }
    
    // Part 1: Connect as a superuser (postgres)
    echo "<h2>Step 1: Connecting as PostgreSQL superuser</h2>";
    echo "<p>First, we need to connect to PostgreSQL as a superuser to fix permissions.</p>";
    
    $postgres_conn = @pg_connect("host=127.0.0.1 port=5432 dbname=postgres user=postgres password=postgres");
    
    if (!$postgres_conn) {
        // Try without password (trust authentication)
        $postgres_conn = @pg_connect("host=127.0.0.1 port=5432 dbname=postgres user=postgres");
    }
    
    // If both attempts fail, try as the current database user
    if (!$postgres_conn) {
        echo "<p class='warning'>⚠️ Could not connect as PostgreSQL superuser. Trying to connect as regular user to see what permissions we have.</p>";
        
        // Connect with notebook user
        $notebook_conn = pg_connect("host={$DB_CONFIG['postgresql']['host']} port={$DB_CONFIG['postgresql']['port']} 
                                    dbname={$DB_CONFIG['postgresql']['dbname']} 
                                    user={$DB_CONFIG['postgresql']['user']} 
                                    password={$DB_CONFIG['postgresql']['password']}");
        
        if (!$notebook_conn) {
            throw new Exception("Could not connect with either superuser or notebook_user");
        }
        
        echo "<p class='success'>✅ Connected as notebook_user</p>";
        
        // Get current database
        $db_query = pg_query($notebook_conn, "SELECT current_database()");
        $current_db = pg_fetch_result($db_query, 0, 0);
        echo "<p>Current database: <strong>$current_db</strong></p>";
        
        // Get current user
        $user_query = pg_query($notebook_conn, "SELECT current_user");
        $current_user = pg_fetch_result($user_query, 0, 0);
        echo "<p>Connected as user: <strong>$current_user</strong></p>";
        
        // Check if we can create a workaround view
        echo "<h2>Step 2: Creating workarounds without superuser access</h2>";
        echo "<p>Since we don't have superuser access, we'll try alternative approaches.</p>";
        
        // Try to create a view for the notes table
        $view_created = execute_sql($notebook_conn, "
            CREATE OR REPLACE VIEW public.notes_view AS
            SELECT * FROM public.notes;
        ", "Create a view of the notes table");
        
        if ($view_created) {
            execute_sql($notebook_conn, "GRANT ALL PRIVILEGES ON notes_view TO {$DB_CONFIG['postgresql']['user']};", 
                       "Grant all privileges on notes_view");
            
            echo "<p class='success'>✅ Created notes_view as a workaround for the notes table</p>";
            echo "<p>Now you can update the application code to use 'notes_view' instead of 'notes' for queries.</p>";
            
            // Example code modification
            echo "<details>
                    <summary>Code Changes Needed</summary>
                    <p>In your file <code>/var/www/html/database/enhanced_db_connect.php</code> around line 597:</p>
                    <pre>
// Replace this line:
CREATE INDEX IF NOT EXISTS idx_notes_user_id ON notes(user_id)

// With this version:
CREATE INDEX IF NOT EXISTS idx_notes_view_user_id ON notes_view(user_id)

// And update any queries that use the 'notes' table to use 'notes_view' instead
// For example:
// 'SELECT * FROM notes' becomes 'SELECT * FROM notes_view'
                    </pre>
                </details>";
        } else {
            echo "<p class='warning'>⚠️ Could not create a view. Let's try to modify our application code instead.</p>";
            echo "<p>Since we can't change the database permissions or create views, let's update the application code to handle permission errors gracefully:</p>";
            
            echo "<details>
                    <summary>Recommended Code Changes</summary>
                    <p>In your file <code>/var/www/html/database/enhanced_db_connect.php</code> around line 597, update the code to handle permission errors:</p>
                    <pre>
// Replace this:
CREATE INDEX IF NOT EXISTS idx_notes_user_id ON notes(user_id)

// With this try-catch block:
try {
    $result = pg_query(\$conn, \"
        CREATE INDEX IF NOT EXISTS idx_notes_user_id ON notes(user_id)
    \");
    
    if (!\$result) {
        // Log the error but don't fail - this is non-critical
        error_log(\"Note: Could not create index on notes table: \" . pg_last_error(\$conn));
    }
} catch (Exception \$e) {
    // Just log the error and continue
    error_log(\"Exception creating notes index: \" . \$e->getMessage());
}
                    </pre>
                </details>";
        }
        
        // Close notebook connection
        pg_close($notebook_conn);
    } else {
        // Connected as postgres superuser
        echo "<p class='success'>✅ Connected as PostgreSQL superuser</p>";
        
        // Get current user 
        $user_query = pg_query($postgres_conn, "SELECT current_user");
        $current_user = pg_fetch_result($user_query, 0, 0);
        echo "<p>Connected as user: <strong>$current_user</strong></p>";
        
        // Switch to the notebook database
        $db_switch = pg_query($postgres_conn, "\\c {$DB_CONFIG['postgresql']['dbname']}");
        if (!$db_switch) {
            // If direct switch doesn't work, create a new connection
            $notebook_conn = pg_connect("host=127.0.0.1 port=5432 dbname={$DB_CONFIG['postgresql']['dbname']} user=postgres password=postgres");
            
            if (!$notebook_conn) {
                // Try without password
                $notebook_conn = pg_connect("host=127.0.0.1 port=5432 dbname={$DB_CONFIG['postgresql']['dbname']} user=postgres");
            }
            
            if (!$notebook_conn) {
                throw new Exception("Could not connect to the notebook database as superuser");
            }
            
            // Use the new connection
            $postgres_conn = $notebook_conn;
        }
        
        echo "<p class='success'>✅ Connected to {$DB_CONFIG['postgresql']['dbname']} database</p>";
        
        // Check if the notebook user exists
        $user_check = pg_query($postgres_conn, "SELECT 1 FROM pg_roles WHERE rolname = '{$DB_CONFIG['postgresql']['user']}'");
        
        if (pg_num_rows($user_check) == 0) {
            echo "<p class='warning'>⚠️ User {$DB_CONFIG['postgresql']['user']} does not exist. Creating user...</p>";
            
            execute_sql($postgres_conn, 
                "CREATE USER {$DB_CONFIG['postgresql']['user']} WITH PASSWORD '{$DB_CONFIG['postgresql']['password']}'", 
                "Create notebook_user");
        }
        
        echo "<h2>Step 2: Fixing table ownership and permissions</h2>";
        
        // List of tables to fix permissions for
        $tables = ['notes', 'users', 'database_stats', 'sync_history', 'auto_sync_settings', 'backup_history'];
        
        foreach ($tables as $table) {
            // Check if table exists
            $table_check = pg_query($postgres_conn, "SELECT to_regclass('public.$table')");
            $table_exists = pg_fetch_result($table_check, 0, 0);
            
            if (!empty($table_exists)) {
                echo "<h3>Fixing permissions for table: $table</h3>";
                
                // 1. Change ownership
                execute_sql($postgres_conn, 
                    "ALTER TABLE $table OWNER TO {$DB_CONFIG['postgresql']['user']}", 
                    "Change ownership of $table to {$DB_CONFIG['postgresql']['user']}");
                    
                // 2. Get the sequence name and change its ownership
                $seq_query = pg_query($postgres_conn, "
                    SELECT pg_get_serial_sequence('$table', 'id') as sequence
                ");
                
                if ($seq_query && pg_num_rows($seq_query) > 0) {
                    $sequence = pg_fetch_result($seq_query, 0, 0);
                    
                    if (!empty($sequence)) {
                        execute_sql($postgres_conn, 
                            "ALTER SEQUENCE $sequence OWNER TO {$DB_CONFIG['postgresql']['user']}", 
                            "Change ownership of sequence $sequence to {$DB_CONFIG['postgresql']['user']}");
                    }
                }
                
                // 3. Grant all privileges
                execute_sql($postgres_conn, 
                    "GRANT ALL PRIVILEGES ON TABLE $table TO {$DB_CONFIG['postgresql']['user']}", 
                    "Grant all privileges on $table to {$DB_CONFIG['postgresql']['user']}");
            } else {
                echo "<p class='warning'>⚠️ Table '$table' does not exist. Skipping.</p>";
            }
        }
        
        // Set default privileges for future tables
        execute_sql($postgres_conn, 
            "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO {$DB_CONFIG['postgresql']['user']}", 
            "Set default privileges for future tables");
            
        // Grant permissions on schema
        execute_sql($postgres_conn, 
            "GRANT USAGE ON SCHEMA public TO {$DB_CONFIG['postgresql']['user']}", 
            "Grant usage on public schema");
            
        echo "<h2>Step 3: Testing the fixes</h2>";
        
        // Connect as notebook_user to test permissions
        $notebook_conn = pg_connect("host={$DB_CONFIG['postgresql']['host']} port={$DB_CONFIG['postgresql']['port']} 
                                   dbname={$DB_CONFIG['postgresql']['dbname']} 
                                   user={$DB_CONFIG['postgresql']['user']} 
                                   password={$DB_CONFIG['postgresql']['password']}");
        
        if (!$notebook_conn) {
            echo "<p class='error'>❌ Could not connect as {$DB_CONFIG['postgresql']['user']} to test permissions</p>";
        } else {
            echo "<p class='success'>✅ Connected as {$DB_CONFIG['postgresql']['user']}</p>";
            
            // Test SELECT on notes table
            $select_test = @pg_query($notebook_conn, "SELECT * FROM notes LIMIT 1");
            if ($select_test !== false) {
                echo "<p class='success'>✅ Can SELECT from notes table</p>";
            } else {
                echo "<p class='error'>❌ Cannot SELECT from notes table: " . pg_last_error($notebook_conn) . "</p>";
            }
            
            // Test CREATE INDEX
            $index_test = @pg_query($notebook_conn, "CREATE INDEX IF NOT EXISTS idx_notes_user_id_test ON notes(user_id)");
            if ($index_test !== false) {
                echo "<p class='success'>✅ Can CREATE INDEX on notes table</p>";
            } else {
                echo "<p class='error'>❌ Cannot CREATE INDEX on notes table: " . pg_last_error($notebook_conn) . "</p>";
            }
            
            // Close notebook connection
            pg_close($notebook_conn);
        }
        
        // Close superuser connection
        pg_close($postgres_conn);
    }
    
    echo "<h2>Result</h2>";
    echo "<p>The permission fix process has completed. Check the results above to see if the fixes were successful.</p>";
    echo "<p>If the fixes were successful, you should no longer see the 'must be owner of table notes' error.</p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
}

// Link back to admin panel
echo "<a href='/admin/index.php' class='btn'>Return to Admin Panel</a>";
echo "</div></body></html>";
?>
