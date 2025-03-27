<?php
/**
 * Fix Table Ownership Script
 * 
 * This script runs SQL commands to fix ownership of PostgreSQL tables
 * using sudo to execute commands as the postgres user.
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix PostgreSQL Table Ownership</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6;
            margin: 20px; 
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        pre {
            background-color: #f8f8f8;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
            border: 1px solid #ddd;
        }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Fix PostgreSQL Table Ownership</h1>";

// Include database configuration
require_once __DIR__ . '/../database/config.php';

// Function to execute a command and capture output
function exec_command($command) {
    $output = [];
    $return_var = 0;
    exec($command . " 2>&1", $output, $return_var);
    return [
        'output' => implode("\n", $output),
        'success' => ($return_var === 0)
    ];
}

// Function to run a PostgreSQL command as postgres user
function run_psql_command($command, $dbname = "notebook") {
    // Escape single quotes in the command for the shell
    $escaped_command = str_replace("'", "'\\''", $command);
    
    // The full command to execute - use the correct password
    $full_command = "PGPASSWORD='password' psql -h 127.0.0.1 -U postgres -d $dbname -c '$escaped_command'";
    
    echo "<p>Executing command:</p>";
    echo "<pre>$full_command</pre>";
    
    $result = exec_command($full_command);
    
    if ($result['success']) {
        echo "<p class='success'>✅ Command succeeded</p>";
    } else {
        echo "<p class='error'>❌ Command failed</p>";
    }
    
    echo "<p>Output:</p>";
    echo "<pre>" . htmlspecialchars($result['output']) . "</pre>";
    
    return $result['success'];
}

try {
    echo "<h2>Step 1: Fix table ownership for 'notes' table</h2>";
    
    // Change ownership of notes table
    run_psql_command("ALTER TABLE notes OWNER TO {$DB_CONFIG['postgresql']['user']};");
    
    // Change ownership of sequence if it exists
    run_psql_command("
        DO $$
        DECLARE
            seq_name text;
        BEGIN
            SELECT pg_get_serial_sequence('notes', 'id') INTO seq_name;
            IF seq_name IS NOT NULL THEN
                EXECUTE 'ALTER SEQUENCE ' || seq_name || ' OWNER TO {$DB_CONFIG['postgresql']['user']}';
            END IF;
        END $$;
    ");
    
    echo "<h2>Step 2: Fix table ownership for 'users' table</h2>";
    
    // Change ownership of users table
    run_psql_command("ALTER TABLE users OWNER TO {$DB_CONFIG['postgresql']['user']};");
    
    // Change ownership of sequence if it exists
    run_psql_command("
        DO $$
        DECLARE
            seq_name text;
        BEGIN
            SELECT pg_get_serial_sequence('users', 'id') INTO seq_name;
            IF seq_name IS NOT NULL THEN
                EXECUTE 'ALTER SEQUENCE ' || seq_name || ' OWNER TO {$DB_CONFIG['postgresql']['user']}';
            END IF;
        END $$;
    ");
    
    echo "<h2>Step 3: Grant all privileges and set default privileges</h2>";
    
    // Grant all privileges on all tables
    run_psql_command("GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO {$DB_CONFIG['postgresql']['user']};");
    
    // Set default privileges for future tables
    run_psql_command("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO {$DB_CONFIG['postgresql']['user']};");
    
    echo "<h2>Step 4: Verify the changes</h2>";
    
    // Check ownership of notes table
    run_psql_command("
        SELECT tablename, tableowner 
        FROM pg_tables 
        WHERE schemaname = 'public' AND tablename IN ('notes', 'users');
    ");
    
    echo "<h2>Summary</h2>";
    echo "<p>The ownership of the 'notes' and 'users' tables should now be fixed.</p>";
    echo "<p>Try using the application again to see if the issue is resolved.</p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
}

echo "<a href='/admin/index.php' class='btn'>Return to Admin Panel</a>";
echo "</div></body></html>";
?>
