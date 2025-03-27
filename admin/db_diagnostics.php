<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Diagnostics</h1>";

// 1. PHP Version and Extensions
echo "<h2>PHP Environment</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Loaded Extensions: <pre>" . implode(", ", get_loaded_extensions()) . "</pre><br>";
echo "PostgreSQL Functions Available: " . (function_exists('pg_connect') ? 'Yes' : 'No') . "<br>";
echo "PDO Drivers Available: <pre>" . implode(", ", PDO::getAvailableDrivers()) . "</pre><br>";

// 2. Database Configuration
require_once __DIR__ . '/../database/config.php';
echo "<h2>Database Configuration</h2>";
echo "<h3>PostgreSQL Config:</h3>";
echo "Host: " . $DB_CONFIG['postgresql']['host'] . "<br>";
echo "Port: " . $DB_CONFIG['postgresql']['port'] . "<br>";
echo "Database: " . $DB_CONFIG['postgresql']['dbname'] . "<br>";
echo "User: " . $DB_CONFIG['postgresql']['user'] . "<br>";
echo "Password: " . str_repeat('*', strlen($DB_CONFIG['postgresql']['password'])) . "<br>";

// 3. Test Direct PostgreSQL Connection
echo "<h2>PostgreSQL Connection Test</h2>";
if (function_exists('pg_connect')) {
    $connection_string = sprintf(
        "host=%s port=%d dbname=%s user=%s password=%s",
        $DB_CONFIG['postgresql']['host'],
        $DB_CONFIG['postgresql']['port'],
        $DB_CONFIG['postgresql']['dbname'],
        $DB_CONFIG['postgresql']['user'],
        $DB_CONFIG['postgresql']['password']
    );
    
    echo "Connection String: " . $connection_string . "<br>";
    
    echo "<h3>Testing Connection...</h3>";
    $conn = @pg_connect($connection_string);
    
    if ($conn) {
        echo "<div style='color: green;'>Connection Successful!</div>";
        
        // Test a simple query
        echo "<h3>Testing Query...</h3>";
        $result = @pg_query($conn, "SELECT current_database() as dbname, current_user as username");
        if ($result) {
            $row = pg_fetch_assoc($result);
            echo "Connected to database: " . $row['dbname'] . "<br>";
            echo "Connected as user: " . $row['username'] . "<br>";
            pg_free_result($result);
        } else {
            echo "<div style='color: red;'>Query Failed: " . pg_last_error($conn) . "</div>";
        }
        
        pg_close($conn);
    } else {
        echo "<div style='color: red;'>Connection Failed: " . pg_last_error() . "</div>";
        
        // Try with different connection options
        echo "<h3>Trying connection without SSL...</h3>";
        $alt_connection_string = $connection_string . " sslmode=disable";
        $conn = @pg_connect($alt_connection_string);
        if ($conn) {
            echo "<div style='color: green;'>Connection with sslmode=disable Successful!</div>";
            pg_close($conn);
        } else {
            echo "<div style='color: red;'>Connection Failed: " . pg_last_error() . "</div>";
        }
    }
} else {
    echo "<div style='color: red;'>PostgreSQL extension is not installed or enabled!</div>";
}

// 4. Test PDO PostgreSQL Connection
echo "<h2>PDO PostgreSQL Connection Test</h2>";
if (in_array('pgsql', PDO::getAvailableDrivers())) {
    try {
        $dsn = "pgsql:host={$DB_CONFIG['postgresql']['host']};port={$DB_CONFIG['postgresql']['port']};dbname={$DB_CONFIG['postgresql']['dbname']};";
        $user = $DB_CONFIG['postgresql']['user'];
        $password = $DB_CONFIG['postgresql']['password'];
        
        echo "DSN: " . $dsn . "<br>";
        echo "Attempting PDO connection...<br>";
        
        $pdoConn = new PDO($dsn, $user, $password);
        $pdoConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<div style='color: green;'>PDO Connection Successful!</div>";
        
        // Test a simple query
        $stmt = $pdoConn->query("SELECT current_database() as dbname, current_user as username");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Connected to database: " . $row['dbname'] . "<br>";
        echo "Connected as user: " . $row['username'] . "<br>";
        
        $pdoConn = null;
    } catch (PDOException $e) {
        echo "<div style='color: red;'>PDO Connection Failed: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div style='color: red;'>PDO PostgreSQL driver not available!</div>";
}

// 5. Check PostgreSQL Server Info
echo "<h2>PostgreSQL Server Environment Check</h2>";
if (function_exists('pg_connect')) {
    $connection_string = sprintf(
        "host=%s port=%d dbname=%s user=%s password=%s",
        $DB_CONFIG['postgresql']['host'],
        $DB_CONFIG['postgresql']['port'],
        $DB_CONFIG['postgresql']['dbname'],
        $DB_CONFIG['postgresql']['user'],
        $DB_CONFIG['postgresql']['password']
    );
    
    $conn = @pg_connect($connection_string);
    
    if ($conn) {
        echo "<h3>Server Information:</h3>";
        $result = @pg_query($conn, "SELECT version()");
        if ($result) {
            $row = pg_fetch_row($result);
            echo "PostgreSQL Version: " . $row[0] . "<br>";
            pg_free_result($result);
        }
        
        echo "<h3>User Permissions:</h3>";
        $result = @pg_query($conn, "SELECT * FROM pg_catalog.pg_roles WHERE rolname = current_user");
        if ($result) {
            $row = pg_fetch_assoc($result);
            echo "<pre>";
            print_r($row);
            echo "</pre>";
            pg_free_result($result);
        }
        
        pg_close($conn);
    }
}

// 6. Test DatabaseManager Connection
echo "<h2>DatabaseManager Connection Test</h2>";
require_once __DIR__ . '/../database/enhanced_db_connect.php';

try {
    $db = new DatabaseManager(DB_TYPE_POSTGRESQL);
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "<div style='color: green;'>DatabaseManager Connection Successful!</div>";
        
        // Check if it's actually usable
        if ($db->getCurrentDbType() === DB_TYPE_POSTGRESQL) {
            $result = $db->executeQuery("SELECT current_database() as dbname, current_user as username");
            if ($result && is_array($result)) {
                echo "Connected to database: " . $result[0]['dbname'] . "<br>";
                echo "Connected as user: " . $result[0]['username'] . "<br>";
            } else {
                echo "<div style='color: red;'>Query Failed through DatabaseManager</div>";
            }
        } else {
            echo "<div style='color: orange;'>Connected but using fallback database: " . $db->getCurrentDbType() . "</div>";
        }
    } else {
        echo "<div style='color: red;'>DatabaseManager Connection Failed</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>DatabaseManager Exception: " . $e->getMessage() . "</div>";
}
?>
