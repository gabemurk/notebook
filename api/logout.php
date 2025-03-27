<?php
// Start the session
session_start();

// Destroy the session
session_unset();
session_destroy();

// Return success response
$response = [
    'success' => true,
    'message' => 'Successfully logged out'
];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
