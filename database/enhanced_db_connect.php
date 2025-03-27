<?php
/**
 * Enhanced Database Connection Manager
 * 
 * A flexible database connection manager that supports multiple database types:
 * - PostgreSQL
 * - SQLite
 * - MySQL
 * - SQL Server
 * - Oracle
 * - MongoDB
 * 
 * Usage:
 * $db = new DatabaseManager('mysql', [
 *     'host' => 'localhost',
 *     'dbname' => 'my_database',
 *     'user' => 'root',
 *     'password' => 'secret'
 * ]);
 * $conn = $db->getConnection();
 */

// Define constants for database types
define('DB_TYPE_POSTGRESQL', 'postgresql');
define('DB_TYPE_SQLITE', 'sqlite');
define('DB_TYPE_MYSQL', 'mysql');
define('DB_TYPE_SQLSERVER', 'sqlserver');
define('DB_TYPE_ORACLE', 'oracle');
define('DB_TYPE_MONGODB', 'mongodb');

// Define auto mode (try multiple databases in priority order)
define('DB_TYPE_AUTO', 'auto');

// Global variable to track the active database type
$GLOBALS['active_db_type'] = null;

/**
 * Main Database Manager class
 */
class DatabaseManager {
    // Database configuration
    private $db_type;
    private $config;
    private $connection = null;
    
    // Track multiple database connections
    private $connections = [];
    private $primary_db_type = DB_TYPE_POSTGRESQL;
    private $secondary_db_type = DB_TYPE_SQLITE;
    private $sync_enabled = false;
    private $last_sync_time = null;
    private $debug = false;
    
    /**
     * Get the last sync time
     * 
     * @return string|null Last sync time in Y-m-d H:i:s format, or null if never synced
     */
    public function getLastSyncTime() {
        return $this->last_sync_time;
    }
    
    /**
     * Set the last sync time
     * 
     * @param string $time Time in any format that strtotime can parse
     */
    public function setLastSyncTime($time) {
        $this->last_sync_time = date('Y-m-d H:i:s', strtotime($time));
    }
    
    /**
     * Constructor
     * 
     * @param string $db_type The database type to use
     * @param array $config Configuration options
     */
    public function __construct($db_type = DB_TYPE_AUTO, $config = []) {
        $this->db_type = $db_type;
        
        // Default configuration
        $default_config = [
            // PostgreSQL defaults
            'postgresql' => [
                'host' => 'localhost',
                'port' => 5432,
                'dbname' => 'notebook',
                'user' => 'notebook_user',
                'password' => 'notebook_password',
            ],
            
            // SQLite defaults
            'sqlite' => [
                'path' => __DIR__ . '/sqlite/notebook.db',
            ],
            
            // MySQL defaults
            'mysql' => [
                'host' => 'localhost',
                'port' => 3306,
                'dbname' => 'notebook',
                'user' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
            ],
            
            // SQL Server defaults
            'sqlserver' => [
                'host' => 'localhost',
                'port' => 1433,
                'dbname' => 'notebook',
                'user' => 'sa',
                'password' => '',
            ],
            
            // Oracle defaults
            'oracle' => [
                'connection_string' => 'localhost/XE',
                'user' => 'system',
                'password' => 'password',
            ],
            
            // MongoDB defaults
            'mongodb' => [
                'uri' => 'mongodb://localhost:27017',
                'dbname' => 'notebook',
            ],
            
            // Auto mode settings
            'auto_priority' => [
                DB_TYPE_POSTGRESQL,
                DB_TYPE_SQLITE,
                DB_TYPE_MYSQL,
                DB_TYPE_SQLSERVER,
                DB_TYPE_ORACLE,
                DB_TYPE_MONGODB
            ]
        ];
        
        // Merge user config with defaults
        $this->config = array_merge($default_config, $config);
    }
    
    /**
     * Get a database connection
     * 
     * @param string|null $specific_db_type Request a specific database connection type
     * @return mixed Database connection or false on failure
     */
    public function getConnection($specific_db_type = null) {
        // If a specific database connection is requested
        if ($specific_db_type !== null) {
            return $this->getSpecificConnection($specific_db_type);
        }
        
        // For backward compatibility - if already connected, return primary connection
        if ($this->connection !== null) {
            return $this->connection;
        }
        
        // Initialize dual connections if not already done
        if (empty($this->connections)) {
            $this->initializeDualConnections();
        }
        
        // Return primary connection (usually PostgreSQL if available)
        if (isset($this->connections[$this->primary_db_type])) {
            $this->connection = $this->connections[$this->primary_db_type];
            $GLOBALS['active_db_type'] = $this->primary_db_type;
            return $this->connection;
        }
        
        // If primary not available, return secondary
        if (isset($this->connections[$this->secondary_db_type])) {
            $this->connection = $this->connections[$this->secondary_db_type];
            $GLOBALS['active_db_type'] = $this->secondary_db_type;
            return $this->connection;
        }
        
        // If no connections are available, try the old fallback method
        if ($this->db_type !== DB_TYPE_AUTO) {
            $connection = $this->connectToDatabase($this->db_type);
            if ($connection) {
                $this->connection = $connection;
                $this->connections[$this->db_type] = $connection;
                $GLOBALS['active_db_type'] = $this->db_type;
                return $connection;
            }
            
            // Try fallback if primary database type was requested but failed
            if ($this->db_type === $this->primary_db_type) {
                foreach ($this->config['auto_priority'] as $db_type) {
                    if ($db_type === $this->primary_db_type) {
                        continue; // Skip as we already tried it
                    }
                    
                    $connection = $this->connectToDatabase($db_type);
                    if ($connection) {
                        $this->connection = $connection;
                        $this->connections[$db_type] = $connection;
                        $GLOBALS['active_db_type'] = $db_type;
                        return $connection;
                    }
                }
            }
            
            // If all connections failed, return false
            return false;
        }
        
        // If using auto mode, try each database type in priority order
        foreach ($this->config['auto_priority'] as $db_type) {
            $connection = $this->connectToDatabase($db_type);
            if ($connection) {
                $this->connection = $connection;
                $this->connections[$db_type] = $connection;
                $GLOBALS['active_db_type'] = $db_type;
                return $connection;
            }
        }
        
        // If all connections failed, return false
        return false;
    }
    
    /**
     * Connect to a specific database type
     * 
     * @param string $db_type The database type to connect to
     * @return mixed Database connection or false on failure
     */
    private function connectToDatabase($db_type) {
        switch ($db_type) {
            case DB_TYPE_POSTGRESQL:
                return $this->connectToPostgreSQL();
            
            case DB_TYPE_SQLITE:
                return $this->connectToSQLite();
                
            case DB_TYPE_MYSQL:
                return $this->connectToMySQL();
                
            case DB_TYPE_SQLSERVER:
                return $this->connectToSQLServer();
                
            case DB_TYPE_ORACLE:
                return $this->connectToOracle();
                
            case DB_TYPE_MONGODB:
                return $this->connectToMongoDB();
                
            default:
                return false;
        }
    }
    
    /**
     * Connect to PostgreSQL
     * 
     * @return resource|false PostgreSQL connection or false on failure
     */
    private function connectToPostgreSQL() {
        // Check if PostgreSQL extension is available
        if (!function_exists('pg_connect')) {
            error_log("PostgreSQL extension is not installed or enabled");
            return false;
        }
        
        // Build connection string with enhanced error handling
        try {
            // Handle possible missing configuration
            if (!isset($this->config['postgresql'])) {
                error_log("PostgreSQL configuration is missing");
                return false;
            }
            
            // Ensure all required config elements exist
            $required_config = ['host', 'port', 'dbname', 'user', 'password'];
            foreach ($required_config as $item) {
                if (!isset($this->config['postgresql'][$item])) {
                    error_log("PostgreSQL configuration missing required item: {$item}");
                    return false;
                }
            }
            
            // Determine if using Unix socket or TCP/IP
            $using_socket = (strpos($this->config['postgresql']['host'], '/') === 0);
            
            if ($using_socket) {
                // Build connection string for Unix socket
                $connection_string = sprintf(
                    "host=%s dbname=%s user=%s password=%s",
                    $this->config['postgresql']['host'],
                    $this->config['postgresql']['dbname'],
                    $this->config['postgresql']['user'],
                    $this->config['postgresql']['password']
                );
                error_log("Attempting PostgreSQL connection via Unix socket at {$this->config['postgresql']['host']} as {$this->config['postgresql']['user']}");
            } else {
                // Build connection string for TCP/IP
                $connection_string = sprintf(
                    "host=%s port=%d dbname=%s user=%s password=%s",
                    $this->config['postgresql']['host'],
                    $this->config['postgresql']['port'],
                    $this->config['postgresql']['dbname'],
                    $this->config['postgresql']['user'],
                    $this->config['postgresql']['password']
                );
                error_log("Attempting PostgreSQL connection to {$this->config['postgresql']['host']}:{$this->config['postgresql']['port']} as {$this->config['postgresql']['user']}");
            }
            
            // Try to connect with full error reporting
            $conn = @pg_connect($connection_string);
            
            // If that fails, try with sslmode=disable as fallback
            if (!$conn) {
                error_log("First PostgreSQL connection attempt failed: " . error_get_last()['message']);
                error_log("Trying PostgreSQL connection with sslmode=disable");
                $connection_string .= " sslmode=disable";
                $conn = @pg_connect($connection_string);
            }
            
            // Initialize database if connected
            if ($conn) {
                error_log("PostgreSQL connection successful");
                $this->initializePostgreSQL($conn);
            } else {
                error_log("PostgreSQL connection failed: " . error_get_last()['message']);
            }
            
            return $conn;
        } catch (Exception $e) {
            error_log("PostgreSQL connection exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Connect to SQLite
     * 
     * @return PDO|false SQLite connection or false on failure
     */
    private function connectToSQLite() {
        // Check if PDO SQLite extension is available
        if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers())) {
            return false;
        }
        
        // Ensure directory exists
        $db_dir = dirname($this->config['sqlite']['path']);
        if (!file_exists($db_dir)) {
            mkdir($db_dir, 0755, true);
        }
        
        try {
            // Create PDO connection
            $pdo = new PDO("sqlite:" . $this->config['sqlite']['path']);
            
            // Set error mode to exceptions
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Enable foreign keys
            $pdo->exec('PRAGMA foreign_keys = ON');
            
            // Initialize database
            $this->initializeSQLite($pdo);
            
            return $pdo;
        } catch (PDOException $e) {
            error_log('SQLite connection error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Connect to MySQL
     * 
     * @return mysqli|PDO|false MySQL connection or false on failure
     */
    private function connectToMySQL() {
        // Try MySQLi extension first
        if (function_exists('mysqli_connect')) {
            try {
                $conn = @mysqli_connect(
                    $this->config['mysql']['host'],
                    $this->config['mysql']['user'],
                    $this->config['mysql']['password'],
                    $this->config['mysql']['dbname'],
                    $this->config['mysql']['port']
                );
                
                if ($conn) {
                    // Set charset
                    mysqli_set_charset($conn, $this->config['mysql']['charset']);
                    
                    // Initialize database
                    $this->initializeMySQL($conn);
                    
                    return $conn;
                }
            } catch (Exception $e) {
                error_log('MySQLi connection error: ' . $e->getMessage());
            }
        }
        
        // Fall back to PDO MySQL
        if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers())) {
            try {
                $dsn = sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    $this->config['mysql']['host'],
                    $this->config['mysql']['port'],
                    $this->config['mysql']['dbname'],
                    $this->config['mysql']['charset']
                );
                
                $pdo = new PDO(
                    $dsn,
                    $this->config['mysql']['user'],
                    $this->config['mysql']['password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                
                // Initialize database
                $this->initializeMySQLPDO($pdo);
                
                return $pdo;
            } catch (PDOException $e) {
                error_log('PDO MySQL connection error: ' . $e->getMessage());
            }
        }
        
        return false;
    }
    
    /**
     * Connect to SQL Server
     * 
     * @return resource|PDO|false SQL Server connection or false on failure
     */
    private function connectToSQLServer() {
        // Try sqlsrv extension first (Microsoft driver)
        if (function_exists('sqlsrv_connect')) {
            $serverName = sprintf(
                "%s,%d",
                $this->config['sqlserver']['host'],
                $this->config['sqlserver']['port']
            );
            
            $connectionInfo = [
                'Database' => $this->config['sqlserver']['dbname'],
                'UID' => $this->config['sqlserver']['user'],
                'PWD' => $this->config['sqlserver']['password']
            ];
            
            $conn = @sqlsrv_connect($serverName, $connectionInfo);
            
            if ($conn) {
                // Initialize database
                $this->initializeSQLServer($conn);
                
                return $conn;
            }
        }
        
        // Try PDO SQL Server
        if (class_exists('PDO') && in_array('sqlsrv', PDO::getAvailableDrivers())) {
            try {
                $dsn = sprintf(
                    "sqlsrv:Server=%s,%d;Database=%s",
                    $this->config['sqlserver']['host'],
                    $this->config['sqlserver']['port'],
                    $this->config['sqlserver']['dbname']
                );
                
                $pdo = new PDO(
                    $dsn,
                    $this->config['sqlserver']['user'],
                    $this->config['sqlserver']['password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                
                // Initialize database
                $this->initializeSQLServerPDO($pdo);
                
                return $pdo;
            } catch (PDOException $e) {
                error_log('PDO SQL Server connection error: ' . $e->getMessage());
            }
        }
        
        return false;
    }
    
    /**
     * Connect to Oracle
     * 
     * @return resource|PDO|false Oracle connection or false on failure
     */
    private function connectToOracle() {
        // Try oci8 extension
        if (function_exists('oci_connect')) {
            $conn = @oci_connect(
                $this->config['oracle']['user'],
                $this->config['oracle']['password'],
                $this->config['oracle']['connection_string']
            );
            
            if ($conn) {
                // Initialize database
                $this->initializeOracle($conn);
                
                return $conn;
            }
        }
        
        // Try PDO OCI
        if (class_exists('PDO') && in_array('oci', PDO::getAvailableDrivers())) {
            try {
                $dsn = sprintf("oci:dbname=%s", $this->config['oracle']['connection_string']);
                
                $pdo = new PDO(
                    $dsn,
                    $this->config['oracle']['user'],
                    $this->config['oracle']['password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                
                // Initialize database
                $this->initializeOraclePDO($pdo);
                
                return $pdo;
            } catch (PDOException $e) {
                error_log('PDO OCI connection error: ' . $e->getMessage());
            }
        }
        
        return false;
    }
    
    /**
     * Connect to MongoDB
     * 
     * @return MongoDB\Client|false MongoDB connection or false on failure
     */
    private function connectToMongoDB() {
        // Check if MongoDB extension is available
        if (!class_exists('MongoDB\Client')) {
            return false;
        }
        
        try {
            // Create MongoDB client
            $client = new MongoDB\Client($this->config['mongodb']['uri']);
            
            // Test connection by accessing the database
            $client->selectDatabase($this->config['mongodb']['dbname']);
            
            // Initialize MongoDB collections
            $this->initializeMongoDB($client);
            
            return $client;
        } catch (Exception $e) {
            error_log('MongoDB connection error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initialize PostgreSQL database schema
     */
    private function initializePostgreSQL($conn) {
        // Create users table
        pg_query($conn, "
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create notes table
        pg_query($conn, "
            CREATE TABLE IF NOT EXISTS notes (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id),
                content TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create index for faster user note lookups - with permission check and alternative approach
        try {
            // First try directly creating the index
            $result = @pg_query($conn, "
                CREATE INDEX IF NOT EXISTS idx_notes_user_id ON notes(user_id)
            ");
            
            if (!$result) {
                $error = pg_last_error($conn);
                error_log("Note: Could not create index directly: " . $error);
                
                // If we get a permission/ownership error, try an alternative approach
                if (strpos($error, 'must be owner') !== false) {
                    error_log("Trying alternative approach for indexes");
                    
                    // Try with a function-based approach which might have different permission requirements
                    $function_approach = @pg_query($conn, "
                        DO $$
                        BEGIN
                            -- Check if index already exists
                            IF NOT EXISTS (
                                SELECT 1 FROM pg_indexes 
                                WHERE indexname = 'idx_notes_user_id'
                            ) THEN
                                -- Try creating it with CONCURRENTLY to avoid locking
                                BEGIN
                                    EXECUTE 'CREATE INDEX CONCURRENTLY idx_notes_user_id ON notes(user_id)';
                                    RAISE NOTICE 'Index created successfully';
                                EXCEPTION WHEN OTHERS THEN
                                    RAISE NOTICE 'Could not create index: %', SQLERRM;
                                END;
                            END IF;
                        END $$;
                    ");
                    
                    if (!$function_approach) {
                        error_log("Alternative index creation also failed: " . pg_last_error($conn));
                    } else {
                        error_log("Successfully tried alternative index approach");
                    }
                }
            }
        } catch (Exception $e) {
            // Just log the error and continue - indexes are performance optimizations, not critical functionality
            error_log("Exception creating notes index: " . $e->getMessage());
        }
    }
    
    /**
     * Initialize SQLite database schema
     */
    private function initializeSQLite($pdo) {
        // Create users table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create notes table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                content TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        // Create index for faster user note lookups
        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_notes_user_id ON notes(user_id)
        ");
    }
    
    /**
     * Initialize MySQL database schema using MySQLi
     */
    private function initializeMySQL($conn) {
        // Create database if it doesn't exist
        mysqli_query($conn, "
            CREATE DATABASE IF NOT EXISTS `{$this->config['mysql']['dbname']}`
            CHARACTER SET {$this->config['mysql']['charset']} COLLATE {$this->config['mysql']['charset']}_general_ci
        ");
        
        // Select the database
        mysqli_select_db($conn, $this->config['mysql']['dbname']);
        
        // Create users table
        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `username` VARCHAR(50) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `email` VARCHAR(100) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `username` (`username`),
                UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$this->config['mysql']['charset']}
        ");
        
        // Create notes table
        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS `notes` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) UNSIGNED NOT NULL,
                `content` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET={$this->config['mysql']['charset']}
        ");
    }
    
    /**
     * Initialize MySQL database schema using PDO
     */
    private function initializeMySQLPDO($pdo) {
        // Create database if it doesn't exist
        $pdo->exec("
            CREATE DATABASE IF NOT EXISTS `{$this->config['mysql']['dbname']}`
            CHARACTER SET {$this->config['mysql']['charset']} COLLATE {$this->config['mysql']['charset']}_general_ci
        ");
        
        // Create users table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `username` VARCHAR(50) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `email` VARCHAR(100) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `username` (`username`),
                UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$this->config['mysql']['charset']}
        ");
        
        // Create notes table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notes` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) UNSIGNED NOT NULL,
                `content` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET={$this->config['mysql']['charset']}
        ");
    }
    
    /**
     * Initialize SQL Server database schema
     */
    private function initializeSQLServer($conn) {
        // Create database if it doesn't exist
        sqlsrv_query($conn, "
            IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = N'{$this->config['sqlserver']['dbname']}')
            BEGIN
                CREATE DATABASE [{$this->config['sqlserver']['dbname']}]
            END
        ");
        
        // Create users table
        sqlsrv_query($conn, "
            IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='users' AND xtype='U')
            BEGIN
                CREATE TABLE users (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    username NVARCHAR(50) NOT NULL UNIQUE,
                    password NVARCHAR(255) NOT NULL,
                    email NVARCHAR(100) NOT NULL UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            END
        ");
        
        // Create notes table
        sqlsrv_query($conn, "
            IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='notes' AND xtype='U')
            BEGIN
                CREATE TABLE notes (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    user_id INT NOT NULL,
                    content NVARCHAR(MAX),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT FK_notes_users FOREIGN KEY (user_id) REFERENCES users(id)
                )
            END
        ");
        
        // Create index for faster user note lookups
        sqlsrv_query($conn, "
            IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name='idx_notes_user_id' AND object_id = OBJECT_ID('notes'))
            BEGIN
                CREATE INDEX idx_notes_user_id ON notes(user_id)
            END
        ");
    }
    
    /**
     * Initialize SQL Server database schema using PDO
     */
    private function initializeSQLServerPDO($pdo) {
        // Create users table
        $pdo->exec("
            IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='users' AND xtype='U')
            BEGIN
                CREATE TABLE users (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    username NVARCHAR(50) NOT NULL UNIQUE,
                    password NVARCHAR(255) NOT NULL,
                    email NVARCHAR(100) NOT NULL UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            END
        ");
        
        // Create notes table
        $pdo->exec("
            IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='notes' AND xtype='U')
            BEGIN
                CREATE TABLE notes (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    user_id INT NOT NULL,
                    content NVARCHAR(MAX),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT FK_notes_users FOREIGN KEY (user_id) REFERENCES users(id)
                )
            END
        ");
        
        // Create index for faster user note lookups
        $pdo->exec("
            IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name='idx_notes_user_id' AND object_id = OBJECT_ID('notes'))
            BEGIN
                CREATE INDEX idx_notes_user_id ON notes(user_id)
            END
        ");
    }
    
    /**
     * Initialize Oracle database schema
     */
    private function initializeOracle($conn) {
        // Create users table
        $stmt = oci_parse($conn, "
            BEGIN
                EXECUTE IMMEDIATE '
                    CREATE TABLE users (
                        id NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                        username VARCHAR2(50) UNIQUE NOT NULL,
                        password VARCHAR2(255) NOT NULL,
                        email VARCHAR2(100) UNIQUE NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ';
            EXCEPTION
                WHEN OTHERS THEN
                    IF SQLCODE = -955 THEN NULL; -- Table already exists
                    ELSE RAISE;
                    END IF;
            END;
        ");
        oci_execute($stmt);
        
        // Create notes table
        $stmt = oci_parse($conn, "
            BEGIN
                EXECUTE IMMEDIATE '
                    CREATE TABLE notes (
                        id NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                        user_id NUMBER NOT NULL,
                        content CLOB,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        CONSTRAINT fk_notes_users FOREIGN KEY (user_id) REFERENCES users(id)
                    )
                ';
            EXCEPTION
                WHEN OTHERS THEN
                    IF SQLCODE = -955 THEN NULL; -- Table already exists
                    ELSE RAISE;
                    END IF;
            END;
        ");
        oci_execute($stmt);
        
        // Create index for faster user note lookups
        $stmt = oci_parse($conn, "
            BEGIN
                EXECUTE IMMEDIATE '
                    CREATE INDEX idx_notes_user_id ON notes(user_id)
                ';
            EXCEPTION
                WHEN OTHERS THEN
                    IF SQLCODE = -955 THEN NULL; -- Index already exists
                    ELSE RAISE;
                    END IF;
            END;
        ");
        oci_execute($stmt);
    }
    
    /**
     * Initialize Oracle database schema using PDO
     */
    private function initializeOraclePDO($pdo) {
        // Create users table
        try {
            $pdo->exec("
                CREATE TABLE users (
                    id NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    username VARCHAR2(50) UNIQUE NOT NULL,
                    password VARCHAR2(255) NOT NULL,
                    email VARCHAR2(100) UNIQUE NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } catch (PDOException $e) {
            // Ignore error if table already exists
            if (strpos($e->getMessage(), 'ORA-00955') === false) {
                throw $e;
            }
        }
        
        // Create notes table
        try {
            $pdo->exec("
                CREATE TABLE notes (
                    id NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    user_id NUMBER NOT NULL,
                    content CLOB,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_notes_users FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");
        } catch (PDOException $e) {
            // Ignore error if table already exists
            if (strpos($e->getMessage(), 'ORA-00955') === false) {
                throw $e;
            }
        }
        
        // Create index for faster user note lookups
        try {
            $pdo->exec("
                CREATE INDEX idx_notes_user_id ON notes(user_id)
            ");
        } catch (PDOException $e) {
            // Ignore error if index already exists
            if (strpos($e->getMessage(), 'ORA-00955') === false) {
                throw $e;
            }
        }
    }
    
    /**
     * Initialize MongoDB collections
     */
    private function initializeMongoDB($client) {
        $db = $client->selectDatabase($this->config['mongodb']['dbname']);
        
        // Create users collection if it doesn't exist
        if (!in_array('users', $db->listCollectionNames())) {
            $db->createCollection('users');
            
            // Create unique indexes
            $usersCollection = $db->users;
            $usersCollection->createIndex(['username' => 1], ['unique' => true]);
            $usersCollection->createIndex(['email' => 1], ['unique' => true]);
        }
        
        // Create notes collection if it doesn't exist
        if (!in_array('notes', $db->listCollectionNames())) {
            $db->createCollection('notes');
            
            // Create index for faster user note lookups
            $notesCollection = $db->notes;
            $notesCollection->createIndex(['user_id' => 1]);
        }
    }
    
    /**
     * Close the database connection if open
     */
    public function closeConnection() {
        if ($this->connection) {
            switch ($GLOBALS['active_db_type']) {
                case DB_TYPE_POSTGRESQL:
                    if (is_resource($this->connection)) {
                        pg_close($this->connection);
                    }
                    break;
                    
                case DB_TYPE_MYSQL:
                    if (is_object($this->connection) && $this->connection instanceof mysqli) {
                        $this->connection->close();
                    }
                    // PDO connections close automatically
                    break;
                    
                case DB_TYPE_SQLSERVER:
                    if (is_resource($this->connection) && function_exists('sqlsrv_close')) {
                        sqlsrv_close($this->connection);
                    }
                    // PDO connections close automatically
                    break;
                    
                case DB_TYPE_ORACLE:
                    if (is_resource($this->connection) && function_exists('oci_close')) {
                        oci_close($this->connection);
                    }
                    // PDO connections close automatically
                    break;
                    
                // MongoDB and SQLite via PDO close automatically
                default:
                    // Nothing to do
                    break;
            }
            
            $this->connection = null;
        }
    }
    
    /**
     * Execute a SQL query on any database type with parameter binding
     * 
     * @param string $query The SQL query to execute
     * @param array $params The parameters to bind to the query (optional)
     * @param bool $fetchMode Whether to fetch results as an associative array (default: true)
     * @return array|bool Returns query results as an array, true for successful execution with no results, or false on failure
     */
    public function executeQuery($query, $params = [], $fetchMode = true) {
        $result = false;
        $rows = [];
        $error = null;
        
        try {
            // Get current connection
            $conn = $this->getConnection();
            
            if (!$conn) {
                throw new Exception("No active database connection");
            }
            
            // Get current database type
            $db_type = $GLOBALS['active_db_type'];
            
            switch ($db_type) {
                case DB_TYPE_POSTGRESQL:
                    // PostgreSQL query execution
                    if (empty($params)) {
                        $result = pg_query($conn, $query);
                    } else {
                        // PostgreSQL uses $1, $2, etc. as placeholders instead of ?
                        // Convert ? placeholders to $1, $2, etc.
                        $placeholders = array();
                        $pg_query = preg_replace_callback('/\?/', function ($match) use (&$placeholders) {
                            $placeholders[] = '$' . (count($placeholders) + 1);
                            return end($placeholders);
                        }, $query);
                        
                        // Log the conversion for debugging
                        if ($this->debug) {
                            error_log("Original query: $query");
                            error_log("Converted to PostgreSQL format: $pg_query");
                            error_log("Params: " . print_r($params, true));
                        }
                        
                        $result = pg_query_params($conn, $pg_query, $params);
                    }
                    
                    if ($result === false) {
                        $error = pg_last_error($conn);
                        error_log("PostgreSQL query error: $error");
                        throw new Exception($error);
                    }
                    
                    // Fetch results if needed
                    if ($fetchMode && pg_num_rows($result) > 0) {
                        $rows = [];
                        while ($row = pg_fetch_assoc($result)) {
                            $rows[] = $row;
                        }
                    } else {
                        $rows = true; // Success with no results to fetch
                    }
                    break;
                    
                case DB_TYPE_SQLITE:
                    // SQLite PDO query execution
                    try {
                        // Enable error mode for better debugging
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // For direct execution without parameters
                        if (empty($params)) {
                            try {
                                $result = $conn->exec($query);
                                if ($result !== false) {
                                    // Success with no results to fetch for non-SELECT queries
                                    $rows = true;
                                    break;
                                }
                            } catch (PDOException $e) {
                                // Continue to prepared statement if direct execution fails
                            }
                        }
                        
                        // Use prepared statement for all other queries
                        $stmt = $conn->prepare($query);
                        
                        if ($stmt === false) {
                            throw new Exception("Failed to prepare statement: " . implode(" ", $conn->errorInfo()));
                        }
                        
                        // Bind parameters if any
                        if (!empty($params)) {
                            // Check if it's a sequential array (numeric keys) or associative
                            $isSequential = array_keys($params) === range(0, count($params) - 1);
                            
                            if ($isSequential) {
                                // Indexed array, bind using positions
                                foreach ($params as $i => $param) {
                                    // Get appropriate parameter type
                                    $type = PDO::PARAM_STR;
                                    if (is_int($param)) {
                                        $type = PDO::PARAM_INT;
                                    } elseif (is_bool($param)) {
                                        $type = PDO::PARAM_BOOL;
                                    } elseif (is_null($param)) {
                                        $type = PDO::PARAM_NULL;
                                    }
                                    
                                    $stmt->bindValue($i + 1, $param, $type);
                                }
                            } else {
                                // Find all placeholders in the query
                                preg_match_all('/:[a-zA-Z0-9_]+/', $query, $placeholders);
                                $foundPlaceholders = $placeholders[0] ?? [];
                                
                                // Log information for debugging
                                if ($this->config['enable_logging'] ?? false) {
                                    error_log("Found placeholders in query: " . print_r($foundPlaceholders, true));
                                    error_log("Available parameters: " . print_r(array_keys($params), true));
                                }
                                
                                // Associative array, bind using names
                                foreach ($params as $key => $value) {
                                    // Get appropriate parameter type
                                    $type = PDO::PARAM_STR;
                                    if (is_int($value)) {
                                        $type = PDO::PARAM_INT;
                                    } elseif (is_bool($value)) {
                                        $type = PDO::PARAM_BOOL;
                                    } elseif (is_null($value)) {
                                        $type = PDO::PARAM_NULL;
                                    }
                                    
                                    // Make sure key has colon prefix for binding
                                    $paramName = (strpos($key, ':') === 0) ? $key : ":$key";
                                    
                                    // Only bind if the parameter exists in the query
                                    if (in_array($paramName, $foundPlaceholders) || empty($foundPlaceholders)) {
                                        try {
                                            $stmt->bindValue($paramName, $value, $type);
                                            
                                            if ($this->config['enable_logging'] ?? false) {
                                                error_log("Bound parameter $paramName = " . (is_string($value) ? $value : gettype($value)));
                                            }
                                        } catch (PDOException $e) {
                                            // Log binding errors
                                            if ($this->config['enable_logging'] ?? false) {
                                                error_log("Failed to bind parameter $paramName: " . $e->getMessage());
                                            }
                                            throw $e;
                                        }
                                    } else if ($this->config['enable_logging'] ?? false) {
                                        error_log("Parameter $paramName not found in query, skipping");
                                    }
                                }
                            }
                        }
                        
                        // Execute the prepared statement
                        try {
                            $result = $stmt->execute();
                        } catch (PDOException $e) {
                            // Log execute errors
                            if ($this->config['enable_logging'] ?? false) {
                                error_log("Execute failed: " . $e->getMessage());
                            }
                            throw $e;
                        }
                        
                        if ($result === false) {
                            $errorInfo = $stmt->errorInfo();
                            $errorMsg = isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error';
                            throw new Exception("Failed to execute query: $errorMsg");
                        }
                    } catch (PDOException $e) {
                        // Capture and log detailed error information
                        $errorMessage = $e->getMessage();
                        
                        // Check for SQLITE_CONSTRAINT violation
                        if (strpos($errorMessage, 'UNIQUE constraint failed') !== false) {
                            $errorMessage = "Duplicate entry: " . $errorMessage;
                        }
                        
                        // Log the query and parameters for debugging
                        if ($this->config['enable_logging'] ?? false) {
                            error_log("SQLite PDO Exception: " . $errorMessage);
                            error_log("Query: $query");
                            error_log("Params: " . print_r($params, true));
                            error_log("Stack trace: " . $e->getTraceAsString());
                        }
                        
                        // Store the error in a global variable for the test script to access
                        $GLOBALS['last_db_error'] = $errorMessage;
                        
                        // Re-throw the exception
                        throw new Exception("Database error: " . $errorMessage, 0, $e);
                    }
                    
                    // Fetch results if needed
                    if ($fetchMode && $stmt->columnCount() > 0) {
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $rows = true; // Success with no results to fetch
                    }
                    break;
                    
                case DB_TYPE_MYSQL:
                    // MySQL query execution
                    if (isset($this->config['mysql_use_pdo']) && $this->config['mysql_use_pdo']) {
                        // MySQL PDO
                        $stmt = $conn->prepare($query);
                        
                        // Bind parameters if any
                        if (!empty($params)) {
                            if (array_keys($params) === range(0, count($params) - 1)) {
                                // Indexed array, bind using positions
                                foreach ($params as $i => $param) {
                                    $stmt->bindValue($i + 1, $param);
                                }
                            } else {
                                // Associative array, bind using names
                                foreach ($params as $key => $value) {
                                    $stmt->bindValue(":$key", $value);
                                }
                            }
                        }
                        
                        $result = $stmt->execute();
                        
                        if ($result === false) {
                            throw new Exception($stmt->errorInfo()[2]);
                        }
                        
                        // Fetch results if needed
                        if ($fetchMode && $stmt->columnCount() > 0) {
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            $rows = true; // Success with no results to fetch
                        }
                    } else {
                        // MySQLi
                        if (!empty($params)) {
                            // Prepare and bind parameters
                            $stmt = mysqli_prepare($conn, $query);
                            
                            if ($stmt === false) {
                                throw new Exception(mysqli_error($conn));
                            }
                            
                            // Create types string and values array for bind_param
                            $types = '';
                            $bind_params = [];
                            
                            foreach ($params as $param) {
                                if (is_int($param)) {
                                    $types .= 'i';
                                } elseif (is_float($param)) {
                                    $types .= 'd';
                                } elseif (is_string($param)) {
                                    $types .= 's';
                                } else {
                                    $types .= 'b'; // BLOB
                                }
                                $bind_params[] = $param;
                            }
                            
                            // Only bind if we have parameters
                            if (!empty($bind_params)) {
                                $bind_names[] = $types;
                                
                                for ($i = 0; $i < count($bind_params); $i++) {
                                    $bind_name = 'bind' . $i;
                                    $$bind_name = $bind_params[$i];
                                    $bind_names[] = &$$bind_name;
                                }
                                
                                call_user_func_array([$stmt, 'bind_param'], $bind_names);
                            }
                            
                            $result = mysqli_stmt_execute($stmt);
                            
                            if ($result === false) {
                                throw new Exception(mysqli_stmt_error($stmt));
                            }
                            
                            // Fetch results if needed
                            if ($fetchMode) {
                                $meta = mysqli_stmt_result_metadata($stmt);
                                
                                if ($meta) {
                                    $rows = [];
                                    $row = [];
                                    $params = [];
                                    
                                    while ($field = mysqli_fetch_field($meta)) {
                                        $row[$field->name] = null;
                                        $params[] = &$row[$field->name];
                                    }
                                    
                                    call_user_func_array([$stmt, 'bind_result'], $params);
                                    
                                    while (mysqli_stmt_fetch($stmt)) {
                                        $rows[] = array_map(function($val) { return $val; }, $row);
                                    }
                                    
                                    mysqli_free_result($meta);
                                } else {
                                    $rows = true; // Success with no results to fetch
                                }
                            } else {
                                $rows = true; // Success with no results to fetch
                            }
                            
                            mysqli_stmt_close($stmt);
                        } else {
                            // Simple query without parameters
                            $result = mysqli_query($conn, $query);
                            
                            if ($result === false) {
                                throw new Exception(mysqli_error($conn));
                            }
                            
                            // Fetch results if needed
                            if ($fetchMode && mysqli_num_rows($result) > 0) {
                                $rows = [];
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $rows[] = $row;
                                }
                                mysqli_free_result($result);
                            } else {
                                $rows = true; // Success with no results to fetch
                            }
                        }
                    }
                    break;
                    
                case DB_TYPE_SQLSERVER:
                    // SQL Server query execution
                    if (is_object($conn) && $conn instanceof PDO) {
                        // SQL Server PDO
                        $stmt = $conn->prepare($query);
                        
                        // Bind parameters if any
                        if (!empty($params)) {
                            if (array_keys($params) === range(0, count($params) - 1)) {
                                // Indexed array, bind using positions
                                foreach ($params as $i => $param) {
                                    $stmt->bindValue($i + 1, $param);
                                }
                            } else {
                                // Associative array, bind using names
                                foreach ($params as $key => $value) {
                                    $stmt->bindValue(":$key", $value);
                                }
                            }
                        }
                        
                        $result = $stmt->execute();
                        
                        if ($result === false) {
                            throw new Exception($stmt->errorInfo()[2]);
                        }
                        
                        // Fetch results if needed
                        if ($fetchMode && $stmt->columnCount() > 0) {
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            $rows = true; // Success with no results to fetch
                        }
                    } else {
                        // SQLSRV functions
                        if (!empty($params)) {
                            // Prepare and bind parameters
                            $stmt = sqlsrv_prepare($conn, $query, $params);
                            
                            if ($stmt === false) {
                                $errors = sqlsrv_errors();
                                throw new Exception($errors[0]['message']);
                            }
                            
                            $result = sqlsrv_execute($stmt);
                            
                            if ($result === false) {
                                $errors = sqlsrv_errors();
                                throw new Exception($errors[0]['message']);
                            }
                            
                            // Fetch results if needed
                            if ($fetchMode) {
                                $rows = [];
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    $rows[] = $row;
                                }
                            } else {
                                $rows = true; // Success with no results to fetch
                            }
                            
                            sqlsrv_free_stmt($stmt);
                        } else {
                            // Simple query without parameters
                            $result = sqlsrv_query($conn, $query);
                            
                            if ($result === false) {
                                $errors = sqlsrv_errors();
                                throw new Exception($errors[0]['message']);
                            }
                            
                            // Fetch results if needed
                            if ($fetchMode) {
                                $rows = [];
                                while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                                    $rows[] = $row;
                                }
                                sqlsrv_free_stmt($result);
                            } else {
                                $rows = true; // Success with no results to fetch
                            }
                        }
                    }
                    break;
                    
                case DB_TYPE_ORACLE:
                    // Oracle query execution
                    if (is_object($conn) && $conn instanceof PDO) {
                        // Oracle PDO
                        $stmt = $conn->prepare($query);
                        
                        // Bind parameters if any
                        if (!empty($params)) {
                            if (array_keys($params) === range(0, count($params) - 1)) {
                                // Indexed array, bind using positions
                                foreach ($params as $i => $param) {
                                    $stmt->bindValue($i + 1, $param);
                                }
                            } else {
                                // Associative array, bind using names
                                foreach ($params as $key => $value) {
                                    $stmt->bindValue(":$key", $value);
                                }
                            }
                        }
                        
                        $result = $stmt->execute();
                        
                        if ($result === false) {
                            throw new Exception($stmt->errorInfo()[2]);
                        }
                        
                        // Fetch results if needed
                        if ($fetchMode && $stmt->columnCount() > 0) {
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            $rows = true; // Success with no results to fetch
                        }
                    } else {
                        // OCI functions
                        // Prepare the statement
                        $stmt = oci_parse($conn, $query);
                        
                        if ($stmt === false) {
                            $error = oci_error($conn);
                            throw new Exception($error['message']);
                        }
                        
                        // Bind parameters if any
                        if (!empty($params)) {
                            if (array_keys($params) === range(0, count($params) - 1)) {
                                // Indexed array, we need to bind using positional params
                                foreach ($params as $i => $param) {
                                    oci_bind_by_name($stmt, ":p$i", $params[$i]);
                                }
                            } else {
                                // Associative array, bind using names
                                foreach ($params as $key => $value) {
                                    oci_bind_by_name($stmt, ":$key", $params[$key]);
                                }
                            }
                        }
                        
                        // Execute the statement
                        if ($fetchMode) {
                            $result = oci_execute($stmt, OCI_DEFAULT);
                        } else {
                            $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
                        }
                        
                        if ($result === false) {
                            $error = oci_error($stmt);
                            throw new Exception($error['message']);
                        }
                        
                        // Fetch results if needed
                        if ($fetchMode) {
                            $rows = [];
                            while ($row = oci_fetch_assoc($stmt)) {
                                $rows[] = $row;
                            }
                        } else {
                            $rows = true; // Success with no results to fetch
                        }
                        
                        oci_free_statement($stmt);
                    }
                    break;
                    
                case DB_TYPE_MONGODB:
                    // MongoDB doesn't use SQL so we handle it differently
                    throw new Exception("MongoDB queries should use the MongoDB PHP Library directly.");
                    break;
                    
                default:
                    throw new Exception("Unsupported database type: $db_type");
            }
            
            return $rows;
            
        } catch (Exception $e) {
            // Log the error if logging is enabled
            if (isset($this->config['enable_logging']) && $this->config['enable_logging']) {
                error_log("Database query error: " . $e->getMessage());
                error_log("Query: $query");
                error_log("Params: " . print_r($params, true));
            }
            
            // Return false to indicate failure
            return false;
        }
    }
    
    /**
     * Get the current database type being used
     * 
     * @return string|null The current database type or null if not connected
     */
    public function getCurrentDbType() {
        return isset($GLOBALS['active_db_type']) ? $GLOBALS['active_db_type'] : null;
    }
    
    /**
     * Initialize connections to both primary and secondary databases
     */
    public function initializeDualConnections() {
        // Connect to primary database (PostgreSQL)
        $primary_connection = $this->connectToDatabase($this->primary_db_type);
        if ($primary_connection) {
            $this->connections[$this->primary_db_type] = $primary_connection;
            // Set as default connection for backward compatibility
            if ($this->connection === null) {
                $this->connection = $primary_connection;
                $GLOBALS['active_db_type'] = $this->primary_db_type;
            }
        }
        
        // Connect to secondary database (SQLite)
        $secondary_connection = $this->connectToDatabase($this->secondary_db_type);
        if ($secondary_connection) {
            $this->connections[$this->secondary_db_type] = $secondary_connection;
            // If primary failed, use secondary as default
            if ($this->connection === null) {
                $this->connection = $secondary_connection;
                $GLOBALS['active_db_type'] = $this->secondary_db_type;
            }
        }
        
        return !empty($this->connections);
    }
    
    /**
     * Get a specific database connection by type
     *
     * @param string $db_type The database type to get
     * @return mixed Database connection or false if not connected
     */
    public function getSpecificConnection($db_type) {
        // If already connected to that database type, return it
        if (isset($this->connections[$db_type])) {
            // Test if the connection is still valid
            $conn = $this->connections[$db_type];
            $valid = false;
            
            try {
                if ($db_type === DB_TYPE_POSTGRESQL && is_resource($conn)) {
                    // Test PostgreSQL connection
                    $result = @pg_query($conn, "SELECT 1");
                    $valid = ($result !== false);
                    if ($result) pg_free_result($result);
                } else if ($db_type === DB_TYPE_SQLITE && $conn instanceof PDO) {
                    // Test SQLite connection
                    $result = $conn->query("SELECT 1");
                    $valid = ($result !== false);
                    if ($result) $result->closeCursor();
                } else {
                    // Assume other connection types are valid if set
                    $valid = true;
                }
            } catch (Exception $e) {
                $valid = false;
                error_log("Connection test failed for $db_type: " . $e->getMessage());
            }
            
            if ($valid) {
                return $conn;
            } else {
                // Connection is invalid, remove it and try to reconnect
                unset($this->connections[$db_type]);
                error_log("Invalid connection detected for $db_type, attempting to reconnect");
            }
        }
        
        // Try to connect to the requested database type
        $connection = $this->connectToDatabase($db_type);
        if ($connection) {
            $this->connections[$db_type] = $connection;
            return $connection;
        }
        
        return false;
    }
    
    /**
     * Check if both PostgreSQL and SQLite connections are available
     *
     * @return bool True if dual connections are available
     */
    public function hasDualConnections() {
        return isset($this->connections[$this->primary_db_type]) && 
               isset($this->connections[$this->secondary_db_type]);
    }
    
    /**
     * Enable or disable data synchronization between databases
     *
     * @param bool $enabled Whether to enable synchronization
     * @return bool Success status
     */
    public function enableSync($enabled = true) {
        $this->sync_enabled = $enabled;
        
        // Make sure we have both connections available if enabling sync
        if ($enabled && !$this->hasDualConnections()) {
            $this->initializeDualConnections();
        }
        
        return $this->sync_enabled;
    }
    
    /**
     * Check if data synchronization is enabled
     *
     * @return bool True if sync is enabled
     */
    public function isSyncEnabled() {
        return $this->sync_enabled;
    }
    
    /**
     * Check if PostgreSQL is connected
     *
     * @return bool True if connected to PostgreSQL
     */
    public function isPgConnected() {
        return $this->getSpecificConnection(DB_TYPE_POSTGRESQL) !== false;
    }
    
    /**
     * Check if SQLite is connected
     *
     * @return bool True if connected to SQLite
     */
    public function isSqliteConnected() {
        return $this->getSpecificConnection(DB_TYPE_SQLITE) !== false;
    }
    
    /**
     * Execute a query on both primary and secondary databases
     *
     * @param string $query SQL query to execute
     * @param array $params Parameters for the query
     * @param bool $fetchMode Whether to fetch results
     * @return array|bool Results from primary database or false on failure
     */
    public function executeSyncQuery($query, $params = [], $fetchMode = true) {
        // Initialize connections if needed
        if (!$this->hasDualConnections()) {
            $this->initializeDualConnections();
        }
        
        // Store the current active database type
        $original_db_type = $this->getCurrentDbType();
        $primary_result = false;
        $secondary_result = false;
        
        // First execute on primary (typically PostgreSQL)
        if (isset($this->connections[$this->primary_db_type])) {
            // Set active database to primary
            $GLOBALS['active_db_type'] = $this->primary_db_type;
            $primary_result = $this->executeQuery($query, $params, $fetchMode);
        }
        
        // Then execute on secondary (typically SQLite)
        if (isset($this->connections[$this->secondary_db_type])) {
            // Set active database to secondary
            $GLOBALS['active_db_type'] = $this->secondary_db_type;
            $secondary_result = $this->executeQuery($query, $params, $fetchMode);
        }
        
        // Restore original active database type
        $GLOBALS['active_db_type'] = $original_db_type;
        
        // Return the primary result (or secondary if primary failed)
        return $primary_result !== false ? $primary_result : $secondary_result;
    }
    
    /**
     * Transfer all data from one database to another
     *
     * @param string $source_db Source database type
     * @param string $target_db Target database type
     * @param array $tables Tables to transfer (empty for all)
     * @return bool Success status
     */
    public function syncDatabases($source_db = null, $target_db = null, $tables = []) {
        try {
            // If source and target not specified, use primary and secondary
            if ($source_db === null) {
                $source_db = $this->primary_db_type;
            }
            if ($target_db === null) {
                $target_db = $this->secondary_db_type;
            }
            
            // Make sure we have connections to both databases
            $source_conn = $this->getSpecificConnection($source_db);
            $target_conn = $this->getSpecificConnection($target_db);
            
            if (!$source_conn) {
                error_log("Sync failed: Source database $source_db not connected");
                return false;
            }
            
            if (!$target_conn) {
                error_log("Sync failed: Target database $target_db not connected");
                return false;
            }
            
            // If no tables specified, get all tables from source
            if (empty($tables)) {
                $tables = $this->getTableList($source_db);
                if (empty($tables)) {
                    error_log("Sync failed: No tables found in source database $source_db");
                    return false;
                }
            }
            
            $sync_success = true;
            
            // For each table
            foreach ($tables as $table) {
                try {
                    // Get table structure
                    $structure = $this->getTableStructure($source_db, $table);
                    if (empty($structure)) {
                        error_log("Sync warning: Empty structure for table $table in $source_db");
                        continue;
                    }
                    
                    // Create table in target if it doesn't exist
                    $table_created = $this->createTableInTarget($target_db, $table, $structure);
                    if (!$table_created) {
                        error_log("Sync warning: Could not create table $table in $target_db");
                        $sync_success = false;
                        continue;
                    }
                    
                    // Transfer data
                    $data_transferred = $this->transferTableData($source_db, $target_db, $table);
                    if (!$data_transferred) {
                        error_log("Sync warning: Failed to transfer data for table $table from $source_db to $target_db");
                        $sync_success = false;
                    }
                } catch (Exception $e) {
                    error_log("Sync error on table $table: " . $e->getMessage());
                    $sync_success = false;
                }
            }
            
            // Only update the sync time if the sync was at least partially successful
            if ($sync_success) {
                $this->setLastSyncTime('now');
            }
            
            return $sync_success;
        } catch (Exception $e) {
            error_log("Sync failed with exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get a list of tables from the specified database
     *
     * @param string $db_type Database type
     * @return array List of table names
     */
    private function getTableList($db_type) {
        $tables = [];
        $original_db_type = $this->getCurrentDbType();
        
        // Switch to the specified database
        $GLOBALS['active_db_type'] = $db_type;
        
        try {
            if ($db_type === DB_TYPE_POSTGRESQL) {
                $result = $this->executeQuery("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
                foreach ($result as $row) {
                    $tables[] = $row['table_name'];
                }
            } else if ($db_type === DB_TYPE_SQLITE) {
                $result = $this->executeQuery("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                foreach ($result as $row) {
                    $tables[] = $row['name'];
                }
            }
        } catch (Exception $e) {
            // Log error
        }
        
        // Restore original database type
        $GLOBALS['active_db_type'] = $original_db_type;
        
        return $tables;
    }
    
    /**
     * Get table structure from source database
     *
     * @param string $db_type Database type
     * @param string $table Table name
     * @return array Table structure
     */
    private function getTableStructure($db_type, $table) {
        $structure = [];
        $original_db_type = $this->getCurrentDbType();
        
        // Switch to the specified database
        $GLOBALS['active_db_type'] = $db_type;
        
        try {
            if ($db_type === DB_TYPE_POSTGRESQL) {
                $result = $this->executeQuery(
                    "SELECT column_name, data_type, is_nullable, column_default " .
                    "FROM information_schema.columns " .
                    "WHERE table_name = :table",
                    ['table' => $table]
                );
                
                foreach ($result as $row) {
                    $structure[] = $row;
                }
            } else if ($db_type === DB_TYPE_SQLITE) {
                $result = $this->executeQuery("PRAGMA table_info(" . $table . ")");
                foreach ($result as $row) {
                    $structure[] = [
                        'column_name' => $row['name'],
                        'data_type' => $row['type'],
                        'is_nullable' => $row['notnull'] ? 'NO' : 'YES',
                        'column_default' => $row['dflt_value']
                    ];
                }
            }
        } catch (Exception $e) {
            // Log error
        }
        
        // Restore original database type
        $GLOBALS['active_db_type'] = $original_db_type;
        
        return $structure;
    }
    
    /**
     * Create table in target database based on source structure
     *
     * @param string $db_type Target database type
     * @param string $table Table name
     * @param array $structure Table structure
     * @return bool Success status
     */
    private function createTableInTarget($db_type, $table, $structure) {
        $original_db_type = $this->getCurrentDbType();
        
        // Switch to the target database
        $GLOBALS['active_db_type'] = $db_type;
        
        try {
            // Check if table already exists
            if ($db_type === DB_TYPE_POSTGRESQL) {
                $exists = $this->executeQuery(
                    "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name = :table)",
                    ['table' => $table]
                );
                if (!empty($exists) && $exists[0]['exists'] === 't') {
                    // Table already exists
                    $GLOBALS['active_db_type'] = $original_db_type;
                    return true;
                }
            } else if ($db_type === DB_TYPE_SQLITE) {
                $exists = $this->executeQuery(
                    "SELECT name FROM sqlite_master WHERE type='table' AND name = :table",
                    ['table' => $table]
                );
                if (!empty($exists)) {
                    // Table already exists
                    $GLOBALS['active_db_type'] = $original_db_type;
                    return true;
                }
            }
            
            // Build create table SQL
            $sql = "CREATE TABLE IF NOT EXISTS $table (";
            $columns = [];
            
            foreach ($structure as $column) {
                $col_sql = $column['column_name'] . ' ';
                
                // Map data types between PostgreSQL and SQLite
                if ($db_type === DB_TYPE_SQLITE) {
                    // Map PostgreSQL types to SQLite
                    switch (strtolower($column['data_type'])) {
                        case 'integer':
                        case 'smallint':
                        case 'bigint':
                            $col_sql .= 'INTEGER';
                            break;
                        case 'character varying':
                        case 'varchar':
                        case 'text':
                            $col_sql .= 'TEXT';
                            break;
                        case 'timestamp':
                        case 'date':
                            $col_sql .= 'TEXT';
                            break;
                        case 'boolean':
                            $col_sql .= 'INTEGER';
                            break;
                        case 'numeric':
                        case 'real':
                        case 'double precision':
                            $col_sql .= 'REAL';
                            break;
                        default:
                            $col_sql .= 'TEXT';
                    }
                } else if ($db_type === DB_TYPE_POSTGRESQL) {
                    // Map SQLite types to PostgreSQL
                    switch (strtolower($column['data_type'])) {
                        case 'integer':
                            $col_sql .= 'INTEGER';
                            break;
                        case 'text':
                            $col_sql .= 'TEXT';
                            break;
                        case 'real':
                            $col_sql .= 'DOUBLE PRECISION';
                            break;
                        default:
                            $col_sql .= 'TEXT';
                    }
                }
                
                // Nullable
                if ($column['is_nullable'] === 'NO') {
                    $col_sql .= ' NOT NULL';
                }
                
                // Default value
                if ($column['column_default'] !== null) {
                    $col_sql .= ' DEFAULT ' . $column['column_default'];
                }
                
                $columns[] = $col_sql;
            }
            
            $sql .= implode(', ', $columns) . ')';
            
            // Execute create table
            $this->executeQuery($sql, [], false);
            
        } catch (Exception $e) {
            // Log error
            $GLOBALS['active_db_type'] = $original_db_type;
            return false;
        }
        
        // Restore original database type
        $GLOBALS['active_db_type'] = $original_db_type;
        return true;
    }
    
    /**
     * Transfer data from source table to target table
     *
     * @param string $source_db Source database type
     * @param string $target_db Target database type
     * @param string $table Table name
     * @return bool Success status
     */
    private function transferTableData($source_db, $target_db, $table) {
        $original_db_type = $this->getCurrentDbType();
        
        try {
            // Get data from source
            $GLOBALS['active_db_type'] = $source_db;
            $data = $this->executeQuery("SELECT * FROM $table");
            
            if (empty($data)) {
                // No data to transfer
                $GLOBALS['active_db_type'] = $original_db_type;
                return true;
            }
            
            // Switch to target database
            $GLOBALS['active_db_type'] = $target_db;
            
            // Clear existing data in target
            $this->executeQuery("DELETE FROM $table", [], false);
            
            // Get column names from first row
            $columns = array_keys($data[0]);
            
            // Insert data in batches
            $batch_size = 100;
            $batches = array_chunk($data, $batch_size);
            
            foreach ($batches as $batch) {
                // Prepare batch insert SQL
                $placeholders = [];
                $params = [];
                
                foreach ($batch as $i => $row) {
                    $row_placeholders = [];
                    
                    foreach ($columns as $col) {
                        $param_name = "param_{$i}_{$col}";
                        $row_placeholders[] = ":$param_name";
                        $params[$param_name] = $row[$col];
                    }
                    
                    $placeholders[] = '(' . implode(', ', $row_placeholders) . ')';
                }
                
                $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES " . 
                       implode(', ', $placeholders);
                
                // Execute batch insert
                $this->executeQuery($sql, $params, false);
            }
            
        } catch (Exception $e) {
            // Log error
            $GLOBALS['active_db_type'] = $original_db_type;
            return false;
        }
        
        // Restore original database type
        $GLOBALS['active_db_type'] = $original_db_type;
        return true;
    }

    /**
     * Get a configuration value
     * 
     * @param string $key Configuration key to retrieve
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value or default
     */
    public function getConfig($key, $default = null) {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
    
    /**
     * Set a configuration value
     * 
     * @param string $key Configuration key to set
     * @param mixed $value Value to set
     * @return void
     */
    public function setConfig($key, $value) {
        $this->config[$key] = $value;
        
        // If we're changing connection parameters, reset connections so they'll be re-established
        if (strpos($key, 'pg_') === 0 || strpos($key, 'sqlite_') === 0) {
            // Remove specific connection that this config affects
            if (strpos($key, 'pg_') === 0 && isset($this->connections[DB_TYPE_POSTGRESQL])) {
                unset($this->connections[DB_TYPE_POSTGRESQL]);
            } else if (strpos($key, 'sqlite_') === 0 && isset($this->connections[DB_TYPE_SQLITE])) {
                unset($this->connections[DB_TYPE_SQLITE]);
            }
        }
    }
    
    /**
     * Get the fallback priority order
     * 
     * @return array Database types in priority order
     */
    public function getFallbackOrder() {
        return $this->config['auto_priority'] ?? [
            DB_TYPE_POSTGRESQL,
            DB_TYPE_SQLITE,
            DB_TYPE_MYSQL,
            DB_TYPE_SQLSERVER,
            DB_TYPE_ORACLE,
            DB_TYPE_MONGODB
        ];
    }
    
    /**
     * Enable or disable SQL debug mode
     * 
     * @param bool $enable Whether to enable debugging
     * @return void
     */
    public function enableDebug($enable = true) {
        $this->config['enable_logging'] = $enable;
        
        // If we have an active connection and it's SQLite or another PDO connection, set attributes
        if ($this->connection) {
            $db_type = $this->getCurrentDbType();
            if (in_array($db_type, [DB_TYPE_SQLITE, DB_TYPE_MYSQL, DB_TYPE_SQLSERVER, DB_TYPE_ORACLE]) && 
                $this->connection instanceof PDO) {
                if ($enable) {
                    $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                } else {
                    $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
                }
            }
        }
    }
    
    /**
     * Destructor to ensure connections are closed
     */
    public function __destruct() {
        $this->closeConnection();
    }
}

// Function moved to db.php

// Function moved to db.php

// Function moved to db.php
?>
