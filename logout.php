<?php
// Include database connection
require_once __DIR__ . '/database/db.php';

// Start session
session_start();

// Clear session data
$_SESSION = array();

// Destroy the session
session_destroy();

// Return success response for AJAX request
echo json_encode(['success' => true]);
?>
