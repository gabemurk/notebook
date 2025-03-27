<?php
require_once __DIR__ . '/db.php';

/**
 * Get database statistics
 * 
 * @param string $db_type Database type (postgresql or sqlite)
 * @return array Statistics including user count, note count, and size
 */
function get_database_stats($db_type) {
    try {
        $db = get_db_manager();
        $conn = $db->getSpecificConnection($db_type);
        
        if (!$conn) {
            return null;
        }
        
        $stats = [
            'total_users' => 0,
            'total_notes' => 0,
            'total_size_bytes' => 0
        ];
        
        // Get user count - wrapped in try-catch to handle missing tables
        try {
            $result = db_execute("SELECT COUNT(*) as count FROM users", [], true, $db_type);
            $stats['total_users'] = isset($result[0]['count']) ? $result[0]['count'] : 0;
        } catch (Exception $e) {
            $stats['total_users'] = 0;
        }
        
        // Get note count - wrapped in try-catch to handle missing tables
        try {
            $result = db_execute("SELECT COUNT(*) as count FROM notes", [], true, $db_type);
            $stats['total_notes'] = isset($result[0]['count']) ? $result[0]['count'] : 0;
        } catch (Exception $e) {
            $stats['total_notes'] = 0;
        }
        
        // Get database size
        try {
            if ($db_type === DB_TYPE_POSTGRESQL) {
                $result = db_execute(
                    "SELECT pg_database_size(current_database()) as size",
                    [], true, $db_type
                );
                $stats['total_size_bytes'] = isset($result[0]['size']) ? $result[0]['size'] : 0;
            } else {
                // For SQLite, get the file size
                $config = $GLOBALS['DB_CONFIG']['sqlite'];
                $stats['total_size_bytes'] = file_exists($config['path']) ? filesize($config['path']) : 0;
            }
        } catch (Exception $e) {
            $stats['total_size_bytes'] = 0;
        }
        
        // Save stats to history - only if we have valid stats
        try {
            if ($stats['total_size_bytes'] > 0) {
                // Check if the database_stats table exists first
                $table_exists = false;
                
                if ($db_type === DB_TYPE_POSTGRESQL) {
                    $check = db_execute("SELECT to_regclass('public.database_stats')", [], true, $db_type);
                    $table_exists = isset($check[0]['to_regclass']) && $check[0]['to_regclass'] == 'database_stats';
                } else {
                    $check = db_execute("SELECT name FROM sqlite_master WHERE type='table' AND name='database_stats'", [], true, $db_type);
                    $table_exists = !empty($check);
                }
                
                if ($table_exists) {
                    db_execute(
                        "INSERT INTO database_stats (db_type, total_users, total_notes, total_size_bytes) 
                         VALUES (?, ?, ?, ?)",
                        [$db_type, $stats['total_users'], $stats['total_notes'], $stats['total_size_bytes']],
                        false
                    );
                } else {
                    // Log that table doesn't exist but don't throw an error
                    error_log("database_stats table doesn't exist, skipping stats insertion");
                }
            }
        } catch (Exception $e) {
            // Silently fail if we can't save stats
            error_log("Error saving database stats: " . $e->getMessage());
        }
        
        return $stats;
    } catch (Exception $e) {
        // If anything goes wrong, return null
        return null;
    }
}

/**
 * Get sync history
 * 
 * @param int $limit Number of records to return
 * @return array Sync history records
 */
function get_sync_history($limit = 10) {
    return db_execute(
        "SELECT * FROM sync_history ORDER BY sync_time DESC LIMIT ?",
        [$limit],
        true
    );
}

/**
 * Log sync event
 * 
 * @param string $direction Sync direction (pg_to_sqlite or sqlite_to_pg)
 * @param string $status Status (success or error)
 * @param string $error_message Error message if any
 * @param int $affected_rows Number of rows affected
 */
function log_sync_event($direction, $status, $error_message = null, $affected_rows = 0) {
    db_execute(
        "INSERT INTO sync_history (direction, status, error_message, affected_rows) 
         VALUES (?, ?, ?, ?)",
        [$direction, $status, $error_message, $affected_rows],
        false
    );
}

/**
 * Create database backup
 * 
 * @param string $db_type Database type to backup
 * @return array Backup result with status and path
 */
function create_database_backup($db_type) {
    $backup_dir = __DIR__ . '/../backups';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backup_path = "$backup_dir/{$db_type}_backup_$timestamp";
    
    try {
        if ($db_type === DB_TYPE_POSTGRESQL) {
            $config = $GLOBALS['DB_CONFIG']['postgresql'];
            $env = [
                'PGPASSWORD' => $config['password']
            ];
            $command = sprintf(
                'pg_dump -h %s -p %s -U %s -F c %s > %s.dump',
                $config['host'],
                $config['port'],
                $config['user'],
                $config['dbname'],
                $backup_path
            );
            exec($command, $output, $return_var);
            
            if ($return_var !== 0) {
                throw new Exception("PostgreSQL backup failed");
            }
            
            $backup_path .= '.dump';
        } else {
            $config = $GLOBALS['DB_CONFIG']['sqlite'];
            copy($config['path'], $backup_path . '.db');
            $backup_path .= '.db';
        }
        
        $size = filesize($backup_path);
        
        // Log backup
        db_execute(
            "INSERT INTO backup_history (db_type, file_path, file_size_bytes, status) 
             VALUES (?, ?, ?, 'success')",
            [$db_type, $backup_path, $size],
            false
        );
        
        return [
            'status' => 'success',
            'path' => $backup_path,
            'size' => $size
        ];
    } catch (Exception $e) {
        // Log error
        db_execute(
            "INSERT INTO backup_history (db_type, file_path, file_size_bytes, status, error_message) 
             VALUES (?, ?, 0, 'error', ?)",
            [$db_type, $backup_path, $e->getMessage()],
            false
        );
        
        throw $e;
    }
}

/**
 * Get backup history
 * 
 * @param int $limit Number of records to return
 * @return array Backup history records
 */
function get_backup_history($limit = 10) {
    return db_execute(
        "SELECT * FROM backup_history ORDER BY backup_time DESC LIMIT ?",
        [$limit],
        true
    );
}

/**
 * Get auto-sync settings
 * 
 * @return array Auto-sync settings
 */
function get_auto_sync_settings() {
    try {
        $result = db_execute(
            "SELECT * FROM auto_sync_settings ORDER BY id DESC LIMIT 1",
            [],
            true
        );
        
        if (!empty($result) && isset($result[0])) {
            $settings = [
                'enabled' => filter_var($result[0]['enabled'], FILTER_VALIDATE_BOOLEAN),
                'interval_minutes' => (int)$result[0]['interval_minutes'],
                'last_run' => isset($result[0]['last_run']) ? $result[0]['last_run'] : null
            ];
            
            // Handle potential missing next_run column
            if (isset($result[0]['next_run'])) {
                $settings['next_run'] = $result[0]['next_run'];
            } else {
                // Calculate next_run based on last_run and interval
                if (!empty($settings['last_run'])) {
                    $last_run = new DateTime($settings['last_run']);
                    $last_run->add(new DateInterval('PT' . $settings['interval_minutes'] . 'M'));
                    $settings['next_run'] = $last_run->format('Y-m-d H:i:s');
                } else {
                    $settings['next_run'] = (new DateTime())->format('Y-m-d H:i:s');
                }
            }
            
            return $settings;
        }
    } catch (Exception $e) {
        error_log('Error getting auto-sync settings: ' . $e->getMessage());
    }
    
    // Create default settings if none exist
    try {
        db_execute(
            "INSERT INTO auto_sync_settings (enabled, interval_minutes) VALUES (false, 60)",
            [],
            false
        );
    } catch (Exception $e) {
        error_log('Error creating default auto-sync settings: ' . $e->getMessage());
    }
    
    // Return default settings with proper next_run value
    return [
        'enabled' => false,
        'next_run' => (new DateTime())->format('Y-m-d H:i:s'),
        'interval_minutes' => 60,
        'last_run' => null,
        'next_run' => null
    ];
}

/**
 * Update auto-sync settings
 * 
 * @param bool $enabled Whether auto-sync is enabled
 * @param int $interval_minutes Sync interval in minutes
 */
function update_auto_sync_settings($enabled, $interval_minutes) {
    $next_run = $enabled ? 
        date('Y-m-d H:i:s', strtotime("+$interval_minutes minutes")) : 
        null;
    
    db_execute(
        "UPDATE auto_sync_settings 
         SET enabled = ?, 
             interval_minutes = ?, 
             next_run = ?,
             updated_at = CURRENT_TIMESTAMP",
        [$enabled, $interval_minutes, $next_run],
        false
    );
}

/**
 * Check and run auto-sync if needed
 */
function check_auto_sync() {
    $settings = get_auto_sync_settings();
    
    if (!$settings['enabled']) {
        return;
    }
    
    $now = new DateTime();
    $next_run = $settings['next_run'] ? new DateTime($settings['next_run']) : null;
    
    if (!$next_run || $now >= $next_run) {
        // Time to sync
        try {
            db_sync();
            
            // Update last_run and next_run
            $next_run = date('Y-m-d H:i:s', strtotime("+{$settings['interval_minutes']} minutes"));
            db_execute(
                "UPDATE auto_sync_settings 
                 SET last_run = CURRENT_TIMESTAMP, 
                     next_run = ?, 
                     updated_at = CURRENT_TIMESTAMP",
                [$next_run],
                false
            );
        } catch (Exception $e) {
            log_sync_event('auto', 'error', $e->getMessage());
        }
    }
}
