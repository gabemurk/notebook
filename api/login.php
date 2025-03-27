<?php
session_start(); // Start the session at the beginning to avoid potential issues
include '../database/index.php'; // Include the database package

// Set content type to JSON for consistent response handling
header('Content-Type: application/json');

// Default response
$response = [
    'success' => false,
    'message' => 'An error occurred'
];

// Get user data from AJAX request
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $response['message'] = 'Username and password are required';
    echo json_encode($response);
    exit;
}

try {
    // Use our database-agnostic functions for database operations
    // This will automatically use PostgreSQL if available, or SQLite as fallback
    
    // Fetch the user using prepared statement for security
    $users = db_execute(
        "SELECT * FROM users WHERE username = :username", 
        ['username' => $username]
    );
    
    if (!empty($users)) {
        $user = $users[0]; // First matching user
        
        // Verify the password
        if (password_verify($password, $user['password'])) {
            // Store user ID in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Success response
            $response['success'] = true;
            $response['message'] = 'Login successful';
        } else {
            $response['message'] = 'Invalid username or password';
        }
    } else {
        $response['message'] = 'Invalid username or password';
    }
} catch (Exception $e) {
    // Log the error (in a production environment)
    error_log('Login error: ' . $e->getMessage());
    $response['message'] = 'Database error occurred';
}

// Return JSON response
echo json_encode($response);
?>