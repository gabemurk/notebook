<?php
/**
 * Database Status and Management Dashboard
 * 
 * This page allows you to view the status of all database connections,
 * perform data synchronization, and run diagnostics.
 */

// Turn on error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database package
include '../database/index.php';

// Initialize database manager with dual connections
$db = get_db_manager();
$db->initializeDualConnections();

// Get database connection status
$has_dual = $db->hasDualConnections();
$pg_conn = $db->getConnection(DB_TYPE_POSTGRESQL);
$sqlite_conn = $db->getConnection(DB_TYPE_SQLITE);
$current_db = get_db_type();

// Process any actions
$action_message = '';
$action_success = false;

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'sync_pg_to_sqlite':
            try {
                $action_success = db_sync('pg_to_sqlite');
                $action_message = $action_success 
                    ? "Successfully synchronized data from PostgreSQL to SQLite" 
                    : "Failed to synchronize data";
            } catch (Exception $e) {
                $action_message = "Error: " . $e->getMessage();
            }
            break;
            
        case 'sync_sqlite_to_pg':
            try {
                $action_success = db_sync('sqlite_to_pg');
                $action_message = $action_success 
                    ? "Successfully synchronized data from SQLite to PostgreSQL" 
                    : "Failed to synchronize data";
            } catch (Exception $e) {
                $action_message = "Error: " . $e->getMessage();
            }
            break;
    }
}

// Get table statistics for both databases
function get_table_stats($db_type) {
    $tables = [];
    $orig_type = isset($GLOBALS['active_db_type']) ? $GLOBALS['active_db_type'] : null;
    
    // Switch to the target database
    $GLOBALS['active_db_type'] = $db_type;
    
    // Get table list based on database type
    if ($db_type === DB_TYPE_POSTGRESQL) {
        $table_list = db_execute("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
        foreach ($table_list as $table_row) {
            $table_name = $table_row['table_name'];
            $count = db_execute("SELECT COUNT(*) as count FROM $table_name");
            $tables[$table_name] = [
                'count' => $count[0]['count'],
                'last_updated' => null
            ];
            
            // Try to get last updated time if the table has an updated_at column
            try {
                $column_check = db_execute(
                    "SELECT column_name FROM information_schema.columns WHERE table_name = :table AND column_name = 'updated_at'",
                    ['table' => $table_name]
                );
                
                if (!empty($column_check)) {
                    $last_updated = db_execute("SELECT MAX(updated_at) as last_update FROM $table_name");
                    if (!empty($last_updated) && isset($last_updated[0]['last_update'])) {
                        $tables[$table_name]['last_updated'] = $last_updated[0]['last_update'];
                    }
                }
            } catch (Exception $e) {
                // Ignore errors trying to get last updated
            }
        }
    } else if ($db_type === DB_TYPE_SQLITE) {
        $table_list = db_execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        foreach ($table_list as $table_row) {
            $table_name = $table_row['name'];
            $count = db_execute("SELECT COUNT(*) as count FROM $table_name");
            $tables[$table_name] = [
                'count' => $count[0]['count'],
                'last_updated' => null
            ];
            
            // Try to get last updated time if the table has an updated_at column
            try {
                $pragma_result = db_execute("PRAGMA table_info($table_name)");
                $has_updated_at = false;
                
                foreach ($pragma_result as $column) {
                    if ($column['name'] === 'updated_at') {
                        $has_updated_at = true;
                        break;
                    }
                }
                
                if ($has_updated_at) {
                    $last_updated = db_execute("SELECT MAX(updated_at) as last_update FROM $table_name");
                    if (!empty($last_updated) && isset($last_updated[0]['last_update'])) {
                        $tables[$table_name]['last_updated'] = $last_updated[0]['last_update'];
                    }
                }
            } catch (Exception $e) {
                // Ignore errors trying to get last updated
            }
        }
    }
    
    // Restore original database type
    $GLOBALS['active_db_type'] = $orig_type;
    
    return $tables;
}

$pg_tables = $pg_conn ? get_table_stats(DB_TYPE_POSTGRESQL) : [];
$sqlite_tables = $sqlite_conn ? get_table_stats(DB_TYPE_SQLITE) : [];

// HTML Page
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Status Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f5f7fa;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        header {
            margin-bottom: 30px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        h1 {
            color: #2c3e50;
            margin-top: 0;
        }
        h2 {
            color: #3498db;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-top: 25px;
        }
        .status-card {
            background: white;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .database-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            grid-gap: 20px;
        }
        .database-box {
            background: white;
            border-radius: 6px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .database-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            margin-left: 10px;
        }
        .status-connected {
            background-color: #27ae60;
            color: white;
        }
        .status-disconnected {
            background-color: #e74c3c;
            color: white;
        }
        .status-active {
            background-color: #f39c12;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .action-btns {
            margin-top: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-danger {
            background-color: #e74c3c;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .btn-success {
            background-color: #27ae60;
        }
        .btn-success:hover {
            background-color: #219955;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .compare-table {
            overflow-x: auto;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 14px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Database Status Dashboard</h1>
            <p>View and manage your dual database connections and synchronization</p>
        </header>
        
        <?php if ($action_message): ?>
        <div class="alert <?php echo $action_success ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo $action_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="status-card">
            <h2>Connection Status</h2>
            <p>
                <strong>PostgreSQL:</strong> 
                <span class="database-status <?php echo $pg_conn ? 'status-connected' : 'status-disconnected'; ?>">
                    <?php echo $pg_conn ? 'Connected' : 'Disconnected'; ?>
                </span>
                <?php if ($current_db === DB_TYPE_POSTGRESQL): ?>
                <span class="database-status status-active">Active</span>
                <?php endif; ?>
            </p>
            
            <p>
                <strong>SQLite:</strong> 
                <span class="database-status <?php echo $sqlite_conn ? 'status-connected' : 'status-disconnected'; ?>">
                    <?php echo $sqlite_conn ? 'Connected' : 'Disconnected'; ?>
                </span>
                <?php if ($current_db === DB_TYPE_SQLITE): ?>
                <span class="database-status status-active">Active</span>
                <?php endif; ?>
            </p>
            
            <p>
                <strong>Dual Connections:</strong> 
                <span class="database-status <?php echo $has_dual ? 'status-connected' : 'status-disconnected'; ?>">
                    <?php echo $has_dual ? 'Available' : 'Unavailable'; ?>
                </span>
            </p>
            
            <p><strong>Current Active Database:</strong> <?php echo ucfirst($current_db); ?></p>
        </div>
        
        <div class="database-grid">
            <!-- PostgreSQL Status -->
            <div class="database-box">
                <h2>PostgreSQL Database</h2>
                
                <?php if ($pg_conn): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Table</th>
                                <th>Records</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pg_tables)): ?>
                                <tr>
                                    <td colspan="3">No tables found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pg_tables as $table_name => $stats): ?>
                                <tr>
                                    <td><?php echo $table_name; ?></td>
                                    <td><?php echo $stats['count']; ?></td>
                                    <td><?php echo $stats['last_updated'] ?: 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>PostgreSQL connection is not available.</p>
                <?php endif; ?>
            </div>
            
            <!-- SQLite Status -->
            <div class="database-box">
                <h2>SQLite Database</h2>
                
                <?php if ($sqlite_conn): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Table</th>
                                <th>Records</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sqlite_tables)): ?>
                                <tr>
                                    <td colspan="3">No tables found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sqlite_tables as $table_name => $stats): ?>
                                <tr>
                                    <td><?php echo $table_name; ?></td>
                                    <td><?php echo $stats['count']; ?></td>
                                    <td><?php echo $stats['last_updated'] ?: 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>SQLite connection is not available.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="status-card">
            <h2>Data Comparison and Synchronization</h2>
            
            <?php if ($has_dual): ?>
                <div class="compare-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Table</th>
                                <th>PostgreSQL Count</th>
                                <th>SQLite Count</th>
                                <th>Difference</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $all_tables = array_unique(array_merge(array_keys($pg_tables), array_keys($sqlite_tables)));
                            foreach ($all_tables as $table_name): 
                                $pg_count = isset($pg_tables[$table_name]) ? $pg_tables[$table_name]['count'] : 0;
                                $sqlite_count = isset($sqlite_tables[$table_name]) ? $sqlite_tables[$table_name]['count'] : 0;
                                $difference = $pg_count - $sqlite_count;
                                $synced = ($difference === 0);
                            ?>
                            <tr>
                                <td><?php echo $table_name; ?></td>
                                <td><?php echo $pg_count; ?></td>
                                <td><?php echo $sqlite_count; ?></td>
                                <td><?php echo $difference; ?></td>
                                <td>
                                    <?php if ($synced): ?>
                                        <span style="color: #27ae60;">✓ Synchronized</span>
                                    <?php else: ?>
                                        <span style="color: #e74c3c;">✗ Out of sync</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="action-btns">
                    <form method="post" action="">
                        <input type="hidden" name="action" value="sync_pg_to_sqlite">
                        <button type="submit" class="btn btn-success">Synchronize PostgreSQL → SQLite</button>
                    </form>
                    
                    <form method="post" action="">
                        <input type="hidden" name="action" value="sync_sqlite_to_pg">
                        <button type="submit" class="btn">Synchronize SQLite → PostgreSQL</button>
                    </form>
                </div>
            <?php else: ?>
                <p>Dual connections are required for data comparison and synchronization.</p>
            <?php endif; ?>
        </div>
        
        <footer class="footer">
            <p>Notebook Application - Dual Database System</p>
        </footer>
    </div>
</body>
</html>
