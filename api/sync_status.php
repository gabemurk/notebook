<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../database/enhanced_db_connect.php';

try {
    $db = new DatabaseManager();
    
    // Check PostgreSQL connection
    $pgConn = $db->getConnection(DB_TYPE_POSTGRESQL);
    $pgStatus = $pgConn ? true : false;
    
    // Check SQLite connection
    $sqliteConn = $db->getConnection(DB_TYPE_SQLITE);
    $sqliteStatus = $sqliteConn ? true : false;
    
    // Get sync status
    $syncStatus = [
        'postgresql' => [
            'connected' => $pgStatus,
            'is_primary' => true
        ],
        'sqlite' => [
            'connected' => $sqliteStatus,
            'is_primary' => !$pgStatus
        ],
        'last_sync' => null,
        'pending_changes' => 0
    ];
    
    // If PostgreSQL is connected, get last sync time
    if ($pgStatus) {
        $stmt = $pgConn->query("SELECT MAX(sync_timestamp) as last_sync FROM sync_log");
        if ($stmt) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $syncStatus['last_sync'] = $result['last_sync'];
        }
    }
    
    // If SQLite is connected, get number of pending changes
    if ($sqliteStatus) {
        $stmt = $sqliteConn->query("SELECT COUNT(*) as pending FROM sync_queue");
        if ($stmt) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $syncStatus['pending_changes'] = (int)$result['pending'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'status' => $syncStatus
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
