<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../database/admin_utils.php';

// Check for auto-sync
check_auto_sync();

// Initialize variables
$pg_status = false;
$sqlite_status = false;
$last_sync = null;
$error_message = null;
$success_message = null;

try {
    // Check database connections
    $db = get_db_manager();
    $db->initializeDualConnections();
    
    // Get connections and verify they're actually working
    $pg_conn = $db->getSpecificConnection(DB_TYPE_POSTGRESQL);
    $pg_status = false;
    
    if ($pg_conn) {
        // Test the PostgreSQL connection with a simple query
        try {
            $result = @pg_query($pg_conn, "SELECT 1 AS connection_test");
            if ($result && pg_fetch_assoc($result)) {
                $pg_status = true;
                pg_free_result($result);
            }
        } catch (Exception $e) {
            error_log("PostgreSQL connection test failed: " . $e->getMessage());
        }
    }
    
    // Check SQLite connection
    $sqlite_conn = $db->getSpecificConnection(DB_TYPE_SQLITE);
    $sqlite_status = false;
    
    if ($sqlite_conn && $sqlite_conn instanceof PDO) {
        try {
            $result = $sqlite_conn->query("SELECT 1 AS connection_test");
            if ($result && $result->fetch(PDO::FETCH_ASSOC)) {
                $sqlite_status = true;
                $result->closeCursor();
            }
        } catch (Exception $e) {
            error_log("SQLite connection test failed: " . $e->getMessage());
        }
    }
    
    $last_sync = $db->getLastSyncTime();
    
    // Get database stats only if connections are verified working
    // Wrap in try-catch to prevent errors
    try {
        $pg_stats = $pg_status ? get_database_stats(DB_TYPE_POSTGRESQL) : null;
    } catch (Exception $e) {
        error_log("Failed to get PostgreSQL stats: " . $e->getMessage());
        $pg_stats = null;
    }
    
    try {
        $sqlite_stats = $sqlite_status ? get_database_stats(DB_TYPE_SQLITE) : null;
    } catch (Exception $e) {
        error_log("Failed to get SQLite stats: " . $e->getMessage());
        $sqlite_stats = null;
    }
    
    // Get sync history
    $sync_history = get_sync_history(5);
    
    // Get backup history
    $backup_history = get_backup_history(5);
    
    // Get auto-sync settings
    $auto_sync = get_auto_sync_settings();
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Handle actions
if (isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'sync':
                // Verify connections before attempting sync
                $db = get_db_manager();
                $pg_conn = $db->getSpecificConnection(DB_TYPE_POSTGRESQL);
                $sqlite_conn = $db->getSpecificConnection(DB_TYPE_SQLITE);
                
                if (!$pg_conn) {
                    throw new Exception('Cannot sync: PostgreSQL database not connected');
                }
                
                if (!$sqlite_conn) {
                    throw new Exception('Cannot sync: SQLite database not connected');
                }
                
                // Attempt sync with detailed error handling
                $sync_result = db_sync();
                if ($sync_result) {
                    $success_message = 'Databases synchronized successfully';
                    log_sync_event('manual', 'success');
                } else {
                    // Try to get more detailed error information from error logs
                    $error_details = "";
                    if (function_exists('error_get_last') && $last_error = error_get_last()) {
                        $error_details = ": " . $last_error['message'];
                    }
                    throw new Exception('Failed to synchronize databases' . $error_details);
                }
                break;
                
            case 'backup':
                $db_type = $_POST['db_type'];
                $result = create_database_backup($db_type);
                $success_message = "Backup created successfully at: {$result['path']}";
                break;
                
            case 'auto_sync':
                $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
                $interval = (int)$_POST['interval'];
                update_auto_sync_settings($enabled, $interval);
                $success_message = 'Auto-sync settings updated';
                break;
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notebook Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
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
                    <a href="/admin/index.php" class="nav-link active">
                        <svg class="icon" viewBox="0 0 24 24">
                            <path d="M20 13H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1v-6c0-.55-.45-1-1-1zM7 19c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM20 3H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1zM7 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>
                        </svg>
                        Database
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/admin/users.php" class="nav-link">
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
                <h2>Database Status</h2>
                
                <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>
                
                <div class="status-cards">
                    <!-- PostgreSQL Status -->
                    <div class="status-card">
                        <div class="card-header">
                            <h3>PostgreSQL</h3>
                            <div class="status-indicator">
                                <span class="status-dot <?php echo $pg_status ? 'connected' : 'disconnected'; ?>"></span>
                                <span><?php echo $pg_status ? 'Connected' : 'Disconnected'; ?></span>
                            </div>
                        </div>
                        <div class="card-content">
                            <p>Primary Database</p>
                            <?php if ($pg_status): ?>
                                <p class="text-success">Running normally</p>
                            <?php else: ?>
                                <p class="text-error">Connection failed</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- SQLite Status -->
                    <div class="status-card">
                        <div class="card-header">
                            <h3>SQLite</h3>
                            <div class="status-indicator">
                                <span class="status-dot <?php echo $sqlite_status ? 'connected' : 'disconnected'; ?>"></span>
                                <span><?php echo $sqlite_status ? 'Connected' : 'Disconnected'; ?></span>
                            </div>
                        </div>
                        <div class="card-content">
                            <p>Fallback Database</p>
                            <?php if ($sqlite_status): ?>
                                <p class="text-success">Ready for failover</p>
                            <?php else: ?>
                                <p class="text-error">Fallback unavailable</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sync Status -->
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>

                <!-- Database Statistics -->
                <div class="stats-panel">
                    <h3>Database Statistics</h3>
                    <div class="stats-grid">
                        <div class="stats-card">
                            <h4>PostgreSQL</h4>
                            <?php if ($pg_stats): ?>
                                <p>Users: <?php echo number_format($pg_stats['total_users']); ?></p>
                                <p>Notes: <?php echo number_format($pg_stats['total_notes']); ?></p>
                                <p>Size: <?php echo number_format($pg_stats['total_size_bytes'] / 1024 / 1024, 2); ?> MB</p>
                            <?php else: ?>
                                <p class="text-error">Not available</p>
                            <?php endif; ?>
                        </div>
                        <div class="stats-card">
                            <h4>SQLite</h4>
                            <?php if ($sqlite_stats): ?>
                                <p>Users: <?php echo number_format($sqlite_stats['total_users']); ?></p>
                                <p>Notes: <?php echo number_format($sqlite_stats['total_notes']); ?></p>
                                <p>Size: <?php echo number_format($sqlite_stats['total_size_bytes'] / 1024 / 1024, 2); ?> MB</p>
                            <?php else: ?>
                                <p class="text-error">Not available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sync Panel -->
                <div class="sync-panel">
                    <h3>Database Synchronization</h3>
                    
                    <!-- Manual Sync -->
                    <div class="sync-section">
                        <h4>Manual Sync</h4>
                        <p>Last sync: <?php echo $last_sync ? date('Y-m-d H:i:s', strtotime($last_sync)) : 'Never'; ?></p>
                        <form method="POST" class="sync-form">
                            <input type="hidden" name="action" value="sync">
                            <button type="submit" class="btn btn-primary" <?php echo (!$pg_status || !$sqlite_status) ? 'disabled' : ''; ?>>
                                <svg class="icon" viewBox="0 0 24 24">
                                    <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/>
                                </svg>
                                Synchronize Now
                            </button>
                        </form>
                    </div>
                    
                    <!-- Auto Sync -->
                    <div class="sync-section">
                        <h4>Automatic Sync</h4>
                        <form method="POST" class="auto-sync-form">
                            <input type="hidden" name="action" value="auto_sync">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="enabled" value="true" <?php echo $auto_sync['enabled'] ? 'checked' : ''; ?>>
                                    Enable automatic synchronization
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Sync interval (minutes):</label>
                                <input type="number" name="interval" value="<?php echo $auto_sync['interval_minutes']; ?>" min="5" max="1440">
                            </div>
                            <button type="submit" class="btn btn-secondary">Save Settings</button>
                        </form>
                        <?php if ($auto_sync['enabled']): ?>
                            <p class="text-info">
                                Last run: <?php echo $auto_sync['last_run'] ? date('Y-m-d H:i:s', strtotime($auto_sync['last_run'])) : 'Never'; ?><br>
                                Next run: <?php echo $auto_sync['next_run'] ? date('Y-m-d H:i:s', strtotime($auto_sync['next_run'])) : 'Not scheduled'; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sync History -->
                    <div class="sync-section">
                        <h4>Sync History</h4>
                        <div class="history-list">
                            <?php foreach ($sync_history as $sync): ?>
                                <div class="history-item <?php echo $sync['status']; ?>">
                                    <div class="history-time"><?php echo date('Y-m-d H:i:s', strtotime($sync['sync_time'])); ?></div>
                                    <div class="history-details">
                                        <span class="badge"><?php echo ucfirst($sync['direction']); ?></span>
                                        <span class="badge <?php echo $sync['status']; ?>"><?php echo ucfirst($sync['status']); ?></span>
                                        <?php if ($sync['error_message']): ?>
                                            <span class="error-message"><?php echo htmlspecialchars($sync['error_message']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Backup Panel -->
                <div class="backup-panel">
                    <h3>Database Backups</h3>
                    
                    <!-- Create Backup -->
                    <div class="backup-section">
                        <h4>Create Backup</h4>
                        <form method="POST" class="backup-form">
                            <input type="hidden" name="action" value="backup">
                            <div class="form-group">
                                <label>Select Database:</label>
                                <select name="db_type" required>
                                    <option value="<?php echo DB_TYPE_POSTGRESQL; ?>" <?php echo !$pg_status ? 'disabled' : ''; ?>>PostgreSQL</option>
                                    <option value="<?php echo DB_TYPE_SQLITE; ?>" <?php echo !$sqlite_status ? 'disabled' : ''; ?>>SQLite</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <svg class="icon" viewBox="0 0 24 24">
                                    <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z"/>
                                </svg>
                                Create Backup
                            </button>
                        </form>
                    </div>
                    
                    <!-- Backup History -->
                    <div class="backup-section">
                        <h4>Backup History</h4>
                        <div class="history-list">
                            <?php foreach ($backup_history as $backup): ?>
                                <div class="history-item <?php echo $backup['status']; ?>">
                                    <div class="history-time"><?php echo date('Y-m-d H:i:s', strtotime($backup['backup_time'])); ?></div>
                                    <div class="history-details">
                                        <span class="badge"><?php echo ucfirst($backup['db_type']); ?></span>
                                        <span class="badge <?php echo $backup['status']; ?>"><?php echo ucfirst($backup['status']); ?></span>
                                        <span class="size"><?php echo number_format($backup['file_size_bytes'] / 1024 / 1024, 2); ?> MB</span>
                                        <?php if ($backup['error_message']): ?>
                                            <span class="error-message"><?php echo htmlspecialchars($backup['error_message']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
