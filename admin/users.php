<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../database/db.php';

// Ensure we have a proper database connection
$db = get_db_manager();
$db->initializeDualConnections();

// Helper function to properly handle database results
function get_db_results($query, $params = []) {
    $result = db_execute($query, $params, true);
    
    // Handle different return formats
    if (is_array($result)) {
        return $result; // Already an array of results
    } elseif (is_object($result) && method_exists($result, 'fetchAll')) {
        // PDO statement
        return $result->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($result === true) {
        // Successful execution with no results
        return [];
    } else {
        // Failed or unknown format
        return [];
    }
}

// Initialize variables
$users = [];
$error_message = null;
$success_message = null;

// Handle actions
if (isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add_user':
                // Validate input
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                
                if (empty($username) || empty($email) || empty($password)) {
                    throw new Exception('All fields are required');
                }
                
                // Check if username exists using our helper function
                $check_query = "SELECT id FROM users WHERE username = ?";
                $existing_users = get_db_results($check_query, [$username]);
                
                if (!empty($existing_users)) {
                    throw new Exception('Username already exists');
                }
                
                // Create user - use a basic insert query that works in both databases
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_query = "INSERT INTO users (username, password, email) VALUES (?, ?, ?)";
                $result = db_execute($insert_query, [$username, $hashed_password, $email], false);
                
                if ($result) {
                    $success_message = "User {$username} added successfully";
                } else {
                    throw new Exception('Failed to add user');
                }
                break;
                
            case 'edit_user':
                // Validate input
                $user_id = $_POST['user_id'];
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                
                if (empty($user_id) || empty($username) || empty($email)) {
                    throw new Exception('User ID, username and email are required');
                }
                
                // Check if username exists for another user
                $check_query = "SELECT id FROM users WHERE username = ? AND id != ?";
                $existing_users = get_db_results($check_query, [$username, $user_id]);
                
                if (!empty($existing_users)) {
                    throw new Exception('Username already exists for another user');
                }
                
                if (!empty($password)) {
                    // Update with new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?";
                    $result = db_execute($update_query, [$username, $email, $hashed_password, $user_id], false);
                } else {
                    // Update without changing password
                    $update_query = "UPDATE users SET username = ?, email = ? WHERE id = ?";
                    $result = db_execute($update_query, [$username, $email, $user_id], false);
                }
                
                if ($result) {
                    $success_message = "User {$username} updated successfully";
                } else {
                    throw new Exception('Failed to update user');
                }
                break;
                
            case 'delete_user':
                // Validate input
                $user_id = $_POST['user_id'];
                
                if (empty($user_id)) {
                    throw new Exception('User ID is required');
                }
                
                // Delete user's notes first (prevent orphaned records)
                $delete_notes_query = "DELETE FROM notes WHERE user_id = ?";
                db_execute($delete_notes_query, [$user_id], false);
                
                // Delete user
                $delete_query = "DELETE FROM users WHERE id = ?";
                $result = db_execute($delete_query, [$user_id], false);
                
                if ($result) {
                    $success_message = "User deleted successfully";
                } else {
                    throw new Exception('Failed to delete user');
                }
                break;
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get all users
try {
    // Run migrations to ensure proper database structure
    try {
        // Ensure the users table exists in both databases
        $create_pg_table = "CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $create_sqlite_table = "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        // Try PostgreSQL connection
        $pg_conn = $db->getSpecificConnection(DB_TYPE_POSTGRESQL);
        if ($pg_conn) {
            pg_query($pg_conn, $create_pg_table);
        }
        
        // Try SQLite connection
        $sqlite_conn = $db->getSpecificConnection(DB_TYPE_SQLITE);
        if ($sqlite_conn && $sqlite_conn instanceof PDO) {
            $sqlite_conn->exec($create_sqlite_table);
        }
    } catch (Exception $e) {
        error_log("Table creation error: " . $e->getMessage());
        // Continue anyway - tables may already exist
    }
    
    // Get all users using our helper function
    $query = "SELECT id, username, email, created_at FROM users ORDER BY username";
    $users = get_db_results($query);
} catch (Exception $e) {
    $error_message = "Error listing users: " . $e->getMessage();
    error_log($error_message);
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Notebook Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        /* User management specific styles */
        .user-grid {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 10px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
            border-radius: 4px;
            padding: 10px;
        }
        
        .user-actions {
            display: flex;
            gap: 5px;
        }
        
        .user-form {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
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
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-buttons {
            grid-column: span 2;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-edit, .btn-delete {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-edit {
            background-color: #4caf50;
            color: white;
        }
        
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 500px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>Admin Panel</h1>
            </div>
            <nav class="nav-list">
                <div class="nav-item">
                    <a href="/" class="nav-link">
                        <svg class="icon" viewBox="0 0 24 24">
                            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                        </svg>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/admin/index.php" class="nav-link">
                        <svg class="icon" viewBox="0 0 24 24">
                            <path d="M20 13H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1v-6c0-.55-.45-1-1-1zM7 19c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM20 3H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1zM7 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>
                        </svg>
                        Database
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/admin/users.php" class="nav-link active">
                        <svg class="icon" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        User Management
                    </a>
                </div>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="admin-panel">
                <h2>User Management</h2>
                
                <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <strong>Success:</strong> <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>
                
                <!-- Add User Button -->
                <div class="action-panel">
                    <button id="addUserBtn" class="btn btn-primary">Add New User</button>
                </div>
                
                <!-- User List -->
                <div class="user-list">
                    <h3>Users</h3>
                    
                    <?php if (empty($users)): ?>
                        <p>No users found.</p>
                    <?php else: ?>
                        <div class="user-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                        <td>
                                            <div class="user-actions">
                                                <button class="btn-edit" data-user-id="<?php echo $user['id']; ?>" 
                                                       data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                       data-email="<?php echo htmlspecialchars($user['email']); ?>">Edit</button>
                                                <button class="btn-delete" data-user-id="<?php echo $user['id']; ?>"
                                                       data-username="<?php echo htmlspecialchars($user['username']); ?>">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form action="/admin/users.php" method="post">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form action="/admin/users.php" method="post">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" id="edit_user_id" name="user_id">
                
                <div class="form-group">
                    <label for="edit_username">Username</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_password">Password (leave blank to keep current)</label>
                    <input type="password" id="edit_password" name="password">
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete User Modal -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete User</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form action="/admin/users.php" method="post">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" id="delete_user_id" name="user_id">
                
                <p>Are you sure you want to delete user <strong id="delete_username"></strong>?</p>
                <p class="warning">This will also delete all notes belonging to this user and cannot be undone!</p>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add User Modal
            const addUserBtn = document.getElementById('addUserBtn');
            const addUserModal = document.getElementById('addUserModal');
            
            // Edit User Modal
            const editUserModal = document.getElementById('editUserModal');
            
            // Delete User Modal
            const deleteUserModal = document.getElementById('deleteUserModal');
            
            // Modal close buttons
            const modalCloseBtns = document.querySelectorAll('.modal-close, .modal-cancel');
            
            // Open Add User Modal
            addUserBtn.addEventListener('click', function() {
                addUserModal.classList.add('active');
            });
            
            // Open Edit User Modal
            document.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const username = this.dataset.username;
                    const email = this.dataset.email;
                    
                    document.getElementById('edit_user_id').value = userId;
                    document.getElementById('edit_username').value = username;
                    document.getElementById('edit_email').value = email;
                    document.getElementById('edit_password').value = '';
                    
                    editUserModal.classList.add('active');
                });
            });
            
            // Open Delete User Modal
            document.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const username = this.dataset.username;
                    
                    document.getElementById('delete_user_id').value = userId;
                    document.getElementById('delete_username').textContent = username;
                    
                    deleteUserModal.classList.add('active');
                });
            });
            
            // Close all modals
            modalCloseBtns.forEach(button => {
                button.addEventListener('click', function() {
                    addUserModal.classList.remove('active');
                    editUserModal.classList.remove('active');
                    deleteUserModal.classList.remove('active');
                });
            });
            
            // Close modals when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === addUserModal) {
                    addUserModal.classList.remove('active');
                }
                if (event.target === editUserModal) {
                    editUserModal.classList.remove('active');
                }
                if (event.target === deleteUserModal) {
                    deleteUserModal.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
