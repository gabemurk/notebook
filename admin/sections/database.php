<?php
/**
 * Admin Dashboard - Database Management Section
 */

// Get database manager instance
$db = get_db_manager();
$pg_conn = db_connect_to(DB_TYPE_POSTGRESQL);
$sqlite_conn = db_connect_to(DB_TYPE_SQLITE);

// Handle database operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $table = $_POST['table'] ?? '';
    $id = $_POST['id'] ?? '';
    
    try {
        switch ($action) {
            case 'delete_record':
                if ($table && $id) {
                    $query = "DELETE FROM " . $table . " WHERE id = ?";
                    db_execute($query, [$id]);
                    $_SESSION['db_message'] = "Record deleted successfully";
                    $_SESSION['db_message_type'] = "success";
                }
                break;
                
            case 'edit_record':
                if ($table && $id) {
                    $fields = [];
                    $values = [];
                    $updates = [];
                    
                    // Get all fields except id, action, and table
                    foreach ($_POST as $key => $value) {
                        if (!in_array($key, ['id', 'action', 'table'])) {
                            $updates[] = $key . " = ?";
                            $values[] = $value;
                        }
                    }
                    
                    if (!empty($updates)) {
                        $values[] = $id; // Add id for WHERE clause
                        $query = "UPDATE " . $table . " SET " . implode(", ", $updates) . " WHERE id = ?";
                        db_execute($query, $values);
                        $_SESSION['db_message'] = "Record updated successfully";
                        $_SESSION['db_message_type'] = "success";
                    }
                }
                break;
                
            case 'add_record':
                if ($table) {
                    $fields = [];
                    $placeholders = [];
                    $values = [];
                    
                    // Get all fields except action and table
                    foreach ($_POST as $key => $value) {
                        if (!in_array($key, ['action', 'table'])) {
                            $fields[] = $key;
                            $placeholders[] = "?";
                            $values[] = $value;
                        }
                    }
                    
                    if (!empty($fields)) {
                        $query = "INSERT INTO " . $table . " (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
                        db_execute($query, $values);
                        $_SESSION['db_message'] = "Record added successfully";
                        $_SESSION['db_message_type'] = "success";
                    }
                }
                break;
                
            case 'truncate_table':
                if ($table) {
                    $query = "TRUNCATE TABLE " . $table;
                    db_execute($query);
                    $_SESSION['db_message'] = "Table truncated successfully";
                    $_SESSION['db_message_type'] = "success";
                }
                break;
        }
    } catch (Exception $e) {
        $_SESSION['db_message'] = "Error: " . $e->getMessage();
        $_SESSION['db_message_type'] = "danger";
    }
    
    // Redirect to prevent form resubmission
    header("Location: ?section=database&table=" . urlencode($table));
    exit;
}

// Get database information for PostgreSQL
$pg_info = [];
if ($pg_conn) {
    try {
        // Get PostgreSQL version
        $stmt = $pg_conn->query("SELECT version()");
        $pg_version = $stmt ? $stmt->fetchColumn() : 'Unknown';
        
        // Get PostgreSQL connection details
        $pg_info = [
            'version' => $pg_version,
            'status' => 'Connected',
            'tables' => []
        ];
        
        // Get table information
        $tables_query = $pg_conn->query("
            SELECT 
                table_name,
                (SELECT count(*) FROM information_schema.columns WHERE table_name = t.table_name) as column_count
            FROM information_schema.tables t
            WHERE table_schema = 'public'
            ORDER BY table_name
        ");
        
        if ($tables_query) {
            while ($row = $tables_query->fetch(PDO::FETCH_ASSOC)) {
                // For each table, get row count
                $count_stmt = $pg_conn->query("SELECT COUNT(*) FROM " . $row['table_name']);
                $row['row_count'] = $count_stmt ? $count_stmt->fetchColumn() : 'N/A';
                
                // Get column information
                $columns_query = $pg_conn->query("
                    SELECT column_name, data_type, is_nullable, column_default
                    FROM information_schema.columns
                    WHERE table_name = '" . $row['table_name'] . "'
                    ORDER BY ordinal_position
                ");
                $row['columns'] = $columns_query->fetchAll(PDO::FETCH_ASSOC);
                
                $pg_info['tables'][] = $row;
            }
        }
    } catch (Exception $e) {
        $pg_info['error'] = $e->getMessage();
    }
}

// Get database information for SQLite
$sqlite_info = [];
if ($sqlite_conn) {
    try {
        // Get SQLite version
        $stmt = $sqlite_conn->query("SELECT sqlite_version()");
        $sqlite_version = $stmt ? $stmt->fetchColumn() : 'Unknown';
        
        $sqlite_info = [
            'version' => $sqlite_version,
            'status' => 'Connected',
            'tables' => []
        ];
        
        // Get table information
        $tables_query = $sqlite_conn->query("
            SELECT name as table_name
            FROM sqlite_master 
            WHERE type='table' AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ");
        
        if ($tables_query) {
            while ($row = $tables_query->fetch(PDO::FETCH_ASSOC)) {
                // For each table, get row count
                $count_stmt = $sqlite_conn->query("SELECT COUNT(*) FROM " . $row['table_name']);
                $row['row_count'] = $count_stmt ? $count_stmt->fetchColumn() : 'N/A';
                
                // Get column information
                $columns_query = $sqlite_conn->query("PRAGMA table_info(" . $row['table_name'] . ")");
                $row['columns'] = [];
                while ($col = $columns_query->fetch(PDO::FETCH_ASSOC)) {
                    $row['columns'][] = [
                        'column_name' => $col['name'],
                        'data_type' => $col['type'],
                        'is_nullable' => $col['notnull'] ? 'NO' : 'YES',
                        'column_default' => $col['dflt_value']
                    ];
                }
                
                $sqlite_info['tables'][] = $row;
            }
        }
    } catch (Exception $e) {
        $sqlite_info['error'] = $e->getMessage();
    }
}

// Get current table data if specified
$current_table = $_GET['table'] ?? '';
$table_data = [];
$table_columns = [];

if ($current_table) {
    try {
        // Get column information
        if ($pg_conn) {
            $columns_query = $pg_conn->query("
                SELECT column_name, data_type, is_nullable, column_default
                FROM information_schema.columns
                WHERE table_name = '" . $current_table . "'
                ORDER BY ordinal_position
            ");
            $table_columns = $columns_query->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Get table data
        $query = "SELECT * FROM " . $current_table . " ORDER BY id DESC LIMIT 100";
        $stmt = db_execute($query, [], true);
        $table_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $_SESSION['db_message'] = "Error: " . $e->getMessage();
        $_SESSION['db_message_type'] = "danger";
    }
}

function formatNumber($n) {
    return number_format($n);
}
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-icon {
    font-size: 2rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.stat-title {
    font-size: 1.1rem;
    color: var(--secondary);
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.stat-desc {
    color: var(--secondary);
    font-size: 0.9rem;
}

.table-actions {
    white-space: nowrap;
}

.table-actions .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 1rem;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header h4 {
    margin: 0;
}

.modal-body {
    padding: 1rem;
}

.modal-footer {
    padding: 1rem;
    border-top: 1px solid #eee;
    text-align: right;
}

.close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    color: var(--secondary);
}

.close:hover {
    color: var(--dark);
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--secondary);
}

.form-control {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.btn-group {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-warning {
    background: var(--warning);
    color: var(--dark);
}

.alert {
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.table-responsive {
    overflow-x: auto;
    margin: 1rem 0;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.nav-tabs {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    border-bottom: 1px solid #dee2e6;
}

.nav-tabs li {
    margin-bottom: -1px;
}

.nav-tabs a {
    display: block;
    padding: 0.5rem 1rem;
    color: var(--secondary);
    text-decoration: none;
    border: 1px solid transparent;
    border-top-left-radius: 0.25rem;
    border-top-right-radius: 0.25rem;
}

.nav-tabs a:hover {
    border-color: #e9ecef #e9ecef #dee2e6;
}

.nav-tabs a.active {
    color: var(--primary);
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
}
</style>

<div class="container">
    <h2>Database Management</h2>
    
    <?php if (isset($_SESSION['db_message'])): ?>
        <div class="alert alert-<?= $_SESSION['db_message_type'] ?>">
            <?= htmlspecialchars($_SESSION['db_message']) ?>
        </div>
        <?php unset($_SESSION['db_message'], $_SESSION['db_message_type']); ?>
    <?php endif; ?>
    
    <ul class="nav-tabs">
        <li><a href="#postgresql" class="active" onclick="showTab(event, 'postgresql')">PostgreSQL</a></li>
        <li><a href="#sqlite" onclick="showTab(event, 'sqlite')">SQLite</a></li>
    </ul>
    
    <!-- PostgreSQL Tab -->
    <div id="postgresql" class="tab-content active">
        <div class="card">
            <div class="card-header">
                <h3>PostgreSQL Tables</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Rows</th>
                            <th>Columns</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pg_info['tables'] as $table): ?>
                            <tr>
                                <td><?= htmlspecialchars($table['table_name']) ?></td>
                                <td><?= formatNumber($table['row_count']) ?></td>
                                <td><?= formatNumber($table['column_count']) ?></td>
                                <td class="table-actions">
                                    <a href="?section=database&table=<?= urlencode($table['table_name']) ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-table"></i> View Data
                                    </a>
                                    <button onclick="showAddRecord('<?= $table['table_name'] ?>')" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                    <button onclick="confirmTruncate('<?= $table['table_name'] ?>')" class="btn btn-sm btn-warning">
                                        <i class="fas fa-trash-alt"></i> Truncate
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- SQLite Tab -->
    <div id="sqlite" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3>SQLite Tables</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Rows</th>
                            <th>Columns</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sqlite_info['tables'] as $table): ?>
                            <tr>
                                <td><?= htmlspecialchars($table['table_name']) ?></td>
                                <td><?= formatNumber($table['row_count']) ?></td>
                                <td><?= count($table['columns']) ?></td>
                                <td class="table-actions">
                                    <a href="?section=database&table=<?= urlencode($table['table_name']) ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-table"></i> View Data
                                    </a>
                                    <button onclick="showAddRecord('<?= $table['table_name'] ?>')" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                    <button onclick="confirmTruncate('<?= $table['table_name'] ?>')" class="btn btn-sm btn-warning">
                                        <i class="fas fa-trash-alt"></i> Truncate
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if ($current_table): ?>
        <div class="card">
            <div class="card-header">
                <div style="float: right;">
                    <button onclick="showAddRecord('<?= htmlspecialchars($current_table) ?>')" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Record
                    </button>
                </div>
                <h3>Table: <?= htmlspecialchars($current_table) ?></h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <?php foreach ($table_columns as $column): ?>
                                <th><?= htmlspecialchars($column['column_name']) ?></th>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($table_data as $row): ?>
                            <tr>
                                <?php foreach ($table_columns as $column): ?>
                                    <td><?= htmlspecialchars($row[$column['column_name']] ?? '') ?></td>
                                <?php endforeach; ?>
                                <td class="table-actions">
                                    <button onclick="showEditRecord('<?= htmlspecialchars($current_table) ?>', <?= htmlspecialchars(json_encode($row)) ?>)" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button onclick="confirmDelete('<?= htmlspecialchars($current_table) ?>', <?= $row['id'] ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Record Modal -->
<div id="recordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="modalTitle">Add Record</h4>
            <button type="button" class="close" onclick="closeModal('recordModal')">&times;</button>
        </div>
        <form id="recordForm" method="post">
            <input type="hidden" name="action" id="formAction" value="add_record">
            <input type="hidden" name="table" id="formTable" value="">
            <input type="hidden" name="id" id="formId" value="">
            
            <div class="modal-body" id="modalBody">
                <!-- Form fields will be added here dynamically -->
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('recordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Confirm Delete</h4>
            <button type="button" class="close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="delete_record">
            <input type="hidden" name="table" id="deleteTable" value="">
            <input type="hidden" name="id" id="deleteId" value="">
            
            <div class="modal-body">
                <p>Are you sure you want to delete this record? This action cannot be undone.</p>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Truncate Confirmation Modal -->
<div id="truncateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Confirm Truncate</h4>
            <button type="button" class="close" onclick="closeModal('truncateModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="truncate_table">
            <input type="hidden" name="table" id="truncateTable" value="">
            
            <div class="modal-body">
                <p>Are you sure you want to truncate this table? This will delete ALL records and cannot be undone.</p>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('truncateModal')">Cancel</button>
                <button type="submit" class="btn btn-warning">Truncate</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(event, tabId) {
    event.preventDefault();
    
    // Update active tab
    document.querySelectorAll('.nav-tabs a').forEach(a => a.classList.remove('active'));
    event.target.classList.add('active');
    
    // Show selected content
    document.querySelectorAll('.tab-content').forEach(div => div.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
}

function showModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

function showAddRecord(table) {
    document.getElementById('modalTitle').textContent = 'Add Record';
    document.getElementById('formAction').value = 'add_record';
    document.getElementById('formTable').value = table;
    document.getElementById('formId').value = '';
    
    // Get column information and build form
    let columns = <?= json_encode(array_merge($pg_info['tables'], $sqlite_info['tables'])) ?>;
    let tableInfo = columns.find(t => t.table_name === table);
    
    if (tableInfo) {
        let html = '';
        tableInfo.columns.forEach(column => {
            if (column.column_name !== 'id') {
                html += `
                    <div class="form-group">
                        <label for="${column.column_name}">${column.column_name}</label>
                        <input type="text" class="form-control" id="${column.column_name}" 
                               name="${column.column_name}" 
                               ${column.is_nullable === 'NO' ? 'required' : ''}>
                        <small class="form-text text-muted">
                            Type: ${column.data_type}
                            ${column.is_nullable === 'NO' ? ' (Required)' : ''}
                            ${column.column_default ? ' Default: ' + column.column_default : ''}
                        </small>
                    </div>
                `;
            }
        });
        document.getElementById('modalBody').innerHTML = html;
    }
    
    showModal('recordModal');
}

function showEditRecord(table, data) {
    document.getElementById('modalTitle').textContent = 'Edit Record';
    document.getElementById('formAction').value = 'edit_record';
    document.getElementById('formTable').value = table;
    document.getElementById('formId').value = data.id;
    
    // Get column information and build form
    let columns = <?= json_encode(array_merge($pg_info['tables'], $sqlite_info['tables'])) ?>;
    let tableInfo = columns.find(t => t.table_name === table);
    
    if (tableInfo) {
        let html = '';
        tableInfo.columns.forEach(column => {
            if (column.column_name !== 'id') {
                html += `
                    <div class="form-group">
                        <label for="${column.column_name}">${column.column_name}</label>
                        <input type="text" class="form-control" id="${column.column_name}" 
                               name="${column.column_name}" 
                               value="${data[column.column_name] || ''}"
                               ${column.is_nullable === 'NO' ? 'required' : ''}>
                        <small class="form-text text-muted">
                            Type: ${column.data_type}
                            ${column.is_nullable === 'NO' ? ' (Required)' : ''}
                            ${column.column_default ? ' Default: ' + column.column_default : ''}
                        </small>
                    </div>
                `;
            }
        });
        document.getElementById('modalBody').innerHTML = html;
    }
    
    showModal('recordModal');
}

function confirmDelete(table, id) {
    document.getElementById('deleteTable').value = table;
    document.getElementById('deleteId').value = id;
    showModal('deleteModal');
}

function confirmTruncate(table) {
    document.getElementById('truncateTable').value = table;
    showModal('truncateModal');
}
</script>
