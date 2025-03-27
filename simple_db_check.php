<?php
// Include only necessary files for connection
require_once __DIR__ . '/database/db.php';

// Function to safely check database status
function check_db_status() {
    try {
        // Get database manager instance
        $db_manager = get_db_manager();
        
        // Check PostgreSQL connection
        $pg_status = [
            'status' => false,
            'message' => 'Unknown status'
        ];
        
        try {
            $pg_conn = $db_manager->getConnection(DB_TYPE_POSTGRESQL);
            if ($pg_conn) {
                $pg_status['status'] = true;
                $pg_status['message'] = 'Connected';
                
                // Try a simple query
                $result = $db_manager->executeQuery("SELECT 1 as check_value", [], true, DB_TYPE_POSTGRESQL);
                $pg_status['query_test'] = !empty($result) ? 'Success' : 'Failed';
            } else {
                $pg_status['message'] = 'Failed to connect';
            }
        } catch (Exception $e) {
            $pg_status['message'] = 'Error: ' . $e->getMessage();
        }
        
        // Check SQLite connection
        $sqlite_status = [
            'status' => false,
            'message' => 'Unknown status'
        ];
        
        try {
            $sqlite_conn = $db_manager->getConnection(DB_TYPE_SQLITE);
            if ($sqlite_conn) {
                $sqlite_status['status'] = true;
                $sqlite_status['message'] = 'Connected';
                
                // Try a simple query - using proper SQLite syntax on SQLite connection
                $result = $db_manager->executeQuery("SELECT 1 as check_value", [], true, DB_TYPE_SQLITE);
                $sqlite_status['query_test'] = !empty($result) ? 'Success' : 'Failed';
            } else {
                $sqlite_status['message'] = 'Failed to connect';
            }
        } catch (Exception $e) {
            $sqlite_status['message'] = 'Error: ' . $e->getMessage();
        }
        
        // Return combined status
        return [
            'postgresql' => $pg_status,
            'sqlite' => $sqlite_status,
            'active_db' => $GLOBALS['active_db_type'] ?? 'None'
        ];
        
    } catch (Exception $e) {
        return ['error' => 'General error: ' . $e->getMessage()];
    }
}

// Output format
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'html';

// Check database status
$status = check_db_status();

if ($format === 'json') {
    // JSON output
    header('Content-Type: application/json');
    echo json_encode($status, JSON_PRETTY_PRINT);
    exit;
}

// HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Database Status Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .status-box {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .failure {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .return-link {
            margin-top: 20px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simple Database Status Check</h1>
        
        <h2>Active Database: <?php echo htmlspecialchars($status['active_db']); ?></h2>
        
        <!-- PostgreSQL Status -->
        <div class="status-box <?php echo $status['postgresql']['status'] ? 'success' : 'failure'; ?>">
            <h3>PostgreSQL Status: <?php echo $status['postgresql']['status'] ? 'Connected' : 'Disconnected'; ?></h3>
            <p><?php echo htmlspecialchars($status['postgresql']['message']); ?></p>
            <?php if (isset($status['postgresql']['query_test'])): ?>
                <p>Test Query: <?php echo htmlspecialchars($status['postgresql']['query_test']); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- SQLite Status -->
        <div class="status-box <?php echo $status['sqlite']['status'] ? 'success' : 'failure'; ?>">
            <h3>SQLite Status: <?php echo $status['sqlite']['status'] ? 'Connected' : 'Disconnected'; ?></h3>
            <p><?php echo htmlspecialchars($status['sqlite']['message']); ?></p>
            <?php if (isset($status['sqlite']['query_test'])): ?>
                <p>Test Query: <?php echo htmlspecialchars($status['sqlite']['query_test']); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if (isset($status['error'])): ?>
        <div class="status-box failure">
            <h3>Error:</h3>
            <p><?php echo htmlspecialchars($status['error']); ?></p>
        </div>
        <?php endif; ?>
        
        <a href="/" class="return-link">Return to Application</a>
        <a href="/simple_editor.php" class="return-link">Go to Simple Editor</a>
    </div>
</body>
</html>
