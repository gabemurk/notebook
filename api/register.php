<?php
session_start(); // Start the session at the beginning
include '../database/index.php'; // Include the database package

// Set content type to JSON for consistent response handling
header('Content-Type: application/json');

// Default response
$response = [
    'success' => false,
    'message' => 'An error occurred'
];

// Get user data from AJAX request with validation
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Basic validation
if (empty($username) || empty($password) || empty($email)) {
    $response['message'] = 'All fields are required';
    echo json_encode($response);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email format';
    echo json_encode($response);
    exit;
}

// Hash the password for security
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // First, check if username or email already exists using our database-agnostic function
    $existing_user = db_execute(
        "SELECT * FROM users WHERE username = :username OR email = :email",
        ['username' => $username, 'email' => $email]
    );
    
    if (!empty($existing_user)) {
        $response['message'] = 'Username or email already exists';
        echo json_encode($response);
        exit;
    }
    
    // Insert the new user in both PostgreSQL and SQLite simultaneously
    $result = db_execute_sync(
        "INSERT INTO users (username, password, email) VALUES (:username, :password, :email)",
        ['username' => $username, 'password' => $hashed_password, 'email' => $email],
        false
    );
    
    if ($result) {
        // Get the newly created user's ID
        $new_user = db_execute(
            "SELECT id FROM users WHERE username = :username",
            ['username' => $username]
        );
        
        if (!empty($new_user)) {
            // Login the user automatically
            $_SESSION['user_id'] = $new_user[0]['id'];
            $_SESSION['username'] = $username;
            
            $response['success'] = true;
            $response['message'] = 'User registered and logged in successfully';
        } else {
            $response['success'] = true;
            $response['message'] = 'User registered successfully';
        }
    } else {
        $response['message'] = 'Registration failed';
    }
} catch (Exception $e) {
    // Log the error (in a production environment)
    error_log('Registration error: ' . $e->getMessage());
    $response['message'] = 'Database error occurred: ' . $e->getMessage();
}

// Return JSON response
echo json_encode($response);
?>