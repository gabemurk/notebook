<?php
/**
 * Ensure Required Tables Exist
 * This script ensures all necessary tables exist in both PostgreSQL and SQLite databases
 */

// Include database connection
require_once __DIR__ . '/db.php';

/**
 * Create tables if they don't exist
 */
function ensure_tables_exist() {
    // Get database manager
    $dbManager = get_db_manager();
    
    // Log the operation
    error_log('Ensuring required tables exist in databases');
    
    try {
        // NOTE: PostgreSQL uses SERIAL for auto-increment, while SQLite uses INTEGER PRIMARY KEY AUTOINCREMENT
        
        // Create tables based on database type
        if (db_has_dual_connections()) {
            // POSTGRESQL TABLES
            
            // Check and create notes table in PostgreSQL
            $pgQuery = "
            CREATE TABLE IF NOT EXISTS notes (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            // Execute the query on PostgreSQL
            $dbManager->executeQuery($pgQuery, [], false, DB_TYPE_POSTGRESQL);
            
            // Check and create users table in PostgreSQL
            $pgUsersQuery = "
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            $dbManager->executeQuery($pgUsersQuery, [], false, DB_TYPE_POSTGRESQL);
            
            // SQLITE TABLES
            
            // Check and create notes table in SQLite
            $sqliteQuery = "
            CREATE TABLE IF NOT EXISTS notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                content TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            // Execute the query on SQLite
            $dbManager->executeQuery($sqliteQuery, [], false, DB_TYPE_SQLITE);
            
            // SQLite users table
            $sqliteUsersQuery = "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                email TEXT UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            $dbManager->executeQuery($sqliteUsersQuery, [], false, DB_TYPE_SQLITE);
        } else {
            // If only one database is available, create tables for the current database type
            $dbType = get_db_type();
            
            if ($dbType == DB_TYPE_POSTGRESQL) {
                // PostgreSQL tables
                $notesQuery = "
                CREATE TABLE IF NOT EXISTS notes (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    content TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                $usersQuery = "
                CREATE TABLE IF NOT EXISTS users (
                    id SERIAL PRIMARY KEY,
                    username VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                $dbManager->executeQuery($notesQuery, [], false);
                $dbManager->executeQuery($usersQuery, [], false);
            } else {
                // SQLite or other database tables
                $notesQuery = "
                CREATE TABLE IF NOT EXISTS notes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    title TEXT NOT NULL,
                    content TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                $usersQuery = "
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    password TEXT NOT NULL,
                    email TEXT UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                $dbManager->executeQuery($notesQuery, [], false);
                $dbManager->executeQuery($usersQuery, [], false);
            }
        }
        
        // Create a default user if there are none
        ensure_default_user_exists($dbManager);
        
        return true;
    } catch (Exception $e) {
        error_log("Error ensuring tables exist: " . $e->getMessage());
        return false;
    }
}

/**
 * Ensure a default user exists for demo purposes
 */
function ensure_default_user_exists($dbManager) {
    try {
        // Check if any users exist in the database
        $query = "SELECT COUNT(*) as user_count FROM users";
        $result = $dbManager->executeQuery($query, [], true);
        
        // If no users exist, create a default one
        if (empty($result) || $result[0]['user_count'] == 0) {
            $username = 'demo';
            $password = password_hash('demo123', PASSWORD_DEFAULT);
            $email = 'demo@example.com';
            
            // Insert user with sync to both databases
            $query = "INSERT INTO users (username, password, email) VALUES (?, ?, ?)";
            $params = [$username, $password, $email];
            
            db_execute_sync($query, $params);
            
            error_log("Default user created: username=demo, password=demo123");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error ensuring default user: " . $e->getMessage());
        return false;
    }
}

// Run the function when this file is included
$tablesCreated = ensure_tables_exist();
?>
