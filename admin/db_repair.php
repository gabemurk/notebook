<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Repair Tool</h1>";

// Load configuration
require_once __DIR__ . '/../database/config.php';

// Function to test a PostgreSQL connection
function test_pg_connection($host, $port, $dbname, $user, $password) {
    try {
        if (!function_exists('pg_connect')) {
            echo "<div style='color: red;'>PostgreSQL extension is not installed!</div>";
            return false;
        }
        
        $conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password";
        echo "Testing connection with: $conn_string<br>";
        
        $conn = @pg_connect($conn_string);
        if ($conn) {
            echo "<div style='color: green;'>Connection successful!</div>";
            pg_close($conn);
            return true;
        } else {
            echo "<div style='color: red;'>Connection failed!</div>";
            if (function_exists('error_get_last')) {
                $error = error_get_last();
                if ($error) {
                    echo "Error: " . $error['message'] . "<br>";
                }
            }
            return false;
        }
    } catch (Exception $e) {
        echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
        return false;
    }
}

// Display current configuration
echo "<h2>Current Configuration</h2>";
echo "Host: " . $DB_CONFIG['postgresql']['host'] . "<br>";
echo "Port: " . $DB_CONFIG['postgresql']['port'] . "<br>";
echo "Database: " . $DB_CONFIG['postgresql']['dbname'] . "<br>";
echo "User: " . $DB_CONFIG['postgresql']['user'] . "<br>";
echo "Password: " . str_repeat('*', strlen($DB_CONFIG['postgresql']['password'])) . "<br>";

// Test connection with current config
echo "<h2>Testing Current Configuration</h2>";
$current_conn_success = test_pg_connection(
    $DB_CONFIG['postgresql']['host'],
    $DB_CONFIG['postgresql']['port'],
    $DB_CONFIG['postgresql']['dbname'],
    $DB_CONFIG['postgresql']['user'],
    $DB_CONFIG['postgresql']['password']
);

// If current connection fails, try different approaches
if (!$current_conn_success) {
    // Try connecting with different parameters
    echo "<h2>Trying Alternative Configurations</h2>";
    
    // Try PostgreSQL superuser (postgres) if allowed
    echo "<h3>Trying with postgres superuser:</h3>";
    $postgres_success = test_pg_connection(
        $DB_CONFIG['postgresql']['host'],
        $DB_CONFIG['postgresql']['port'],
        "postgres", // Try connecting to default postgres database
        "postgres",  // Try PostgreSQL superuser
        "postgres"   // Standard default password
    );
    
    // Try connecting to the default "postgres" database with the notebook_user
    echo "<h3>Trying notebook_user with postgres database:</h3>";
    test_pg_connection(
        $DB_CONFIG['postgresql']['host'],
        $DB_CONFIG['postgresql']['port'],
        "postgres", // Try connecting to default postgres database
        $DB_CONFIG['postgresql']['user'],
        $DB_CONFIG['postgresql']['password']
    );
    
    // Try connecting to just "template1" database
    echo "<h3>Trying postgres user with template1 database:</h3>";
    test_pg_connection(
        $DB_CONFIG['postgresql']['host'],
        $DB_CONFIG['postgresql']['port'],
        "template1", // Try connecting to template1 database
        "postgres",  // Try PostgreSQL superuser
        "postgres"   // Standard default password
    );
}

// Display suggestions based on connection tests
echo "<h2>Recommendations</h2>";
if (!$current_conn_success) {
    echo "<div style='background: #ffe6e6; padding: 10px; border: 1px solid #ffcccc;'>";
    echo "<h3>Issues Detected</h3>";
    echo "<p>The application cannot connect to PostgreSQL with the current settings.</p>";
    
    echo "<h4>Possible solutions:</h4>";
    echo "<ol>";
    echo "<li>Verify PostgreSQL is running on the configured host and port</li>";
    echo "<li>Check that the database '" . $DB_CONFIG['postgresql']['dbname'] . "' exists</li>";
    echo "<li>Ensure user '" . $DB_CONFIG['postgresql']['user'] . "' exists with correct permissions</li>";
    echo "<li>Verify the password in config.php is correct</li>";
    echo "</ol>";
    
    echo "<h4>Database Setup Commands</h4>";
    echo "<p>If you have access to PostgreSQL superuser, you can run these commands to fix the issues:</p>";
    echo "<pre>";
    echo "# Connect to PostgreSQL\n";
    echo "sudo -u postgres psql\n\n";
    echo "# Create user\n";
    echo "CREATE USER " . $DB_CONFIG['postgresql']['user'] . " WITH PASSWORD '" . $DB_CONFIG['postgresql']['password'] . "';\n\n";
    echo "# Create database\n";
    echo "CREATE DATABASE " . $DB_CONFIG['postgresql']['dbname'] . " OWNER " . $DB_CONFIG['postgresql']['user'] . ";\n\n";
    echo "# Grant privileges\n";
    echo "GRANT ALL PRIVILEGES ON DATABASE " . $DB_CONFIG['postgresql']['dbname'] . " TO " . $DB_CONFIG['postgresql']['user'] . ";\n";
    echo "</pre>";
    echo "</div>";
} else {
    echo "<div style='background: #e6ffe6; padding: 10px; border: 1px solid #ccffcc;'>";
    echo "<h3>Connection Successful</h3>";
    echo "<p>The application can connect to PostgreSQL with the current settings.</p>";
    echo "<p>If you're still experiencing issues in the admin panel, try the following:</p>";
    echo "<ol>";
    echo "<li>Restart the web server</li>";
    echo "<li>Check PHP error logs for any additional issues</li>";
    echo "<li>Verify file permissions on database directories</li>";
    echo "</ol>";
    echo "</div>";
}

// Update admin interface with a test connection button
echo "<h2>Connection Troubleshooting</h2>";
echo "<div style='margin-top: 20px;'>";
echo "<a href='/admin/index.php' style='display: inline-block; background: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Return to Admin Panel</a> ";
echo "</div>";
?>
