<?php
/**
 * Make notebook_user a PostgreSQL Superuser
 * 
 * This script creates a connection as the postgres user (superuser)
 * and alters the notebook_user role to have superuser privileges.
 */

// Include database configuration
require_once __DIR__ . '/../database/config.php';

// Start output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Make User Superuser</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6;
            margin: 20px; 
            padding: 0;
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
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
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
    </style>
</head>
<body>
    <div class='container'>
        <h1>Make notebook_user a PostgreSQL Superuser</h1>
        <p>This script will attempt to make the notebook_user account a superuser to bypass permission issues.</p>
";

try {
    echo "<h2>Step 1: Connecting as PostgreSQL superuser</h2>";
    
    // Try connecting to PostgreSQL as postgres (superuser)
    $pg_conn = @pg_connect("host=127.0.0.1 port=5432 dbname=postgres user=postgres password=postgres");
    
    if (!$pg_conn) {
        // Try without password (trust authentication)
        $pg_conn = @pg_connect("host=127.0.0.1 port=5432 dbname=postgres user=postgres");
    }
    
    if (!$pg_conn) {
        throw new Exception("Could not connect as PostgreSQL superuser (postgres). Make sure the postgres user exists and you have the correct password or trust authentication is set up.");
    }
    
    echo "<p class='success'>Connected as PostgreSQL superuser</p>";
    
    // Get current user
    $current_user = pg_fetch_result(pg_query($pg_conn, "SELECT current_user"), 0, 0);
    echo "<p>Connected as user: <strong>$current_user</strong></p>";
    
    // Check if notebook_user exists
    echo "<h2>Step 2: Checking if notebook_user exists</h2>";
    
    $user_exists = pg_fetch_result(pg_query($pg_conn, "
        SELECT COUNT(*) FROM pg_roles WHERE rolname = '{$DB_CONFIG['postgresql']['user']}'
    "), 0, 0);
    
    if ($user_exists == '0') {
        echo "<p class='warning'>User {$DB_CONFIG['postgresql']['user']} does not exist. Creating user...</p>";
        
        $create_result = pg_query($pg_conn, "
            CREATE USER {$DB_CONFIG['postgresql']['user']} WITH PASSWORD '{$DB_CONFIG['postgresql']['password']}'
        ");
        
        if (!$create_result) {
            throw new Exception("Failed to create user: " . pg_last_error($pg_conn));
        }
        
        echo "<p class='success'>Created user {$DB_CONFIG['postgresql']['user']}</p>";
    } else {
        echo "<p class='success'>User {$DB_CONFIG['postgresql']['user']} exists</p>";
    }
    
    // Make notebook_user a superuser
    echo "<h2>Step 3: Making notebook_user a superuser</h2>";
    
    $alter_result = pg_query($pg_conn, "
        ALTER USER {$DB_CONFIG['postgresql']['user']} WITH SUPERUSER
    ");
    
    if (!$alter_result) {
        throw new Exception("Failed to make user a superuser: " . pg_last_error($pg_conn));
    }
    
    echo "<p class='success'>{$DB_CONFIG['postgresql']['user']} has been granted superuser privileges</p>";
    
    // Test the new superuser privileges
    echo "<h2>Step 4: Testing superuser privileges</h2>";
    
    // Connect as notebook_user
    $notebook_conn = pg_connect("
        host={$DB_CONFIG['postgresql']['host']} 
        port={$DB_CONFIG['postgresql']['port']} 
        dbname={$DB_CONFIG['postgresql']['dbname']} 
        user={$DB_CONFIG['postgresql']['user']} 
        password={$DB_CONFIG['postgresql']['password']}
    ");
    
    if (!$notebook_conn) {
        throw new Exception("Could not connect as {$DB_CONFIG['postgresql']['user']}: " . pg_last_error());
    }
    
    echo "<p class='success'>Successfully connected as {$DB_CONFIG['postgresql']['user']}</p>";
    
    // Check if user is now a superuser
    $is_superuser = pg_fetch_result(pg_query($notebook_conn, "
        SELECT usesuper FROM pg_user WHERE usename = current_user
    "), 0, 0);
    
    if ($is_superuser === 't') {
        echo "<p class='success'>{$DB_CONFIG['postgresql']['user']} is now confirmed as a superuser!</p>";
    } else {
        echo "<p class='error'>{$DB_CONFIG['postgresql']['user']} is still not a superuser. Something went wrong.</p>";
    }
    
    // Test creating an index on the notes table
    $index_result = @pg_query($notebook_conn, "
        CREATE INDEX IF NOT EXISTS idx_notes_user_id ON notes(user_id)
    ");
    
    if ($index_result) {
        echo "<p class='success'>Successfully created index on notes table!</p>";
    } else {
        echo "<p class='error'>Still cannot create index: " . pg_last_error($notebook_conn) . "</p>";
    }
    
    // Close connections
    pg_close($notebook_conn);
    pg_close($pg_conn);
    
    echo "<h2>Result</h2>";
    echo "<p class='success'>The database user {$DB_CONFIG['postgresql']['user']} should now have superuser privileges!</p>";
    echo "<p>You should be able to perform all database operations without permission errors.</p>";
    echo "<p><strong>Note:</strong> Making a user a superuser grants them full administrative privileges over the database. This is generally not recommended for production environments due to security concerns, but is acceptable for development/testing.</p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
}

// Link back to admin panel
echo "<a href='/admin/index.php' class='btn'>Return to Admin Panel</a>";
echo "</div></body></html>";
?>
