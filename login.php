<?php
// Include database connection
require_once 'database/db.php';

session_start();

$error = '';
$success = '';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Query the database
        $query = "SELECT id, username, password FROM users WHERE username = ?";
        $result = db_execute($query, [$username], true);
        
        if ($result && !empty($result) && isset($result[0])) {
            $user = $result[0];
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Set success message and redirect
                $success = 'Login successful! Redirecting...';
                header('Refresh: 2; URL=index.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    // Basic validation
    if (empty($username) || empty($password) || empty($confirm_password) || empty($email)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if username exists
        $check_query = "SELECT id FROM users WHERE username = ?";
        $result = db_execute($check_query, [$username], true);
        
        if ($result && !empty($result)) {
            $error = 'Username already exists. Please choose a different one.';
        } else {
            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO users (username, password, email) VALUES (?, ?, ?) RETURNING id";
            $result = db_execute($insert_query, [$username, $hashed_password, $email], true);
            
            if ($result) {
                $success = 'Registration successful! You can now log in.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login/Register - Notebook</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        h1, h2 {
            color: #2c3e50;
        }
        
        .container {
            max-width: 900px;
            margin: 30px auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }
        
        .auth-container {
            display: flex;
            justify-content: space-between;
        }
        
        .auth-section {
            width: 48%;
            padding: 20px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .error-message {
            color: #e74c3c;
            background-color: #fceaea;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            border-left: 4px solid #e74c3c;
        }
        
        .success-message {
            color: #27ae60;
            background-color: #e7f9f1;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            border-left: 4px solid #27ae60;
        }
        
        .home-link {
            display: block;
            margin-top: 20px;
            text-align: center;
        }
        
        .home-link a {
            color: #3498db;
            text-decoration: none;
        }
        
        .home-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Notebook - Login/Register</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="auth-container">
            <!-- Login Section -->
            <div class="auth-section">
                <h2>Login</h2>
                <form method="post" action="">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
            
            <!-- Register Section -->
            <div class="auth-section">
                <h2>Register</h2>
                <form method="post" action="">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group">
                        <label for="reg-username">Username:</label>
                        <input type="text" id="reg-username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg-password">Password:</label>
                        <input type="password" id="reg-password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm-password">Confirm Password:</label>
                        <input type="password" id="confirm-password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn">Register</button>
                </form>
            </div>
        </div>
        
        <div class="home-link">
            <a href="index.html">Return to Home</a>
        </div>
    </div>
</body>
</html>
