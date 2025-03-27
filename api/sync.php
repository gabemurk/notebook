<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../database/enhanced_db_connect.php';

try {
    // Get the raw POST data
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Get database connection
    $db = new DatabaseManager();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Process based on change type
    switch ($data['type']) {
        case 'create':
            $result = processCreate($conn, $data['data']);
            break;
            
        case 'update':
            $result = processUpdate($conn, $data['data']);
            break;
            
        case 'delete':
            $result = processDelete($conn, $data['data']);
            break;
            
        default:
            throw new Exception('Unknown change type');
    }
    
    // Trigger sync between PostgreSQL and SQLite
    $db->syncDatabases();
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function processCreate($conn, $data) {
    // Validate required fields
    if (!isset($data['content'])) {
        throw new Exception('Missing required fields');
    }
    
    $userId = $_SESSION['user']['id'] ?? null;
    if (!$userId) {
        throw new Exception('User not authenticated');
    }
    
    $stmt = $conn->prepare("
        INSERT INTO notes (user_id, title, content, created_at, updated_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        RETURNING id, title, content, created_at, updated_at
    ");
    
    $title = substr(strip_tags($data['content']), 0, 50) . '...';
    $stmt->execute([$userId, $title, $data['content']]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function processUpdate($conn, $data) {
    // Validate required fields
    if (!isset($data['id']) || !isset($data['content'])) {
        throw new Exception('Missing required fields');
    }
    
    $userId = $_SESSION['user']['id'] ?? null;
    if (!$userId) {
        throw new Exception('User not authenticated');
    }
    
    // Verify note ownership
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$data['id'], $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Note not found or access denied');
    }
    
    // Update the note
    $stmt = $conn->prepare("
        UPDATE notes 
        SET content = ?, 
            title = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND user_id = ?
        RETURNING id, title, content, created_at, updated_at
    ");
    
    $title = substr(strip_tags($data['content']), 0, 50) . '...';
    $stmt->execute([$data['content'], $title, $data['id'], $userId]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function processDelete($conn, $data) {
    // Validate required fields
    if (!isset($data['id'])) {
        throw new Exception('Missing note ID');
    }
    
    $userId = $_SESSION['user']['id'] ?? null;
    if (!$userId) {
        throw new Exception('User not authenticated');
    }
    
    // Verify note ownership
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$data['id'], $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Note not found or access denied');
    }
    
    // Soft delete the note
    $stmt = $conn->prepare("
        UPDATE notes 
        SET is_deleted = true,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND user_id = ?
        RETURNING id
    ");
    
    $stmt->execute([$data['id'], $userId]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
