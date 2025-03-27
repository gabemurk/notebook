<?php
/**
 * Database Debugging Tool
 * 
 * This tool provides detailed diagnostics for the database connection and permissions issues
 */

// Initialize output buffering to capture any errors
ob_start();

// Set error reporting to maximum
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once __DIR__ . '/../database/config.php';

// Utility function to format the output
function outputSection($title, $content, $status = 'info') {
    $statusClass = $status === 'success' ? 'success' : ($status === 'error' ? 'error' : 'info');
    echo "<div class='section {$statusClass}'>";
    echo "<h3>{$title}</h3>";
    echo "<div class='content'>{$content}</div>";
    echo "</div>";
}

// Function to test database connection
function testConnection($type, $config) {
    $result = [
        'success' => false,
        'message' => '',
        'details' => []
    ];
    
    try {
        if ($type === 'postgresql') {
            // Build connection string
            $conn_string = "host={$config['host']} port={$config['port']} dbname={$config['dbname']} user={$config['user']} password={$config['password']}";
            
            // Try to connect
            $conn = @pg_connect($conn_string);
            if (!$conn) {
                throw new Exception("Failed to connect: " . error_get_last()['message']);
            }
            
            // Get PostgreSQL version with error handling
            $version_query = @pg_query($conn, "SELECT version()");
            if ($version_query) {
                $version = pg_fetch_result($version_query, 0, 0);
                $result['details']['version'] = $version;
            } else {
                $result['details']['version'] = "<could not determine>";
            }
            
            // Get current user with error handling
            $user_query = @pg_query($conn, "SELECT current_user");
            if ($user_query) {
                $current_user = pg_fetch_result($user_query, 0, 0);
            $result['details']['user'] = $current_user;
            } else {
                $result['details']['user'] = "<could not determine>";
                $current_user = 'unknown';
            }
            
            // Check if user is superuser - with error handling
            try {
                $superuser_query = @pg_query($conn, "SELECT usesuper FROM pg_user WHERE usename = current_user");
                if ($superuser_query && pg_num_rows($superuser_query) > 0) {
                    $is_superuser = pg_fetch_result($superuser_query, 0, 0);
                    $result['details']['is_superuser'] = ($is_superuser === 't') ? 'Yes' : 'No';
                } else {
                    $result['details']['is_superuser'] = 'Unknown';
                }
            } catch (Exception $e) {
                $result['details']['is_superuser'] = 'Unknown';
            }
            
            // Try to get PostgreSQL data directory if we have permission
            try {
                $data_dir_query = @pg_query($conn, "SHOW data_directory");
                if ($data_dir_query) {
                    $data_dir = pg_fetch_result($data_dir_query, 0, 0);
                    $result['details']['data_directory'] = $data_dir;
                } else {
                    $result['details']['data_directory'] = "<permission denied>";
                }
            } catch (Exception $e) {
                $result['details']['data_directory'] = "<error: " . $e->getMessage() . ">";
            }
            
            $result['success'] = true;
            $result['message'] = "Successfully connected to PostgreSQL";
            $result['connection'] = $conn;
        } else if ($type === 'sqlite') {
            // Try to connect
            $path = $config['path'];
            if (!file_exists($path)) {
                throw new Exception("SQLite database file does not exist at: {$path}");
            }
            
            $pdo = new PDO("sqlite:{$path}");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Get SQLite version
            $version = $pdo->query("SELECT sqlite_version()")->fetchColumn();
            $result['details']['version'] = $version;
            
            // Get file permissions
            $perms = fileperms($path);
            $result['details']['file_permissions'] = substr(sprintf('%o', $perms), -4);
            $result['details']['readable'] = is_readable($path) ? 'Yes' : 'No';
            $result['details']['writable'] = is_writable($path) ? 'Yes' : 'No';
            
            $result['success'] = true;
            $result['message'] = "Successfully connected to SQLite";
            $result['connection'] = $pdo;
        }
    } catch (Exception $e) {
        $result['success'] = false;
        $result['message'] = "Connection failed: " . $e->getMessage();
    }
    
    return $result;
}

// Function to check table permissions
function checkTablePermissions($conn, $table) {
    $result = [
        'exists' => false,
        'owner' => '',
        'permissions' => []
    ];
    
    try {
        // Check if table exists
        $exists_query = pg_query($conn, "SELECT to_regclass('public.$table')");
        if (!$exists_query) {
            throw new Exception(pg_last_error($conn));
        }
        
        $exists = pg_fetch_result($exists_query, 0, 0);
        $result['exists'] = !empty($exists);
        
        if ($result['exists']) {
            // Get table owner
            $owner_query = pg_query($conn, "
                SELECT tableowner 
                FROM pg_tables 
                WHERE schemaname = 'public' AND tablename = '$table'
            ");
            
            if (!$owner_query) {
                throw new Exception(pg_last_error($conn));
            }
            
            $result['owner'] = pg_fetch_result($owner_query, 0, 0);
            
            // Check permissions
            $current_user = pg_fetch_result(pg_query($conn, "SELECT current_user"), 0, 0);
            
            // Test SELECT permission
            $select_test = @pg_query($conn, "SELECT COUNT(*) FROM $table LIMIT 1");
            $result['permissions']['select'] = ($select_test !== false);
            
            // Test INSERT permission (careful with this one)
            $can_insert = pg_fetch_result(pg_query($conn, "
                SELECT has_table_privilege('$current_user', '$table', 'INSERT')
            "), 0, 0);
            $result['permissions']['insert'] = ($can_insert === 't');
            
            // Test UPDATE permission
            $can_update = pg_fetch_result(pg_query($conn, "
                SELECT has_table_privilege('$current_user', '$table', 'UPDATE')
            "), 0, 0);
            $result['permissions']['update'] = ($can_update === 't');
            
            // Test DELETE permission
            $can_delete = pg_fetch_result(pg_query($conn, "
                SELECT has_table_privilege('$current_user', '$table', 'DELETE')
            "), 0, 0);
            $result['permissions']['delete'] = ($can_delete === 't');
            
            // Test CREATE INDEX permission - Check for CREATE and INDEX privileges
            try {
                // For CREATE INDEX, we need to check for 'CREATE' privilege on schema
                $can_create_on_schema = @pg_query($conn, "
                    SELECT has_schema_privilege('$current_user', 'public', 'CREATE')
                ");
                
                if ($can_create_on_schema && pg_num_rows($can_create_on_schema) > 0) {
                    $schema_create = pg_fetch_result($can_create_on_schema, 0, 0);
                    $result['permissions']['create_on_schema'] = ($schema_create === 't');
                } else {
                    $result['permissions']['create_on_schema'] = false;
                }
                
                // Also check for ownership or specific INDEX permission
                $is_owner = ($result['owner'] === $current_user);
                $result['permissions']['is_owner'] = $is_owner;
                $result['permissions']['create_index'] = $is_owner || $result['permissions']['create_on_schema'];
            } catch (Exception $e) {
                $result['permissions']['create_index'] = false;
                $result['permissions']['create_on_schema'] = false;
                $result['permissions']['is_owner'] = false;
            }
        }
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

// Function to test specific query
function testQuery($conn, $type, $query, $params = []) {
    $result = [
        'success' => false,
        'message' => '',
        'data' => null,
        'query' => $query
    ];
    
    try {
        if ($type === 'postgresql') {
            if (!empty($params)) {
                $stmt = pg_prepare($conn, "", $query);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . pg_last_error($conn));
                }
                
                $res = pg_execute($conn, "", $params);
            } else {
                $res = pg_query($conn, $query);
            }
            
            if (!$res) {
                throw new Exception("Query failed: " . pg_last_error($conn));
            }
            
            $result['data'] = [];
            while ($row = pg_fetch_assoc($res)) {
                $result['data'][] = $row;
            }
            
            $result['success'] = true;
            $result['message'] = "Query executed successfully";
        } else if ($type === 'sqlite') {
            if ($conn instanceof PDO) {
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . implode(', ', $conn->errorInfo()));
                }
                
                $stmt->execute($params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['success'] = true;
                $result['message'] = "Query executed successfully";
            } else {
                throw new Exception("Invalid SQLite connection");
            }
        }
    } catch (Exception $e) {
        $result['success'] = false;
        $result['message'] = $e->getMessage();
    }
    
    return $result;
}

// Function to generate fix suggestions
function generateFixes($pg_conn, $table, $tableInfo) {
    $fixes = [];
    $current_user = pg_fetch_result(pg_query($pg_conn, "SELECT current_user"), 0, 0);
    
    if ($tableInfo['exists']) {
        // If table exists but current user is not the owner
        if ($tableInfo['owner'] !== $current_user) {
            $fixes[] = [
                'title' => "Change ownership of table '$table'",
                'sql' => "ALTER TABLE $table OWNER TO $current_user;",
                'explanation' => "This will make $current_user the owner of the table, allowing full access."
            ];
        }
        
        // Grant permissions if needed
        if (!$tableInfo['permissions']['select'] || 
            !$tableInfo['permissions']['insert'] || 
            !$tableInfo['permissions']['update'] || 
            !$tableInfo['permissions']['delete']) {
            
            $fixes[] = [
                'title' => "Grant all permissions on '$table'",
                'sql' => "GRANT ALL PRIVILEGES ON TABLE $table TO $current_user;",
                'explanation' => "This will give $current_user full access to the table."
            ];
        }
        
        // Specific fix for index creation
        if (!$tableInfo['permissions']['create_index']) {
            $fixes[] = [
                'title' => "Create index on '$table' using superuser",
                'sql' => "CREATE INDEX IF NOT EXISTS idx_{$table}_user_id ON $table(user_id);",
                'explanation' => "This needs to be run by a superuser or the table owner."
            ];
        }
    } else {
        $fixes[] = [
            'title' => "Table '$table' does not exist",
            'sql' => "-- Run the application's database initialization scripts",
            'explanation' => "The table doesn't exist yet. You need to run the database initialization or migration scripts."
        ];
    }
    
    // Add general fixes
    $fixes[] = [
        'title' => "Grant all permissions on all tables",
        'sql' => "GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO $current_user;",
        'explanation' => "This will give $current_user full access to all existing tables."
    ];
    
    $fixes[] = [
        'title' => "Grant default permissions for future tables",
        'sql' => "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO $current_user;",
        'explanation' => "This ensures that any new tables created will automatically give permissions to $current_user."
    ];
    
    return $fixes;
}

// Function to check the code at the error location
function checkCodeAtError($file, $line) {
    $result = [
        'file' => $file,
        'line' => $line,
        'code' => [],
        'suggestion' => ''
    ];
    
    if (file_exists($file)) {
        $lines = file($file);
        
        // Get 10 lines before and after the error
        $start = max(0, $line - 10);
        $end = min(count($lines), $line + 10);
        
        for ($i = $start; $i < $end; $i++) {
            $result['code'][$i + 1] = rtrim($lines[$i]);
        }
        
        // Simple analysis of the error line
        if (isset($lines[$line - 1])) {
            $errorLine = $lines[$line - 1];
            if (strpos($errorLine, 'CREATE INDEX') !== false) {
                $result['suggestion'] = "This line is trying to create an index on a table that your database user doesn't own. You need to either change the table ownership or add error handling around this call.";
            } else if (strpos($errorLine, 'INSERT INTO') !== false) {
                $result['suggestion'] = "This line is trying to insert data into a table that your database user doesn't have permissions for. You need to grant INSERT privileges.";
            } else if (strpos($errorLine, 'UPDATE') !== false) {
                $result['suggestion'] = "This line is trying to update data in a table that your database user doesn't have permissions for. You need to grant UPDATE privileges.";
            } else if (strpos($errorLine, 'DELETE FROM') !== false) {
                $result['suggestion'] = "This line is trying to delete data from a table that your database user doesn't have permissions for. You need to grant DELETE privileges.";
            }
        }
    } else {
        $result['code'] = ["File not found: $file"];
    }
    
    return $result;
}

// Start HTML output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Debugging Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #2980b9;
            margin-top: 30px;
            border-left: 4px solid #3498db;
            padding-left: 10px;
        }
        h3 {
            color: #16a085;
            margin-top: 20px;
        }
        .section {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
        }
        .error {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .info {
            background-color: #e9ecef;
            border-left: 4px solid #6c757d;
        }
        .content {
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .code-block {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 15px 0;
            border: 1px solid #ddd;
        }
        .line-number {
            color: #888;
            margin-right: 10px;
            user-select: none;
        }
        .error-line {
            background-color: #ffe0e0;
            display: block;
        }
        .fix-panel {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #1976d2;
        }
        .fix-sql {
            background-color: #f1f8e9;
            padding: 10px;
            border-radius: 3px;
            font-family: monospace;
            margin: 10px 0;
            border: 1px solid #ddd;
        }
        .btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            margin-right: 5px;
            font-size: 14px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea {
            height: 100px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Debugging Tool</h1>
        
        <?php
        // Get the error file and line if specified
        $error_file = isset($_GET['file']) ? $_GET['file'] : '/var/www/html/database/enhanced_db_connect.php';
        $error_line = isset($_GET['line']) ? (int)$_GET['line'] : 597;
        
        // Run the code analysis
        $code_analysis = checkCodeAtError($error_file, $error_line);
        
        // Process any SQL fix if requested
        $fix_applied = false;
        $fix_result = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_fix'])) {
            try {
                $fix_sql = $_POST['fix_sql'];
                $pg_conn = pg_connect("host=127.0.0.1 port=5432 dbname=postgres user=postgres password=postgres");
                
                if (!$pg_conn) {
                    throw new Exception("Could not connect to PostgreSQL as superuser");
                }
                
                $result = pg_query($pg_conn, $fix_sql);
                if (!$result) {
                    throw new Exception("Fix failed: " . pg_last_error($pg_conn));
                }
                
                $fix_applied = true;
                $fix_result = "Fix applied successfully!";
            } catch (Exception $e) {
                $fix_result = "Error applying fix: " . $e->getMessage();
            }
        }
        
        // If a fix was just applied, show the result
        if ($fix_applied) {
            outputSection("SQL Fix Result", $fix_result, $fix_applied ? 'success' : 'error');
        }
        
        // 1. PostgreSQL Connection Test
        $pg_test = testConnection('postgresql', $DB_CONFIG['postgresql']);
        outputSection(
            "PostgreSQL Connection Test", 
            $pg_test['success'] ? 
                $pg_test['message'] . '<br><br><strong>Details:</strong><br>' . 
                implode('<br>', array_map(function($k, $v) { return "<strong>{$k}:</strong> {$v}"; }, array_keys($pg_test['details']), $pg_test['details']))
                : 
                $pg_test['message'],
            $pg_test['success'] ? 'success' : 'error'
        );
        
        // 2. SQLite Connection Test
        $sqlite_test = testConnection('sqlite', $DB_CONFIG['sqlite']);
        outputSection(
            "SQLite Connection Test", 
            $sqlite_test['success'] ? 
                $sqlite_test['message'] . '<br><br><strong>Details:</strong><br>' . 
                implode('<br>', array_map(function($k, $v) { return "<strong>{$k}:</strong> {$v}"; }, array_keys($sqlite_test['details']), $sqlite_test['details']))
                : 
                $sqlite_test['message'],
            $sqlite_test['success'] ? 'success' : 'error'
        );
        
        // 3. Table Permissions (if PostgreSQL connection successful)
        if ($pg_test['success']) {
            $tables_to_check = ['notes', 'users', 'database_stats', 'sync_history', 'auto_sync_settings', 'backup_history'];
            $tables_html = '<table><tr><th>Table</th><th>Exists</th><th>Owner</th><th>SELECT</th><th>INSERT</th><th>UPDATE</th><th>DELETE</th><th>CREATE INDEX</th></tr>';
            
            foreach ($tables_to_check as $table) {
                $table_info = checkTablePermissions($pg_test['connection'], $table);
                $exists = $table_info['exists'] ? '✅' : '❌';
                $owner = isset($table_info['owner']) ? $table_info['owner'] : 'N/A';
                
                $select = isset($table_info['permissions']['select']) && $table_info['permissions']['select'] ? '✅' : '❌';
                $insert = isset($table_info['permissions']['insert']) && $table_info['permissions']['insert'] ? '✅' : '❌';
                $update = isset($table_info['permissions']['update']) && $table_info['permissions']['update'] ? '✅' : '❌';
                $delete = isset($table_info['permissions']['delete']) && $table_info['permissions']['delete'] ? '✅' : '❌';
                $create_index = isset($table_info['permissions']['create_index']) && $table_info['permissions']['create_index'] ? '✅' : '❌';
                
                $tables_html .= "<tr>
                    <td>{$table}</td>
                    <td>{$exists}</td>
                    <td>{$owner}</td>
                    <td>{$select}</td>
                    <td>{$insert}</td>
                    <td>{$update}</td>
                    <td>{$delete}</td>
                    <td>{$create_index}</td>
                </tr>";
            }
            
            $tables_html .= '</table>';
            outputSection("Table Permissions", $tables_html);
        }
        
        // 4. Problem Analysis
        if ($pg_test['success']) {
            $notes_info = checkTablePermissions($pg_test['connection'], 'notes');
            $fixes = generateFixes($pg_test['connection'], 'notes', $notes_info);
            
            $fixes_html = '';
            foreach ($fixes as $i => $fix) {
                $fixes_html .= "
                <div class='fix-panel'>
                    <h4>{$fix['title']}</h4>
                    <p>{$fix['explanation']}</p>
                    <div class='fix-sql'>{$fix['sql']}</div>
                    <form method='post'>
                        <input type='hidden' name='fix_sql' value='{$fix['sql']}'>
                        <button type='submit' name='apply_fix' class='btn'>Apply Fix</button>
                    </form>
                </div>";
            }
            
            outputSection("Suggested Fixes for 'notes' Table", $fixes_html);
        }
        
        // 5. Code Analysis
        $code_html = "<p><strong>File:</strong> {$code_analysis['file']}</p>
                      <p><strong>Line:</strong> {$code_analysis['line']}</p>";
        
        if (!empty($code_analysis['suggestion'])) {
            $code_html .= "<p><strong>Suggestion:</strong> {$code_analysis['suggestion']}</p>";
        }
        
        $code_html .= "<div class='code-block'>";
        foreach ($code_analysis['code'] as $num => $line) {
            $class = ($num == $error_line) ? 'error-line' : '';
            $code_html .= "<span class='{$class}'><span class='line-number'>{$num}:</span>" . htmlspecialchars($line) . "</span>\n";
        }
        $code_html .= "</div>";
        
        outputSection("Code at Error Location", $code_html);
        
        // 6. Custom Query Form
        echo "<h2>Run Custom Query</h2>
        <form method='post'>
            <div class='form-group'>
                <label for='db_type'>Database:</label>
                <select name='db_type' id='db_type'>
                    <option value='postgresql'>PostgreSQL</option>
                    <option value='sqlite'>SQLite</option>
                </select>
            </div>
            <div class='form-group'>
                <label for='query'>SQL Query:</label>
                <textarea name='query' id='query' placeholder='Enter your SQL query here...'></textarea>
            </div>
            <button type='submit' name='run_query' class='btn'>Run Query</button>
        </form>";
        
        // Handle custom query
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_query'])) {
            $query = $_POST['query'];
            $db_type = $_POST['db_type'];
            
            if (!empty($query)) {
                $conn = ($db_type === 'postgresql') ? $pg_test['connection'] : $sqlite_test['connection'];
                $query_result = testQuery($conn, $db_type, $query);
                
                $result_html = "<p><strong>Status:</strong> " . ($query_result['success'] ? 'Success' : 'Failed') . "</p>";
                $result_html .= "<p><strong>Message:</strong> {$query_result['message']}</p>";
                
                if ($query_result['success'] && !empty($query_result['data'])) {
                    $result_html .= "<table><tr>";
                    // Headers
                    foreach (array_keys($query_result['data'][0]) as $header) {
                        $result_html .= "<th>{$header}</th>";
                    }
                    $result_html .= "</tr>";
                    
                    // Data
                    foreach ($query_result['data'] as $row) {
                        $result_html .= "<tr>";
                        foreach ($row as $value) {
                            $result_html .= "<td>" . htmlspecialchars($value) . "</td>";
                        }
                        $result_html .= "</tr>";
                    }
                    $result_html .= "</table>";
                }
                
                outputSection("Query Result", $result_html, $query_result['success'] ? 'success' : 'error');
            }
        }
        
        // Close connections
        if ($pg_test['success'] && isset($pg_test['connection'])) {
            pg_close($pg_test['connection']);
        }
        ?>
        
        <div style="margin-top: 30px;">
            <a href="/admin/index.php" class="btn">Return to Admin Panel</a>
        </div>
    </div>
</body>
</html>
<?php
// Flush the output buffer
ob_end_flush();
?>
