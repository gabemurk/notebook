<?php
ini_set('display_errors', 0); // Don't display PHP errors to browser
error_reporting(E_ALL); // Still log all errors

// Set proper content type for JSON response
header('Content-Type: application/json');

// Start output buffering to catch any unexpected output
ob_start();

// Initialize default response
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'notes' => []
];

try {
    // Include the dual database connection
    require_once 'database/db.php';

// Force using the dual database manager
$db_manager = get_db_manager();
$db_manager->initializeDualConnections();
    
    // Start or resume session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verify user is logged in or set a default user_id for demo/testing
    if (!isset($_SESSION['user_id'])) {
        // For demo purposes, use a default user ID if not logged in
        // This allows accessing notes without a formal login
        $_SESSION['user_id'] = 1; // Default demo user
        error_log("Using default user_id=1 for notes loading");
    }
    
    // Set an environment variable to always show all notes for demo purposes
    $SHOW_ALL_NOTES = true;
    
    // Get note_id from query parameters if available
    $note_id = isset($_GET['note_id']) ? intval($_GET['note_id']) : 0;
    
    // Get current database type
    $db_type = get_db_type();
    error_log("Current database type: $db_type");
    
    // Use the database manager to get all notes regardless of connection type
    // This ensures we use the dual database system properly
    
    // First try PostgreSQL
    $db_type = DB_TYPE_POSTGRESQL;
    $db = db_connect_to(DB_TYPE_POSTGRESQL);
    
    // If PostgreSQL fails, fallback to SQLite
    if (!$db) {
        error_log("PostgreSQL connection failed, falling back to SQLite");
        $db_type = DB_TYPE_SQLITE;
        $db = db_connect_to(DB_TYPE_SQLITE);
        
        if (!$db) {
            throw new Exception("Failed to connect to both PostgreSQL and SQLite databases");
        }
    }
    
    error_log("load_notes.php: Successfully connected to $db_type database");
    
    // Debug information
    error_log("load_notes.php: Using user_id={$_SESSION['user_id']} with database type=$db_type");
    
    if ($note_id > 0) {
        // Loading a specific note - use simple query approach
        if ($db_type === DB_TYPE_POSTGRESQL) {
            // Simple PostgreSQL query with proper parameterized query
            $query = "SELECT id, content, 
                    extract(epoch from created_at)::integer as created_at_ts,
                    extract(epoch from updated_at)::integer as updated_at_ts
                    FROM notes WHERE id = $1 AND user_id = $2";
            
            // Use pg_query_params for proper parameter binding
            $result = pg_query_params($db, $query, array($note_id, $_SESSION['user_id']));
            if (!$result) {
                error_log("PostgreSQL specific note query error: " . pg_last_error($db));
                throw new Exception("PostgreSQL query failed: " . pg_last_error($db));
            }
            
            $note = pg_fetch_assoc($result);
            if ($note) {
                // Format dates properly
                $note['created_at'] = date('Y-m-d H:i', intval($note['created_at_ts']));
                $note['updated_at'] = date('Y-m-d H:i', intval($note['updated_at_ts']));
                unset($note['created_at_ts']);
                unset($note['updated_at_ts']);
                
                // Default title
                $note['title'] = 'Untitled Note';
                
                $response['success'] = true;
                $response['message'] = 'Note loaded successfully';
                $response['note'] = $note;
            } else {
                $response['message'] = 'Note not found or access denied';
            }
        } else {
            // SQLite query - check what type of connection we have
            if ($db instanceof PDO) {
                // We have a PDO connection for SQLite
                $query = "SELECT id, content, 
                        strftime('%s', created_at) as created_at_ts,
                        strftime('%s', updated_at) as updated_at_ts
                        FROM notes WHERE id = ? AND user_id = ?";
                
                $stmt = $db->prepare($query);
                $stmt->execute([$note_id, $_SESSION['user_id']]);
                $note = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // For backward compatibility - try using db_execute function
                $query = "SELECT id, content, 
                        strftime('%s', created_at) as created_at_ts,
                        strftime('%s', updated_at) as updated_at_ts
                        FROM notes WHERE id = ? AND user_id = ?";
                
                $result = db_execute($query, [$note_id, $_SESSION['user_id']], true);
                if (is_array($result) && count($result) > 0) {
                    $note = $result[0];
                } else {
                    $note = false;
                }
            }
            
            if ($note) {
                // Format dates properly
                $note['created_at'] = date('Y-m-d H:i', intval($note['created_at_ts']));
                $note['updated_at'] = date('Y-m-d H:i', intval($note['updated_at_ts']));
                unset($note['created_at_ts']);
                unset($note['updated_at_ts']);
                
                // Default title
                $note['title'] = 'Untitled Note';
                
                $response['success'] = true;
                $response['message'] = 'Note loaded successfully';
                $response['note'] = $note;
            } else {
                $response['message'] = 'Note not found or access denied';
            }
        }
    } else {
        // Loading all notes
        if ($db_type === DB_TYPE_POSTGRESQL) {
            // Simple PostgreSQL query for all notes - using native pg functions, not PDO
            // Use parameter binding the PostgreSQL way with pg_query_params
            // Query that only shows notes for the current user
            $query = "SELECT id, substring(content, 1, 150) as preview, 
                    extract(epoch from created_at)::integer as created_at_ts,
                    extract(epoch from updated_at)::integer as updated_at_ts
                    FROM notes WHERE user_id = $1
                    ORDER BY updated_at DESC";
            
            error_log("PostgreSQL: fetching notes for user_id = {$_SESSION['user_id']}");
            // Using proper parameterized query for PostgreSQL
            $result = pg_query_params($db, $query, array($_SESSION['user_id']));
            
            // Debug the query result
            if (!$result) {
                error_log("PostgreSQL query error: " . pg_last_error($db));
            } else {
                $row_count = pg_num_rows($result);
                error_log("PostgreSQL: found $row_count notes");
            }
            
            // Query is executed in the condition block above
            if (!$result) {
                error_log("PostgreSQL query error: " . pg_last_error($db));
                throw new Exception("PostgreSQL query failed: " . pg_last_error($db));
            }
            
            $notes = [];
            while ($note = pg_fetch_assoc($result)) {
                // Format dates properly
                $note['created_at'] = date('Y-m-d H:i', intval($note['created_at_ts']));
                $note['updated_at'] = date('Y-m-d H:i', intval($note['updated_at_ts']));
                unset($note['created_at_ts']);
                unset($note['updated_at_ts']);
                
                // Add default title
                $note['title'] = 'Untitled Note';
                
                $notes[] = $note;
            }
            
            $response['success'] = true;
            $response['message'] = count($notes) > 0 ? 'Notes loaded successfully' : 'No notes found';
            $response['notes'] = $notes;
        } else {
            // SQLite query for all notes - check what type of connection we have
            if ($db instanceof PDO) {
                // We have a PDO connection for SQLite
                // Only show notes for the current user
                $query = "SELECT id, substr(content, 1, 150) as preview, 
                        strftime('%s', created_at) as created_at_ts,
                        strftime('%s', updated_at) as updated_at_ts
                        FROM notes WHERE user_id = ?
                        ORDER BY updated_at DESC";
                
                $stmt = $db->prepare($query);
                $stmt->execute([$_SESSION['user_id']]);
                error_log("SQLite: fetching notes for user_id = {$_SESSION['user_id']}");
                
                // Debug the query result
                $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("SQLite: found " . count($notes) . " notes");
                $stmt->closeCursor();
                
                // Re-execute to get the actual notes
                $stmt = $db->prepare($query);
                $stmt->execute();
                $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // For backward compatibility - try using db_execute function
                $query = "SELECT id, substr(content, 1, 150) as preview, 
                        strftime('%s', created_at) as created_at_ts,
                        strftime('%s', updated_at) as updated_at_ts
                        FROM notes WHERE user_id = ? 
                        ORDER BY updated_at DESC";
                
                $result = db_execute($query, [$_SESSION['user_id']], true);
                if (!$result) {
                    throw new Exception("SQLite query failed: Failed to execute query");
                }
                $notes = $result;
            }
            
            // Process each note
            foreach ($notes as &$note) {
                // Format dates properly
                $note['created_at'] = date('Y-m-d H:i', $note['created_at_ts']);
                $note['updated_at'] = date('Y-m-d H:i', $note['updated_at_ts']);
                unset($note['created_at_ts']);
                unset($note['updated_at_ts']);
                
                // Add default title
                $note['title'] = 'Untitled Note';
            }
            
            $response['success'] = true;
            $response['message'] = count($notes) > 0 ? 'Notes loaded successfully' : 'No notes found';
            $response['notes'] = $notes;
        }
    }
} catch (PDOException $e) {
    // Handle database connection errors
    error_log('Load notes PDO error: ' . $e->getMessage());
    $response['message'] = 'Database connection error: ' . $e->getMessage();
} catch (Exception $e) {
    // Handle general exceptions
    error_log('Load notes error: ' . $e->getMessage());
    if ($e->getMessage() !== 'Not logged in') {
        $response['message'] = 'Error loading notes: ' . $e->getMessage();
    }
} catch (Error $e) {
    // Handle PHP errors
    error_log('Load notes PHP error: ' . $e->getMessage());
    $response['message'] = 'Server error while loading notes: ' . $e->getMessage();
} finally {
    // Capture and discard any unexpected output
    $unexpected_output = ob_get_clean();
    if ($unexpected_output) {
        error_log('Unexpected output in load_notes.php: ' . $unexpected_output);
    }
    
    // Ensure response has the correct structure
    if (!isset($response['success'])) {
        $response['success'] = false;
    }
    
    if (!isset($response['message'])) {
        $response['message'] = 'Unknown error occurred';
    }
    
    if (!isset($response['notes']) && !isset($response['note'])) {
        $response['notes'] = [];
    }
    
    // Add debug info to response
    $response['debug'] = [
        'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set',
        'db_type' => $db_type,
        'show_all_notes' => $SHOW_ALL_NOTES,
        'note_count' => isset($response['notes']) ? count($response['notes']) : 0
    ];
    
    // Log response for debugging
    error_log('Notes response: ' . json_encode($response));
    
    // Output the JSON response
    echo json_encode($response);
}
?>
