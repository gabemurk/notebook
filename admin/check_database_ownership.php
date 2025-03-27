<?php
/**
 * Database Ownership Checker
 * 
 * This script checks ownership and permissions of tables in the PostgreSQL database.
 */

// Include database configuration
require_once __DIR__ . '/../database/config.php';

// Simple HTML styling
echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Ownership Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { border: 1px solid #ccc; border-radius: 5px; padding: 15px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
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
        <h1>Database Ownership Check</h1>";

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
    
    // Get current user
    $current_user = pg_fetch_result(pg_query($conn, "SELECT current_user"), 0, 0);
    echo "<p>Current user: <strong>$current_user</strong></p>";
    
    // Get current database
    $current_db = pg_fetch_result(pg_query($conn, "SELECT current_database()"), 0, 0);
    echo "<p>Current database: <strong>$current_db</strong></p>";
    
    echo "</div>";
    
    // Check table ownership
    echo "<div class='card'>";
    echo "<h2>Table Ownership Information</h2>";
    
    $tables_query = "
        SELECT 
            t.table_name, 
            t.table_type,
            t.table_schema,
            u.usename as owner
        FROM 
            information_schema.tables t
        JOIN 
            pg_catalog.pg_class c ON t.table_name = c.relname
        JOIN 
            pg_catalog.pg_user u ON c.relowner = u.usesysid
        WHERE 
            t.table_schema NOT IN ('pg_catalog', 'information_schema') 
            AND t.table_schema NOT LIKE 'pg_toast%'
            AND t.table_type = 'BASE TABLE'
        ORDER BY 
            t.table_schema, 
            t.table_name
    ";
    
    $tables_result = pg_query($conn, $tables_query);
    
    if ($tables_result) {
        echo "<table>";
        echo "<tr><th>Schema</th><th>Table Name</th><th>Type</th><th>Owner</th><th>Can Access?</th></tr>";
        
        while ($row = pg_fetch_assoc($tables_result)) {
            $table_name = $row['table_name'];
            $schema = $row['table_schema'];
            $owner = $row['owner'];
            $type = $row['table_type'];
            
            // Check if current user can access the table
            $can_access = false;
            $access_check = pg_query($conn, "SELECT has_table_privilege('$current_user', '$schema.$table_name', 'SELECT')");
            if ($access_check) {
                $can_access = pg_fetch_result($access_check, 0, 0) === 't';
            }
            
            $access_class = $can_access ? 'success' : 'error';
            $access_text = $can_access ? 'Yes' : 'No';
            
            $owner_class = ($owner === $current_user) ? 'success' : '';
            
            echo "<tr>";
            echo "<td>$schema</td>";
            echo "<td>$table_name</td>";
            echo "<td>$type</td>";
            echo "<td class='$owner_class'>$owner</td>";
            echo "<td class='$access_class'>$access_text</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p class='error'>Error retrieving tables: " . pg_last_error($conn) . "</p>";
    }
    
    echo "</div>";
    
    // Check user permissions
    echo "<div class='card'>";
    echo "<h2>Current User's Role Memberships</h2>";
    
    $roles_query = "
        SELECT 
            r.rolname as role_name,
            CASE WHEN r.rolsuper THEN 'Yes' ELSE 'No' END as is_superuser,
            CASE WHEN r.rolcreaterole THEN 'Yes' ELSE 'No' END as can_create_roles,
            CASE WHEN r.rolcreatedb THEN 'Yes' ELSE 'No' END as can_create_db,
            CASE WHEN r.rolreplication THEN 'Yes' ELSE 'No' END as can_replicate
        FROM 
            pg_catalog.pg_roles r
        JOIN 
            pg_catalog.pg_auth_members m ON (m.member = r.oid)
        JOIN 
            pg_catalog.pg_roles u ON (u.oid = m.roleid)
        WHERE 
            u.rolname = '$current_user'
    ";
    
    $roles_result = pg_query($conn, $roles_query);
    
    if ($roles_result && pg_num_rows($roles_result) > 0) {
        echo "<table>";
        echo "<tr><th>Role Name</th><th>Is Superuser</th><th>Can Create Roles</th><th>Can Create DB</th><th>Can Replicate</th></tr>";
        
        while ($row = pg_fetch_assoc($roles_result)) {
            echo "<tr>";
            echo "<td>" . $row['role_name'] . "</td>";
            echo "<td>" . $row['is_superuser'] . "</td>";
            echo "<td>" . $row['can_create_roles'] . "</td>";
            echo "<td>" . $row['can_create_db'] . "</td>";
            echo "<td>" . $row['can_replicate'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No specific role memberships found for user $current_user.</p>";
        
        // Check direct user permissions
        $user_query = "
            SELECT 
                rolname as user_name,
                CASE WHEN rolsuper THEN 'Yes' ELSE 'No' END as is_superuser,
                CASE WHEN rolcreaterole THEN 'Yes' ELSE 'No' END as can_create_roles,
                CASE WHEN rolcreatedb THEN 'Yes' ELSE 'No' END as can_create_db,
                CASE WHEN rolreplication THEN 'Yes' ELSE 'No' END as can_replicate
            FROM 
                pg_catalog.pg_roles
            WHERE 
                rolname = '$current_user'
        ";
        
        $user_result = pg_query($conn, $user_query);
        
        if ($user_result && pg_num_rows($user_result) > 0) {
            echo "<h3>Direct User Permissions</h3>";
            echo "<table>";
            echo "<tr><th>User Name</th><th>Is Superuser</th><th>Can Create Roles</th><th>Can Create DB</th><th>Can Replicate</th></tr>";
            
            $row = pg_fetch_assoc($user_result);
            echo "<tr>";
            echo "<td>" . $row['user_name'] . "</td>";
            echo "<td>" . $row['is_superuser'] . "</td>";
            echo "<td>" . $row['can_create_roles'] . "</td>";
            echo "<td>" . $row['can_create_db'] . "</td>";
            echo "<td>" . $row['can_replicate'] . "</td>";
            echo "</tr>";
            
            echo "</table>";
        }
    }
    
    echo "</div>";
    
    // Potential solutions
    echo "<div class='card'>";
    echo "<h2>Potential Solutions for Permission Issues</h2>";
    
    echo "<h3>Option 1: Grant Permissions on Specific Tables</h3>";
    echo "<p>To grant SELECT, INSERT, UPDATE, DELETE permissions on a specific table:</p>";
    echo "<pre>GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE tablename TO $current_user;</pre>";
    
    echo "<h3>Option 2: Grant All Permissions on All Tables</h3>";
    echo "<p>To grant all permissions on all tables in the current schema:</p>";
    echo "<pre>GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO $current_user;</pre>";
    
    echo "<h3>Option 3: Change Table Ownership</h3>";
    echo "<p>To change ownership of a table:</p>";
    echo "<pre>ALTER TABLE tablename OWNER TO $current_user;</pre>";
    
    echo "<h3>Option 4: Workaround in PHP Code</h3>";
    echo "<p>If you cannot change database permissions, consider these code-level solutions:</p>";
    echo "<ul>";
    echo "<li>Use try/catch blocks to handle permission errors gracefully</li>";
    echo "<li>Create views that the user has access to</li>";
    echo "<li>Implement a service account with proper permissions</li>";
    echo "</ul>";
    
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
