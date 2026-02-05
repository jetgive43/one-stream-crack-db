<?php
/**
 * Secure PostgreSQL Database Viewer with Basic Authentication
 */

// ============================================
// CONFIGURATION - CHANGE THESE VALUES!
// ============================================
$AUTH_USERNAME = 'admin';  // Change this!
$AUTH_PASSWORD = 'changeme123';  // Change this to a strong password!
// ============================================

// Simple session-based authentication
session_start();

// Handle login
if (isset($_POST['login'])) {
    if ($_POST['username'] === $AUTH_USERNAME && $_POST['password'] === $AUTH_PASSWORD) {
        $_SESSION['authenticated'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    // Show login form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Viewer - Login</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f5f5f5;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .login-box {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 400px;
            }
            h1 {
                margin: 0 0 30px 0;
                color: #333;
                font-size: 24px;
            }
            .form-group {
                margin-bottom: 20px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                color: #666;
                font-size: 14px;
            }
            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                box-sizing: border-box;
            }
            button {
                width: 100%;
                padding: 12px;
                background: #3498db;
                color: white;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                cursor: pointer;
            }
            button:hover {
                background: #2980b9;
            }
            .error {
                color: #d32f2f;
                margin-bottom: 20px;
                padding: 10px;
                background: #ffebee;
                border-radius: 4px;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 Database Viewer</h1>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" name="login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// User is authenticated, load the viewer
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Handle AJAX requests (same as db_viewer.php)
$action = $_GET['action'] ?? $_POST['action'] ?? null;
if ($action) {
    // Start output buffering if not already started
    if (!ob_get_level()) {
        ob_start();
    } else {
        ob_clean();
    }
    header('Content-Type: application/json');
    
    try {
        switch ($action) {
            case 'get_tables':
                $tables = DB::select("
                    SELECT table_name 
                    FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_type = 'BASE TABLE'
                    ORDER BY table_name
                ");
                echo json_encode(array_map(function($t) { return $t->table_name; }, $tables));
                exit;
                
            case 'get_table_data':
                $tableName = $_GET['table'] ?? '';
                $page = (int)($_GET['page'] ?? 1);
                $perPage = (int)($_GET['per_page'] ?? 50);
                $search = $_GET['search'] ?? '';
                
                if (empty($tableName)) {
                    throw new Exception('Table name is required');
                }
                
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
                    throw new Exception('Invalid table name');
                }
                
                $query = DB::table($tableName);
                
                if (!empty($search)) {
                    $columns = DB::select("
                        SELECT column_name, data_type
                        FROM information_schema.columns
                        WHERE table_schema = 'public' AND table_name = ?
                    ", [$tableName]);
                    
                    $searchConditions = [];
                    foreach ($columns as $col) {
                        if (in_array($col->data_type, ['character varying', 'text', 'character'])) {
                            $searchConditions[] = $col->column_name . "::text ILIKE ?";
                        }
                    }
                    
                    if (!empty($searchConditions)) {
                        $query->whereRaw('(' . implode(' OR ', $searchConditions) . ')', ['%' . $search . '%']);
                    }
                }
                
                $total = $query->count();
                $offset = ($page - 1) * $perPage;
                $data = $query->limit($perPage)->offset($offset)->get();
                
                $columns = DB::select("
                    SELECT column_name, data_type
                    FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = ?
                    ORDER BY ordinal_position
                ", [$tableName]);
                
                $columnNames = array_map(function($col) {
                    return $col->column_name;
                }, $columns);
                
                echo json_encode([
                    'data' => $data,
                    'columns' => $columnNames,
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage)
                ]);
                exit;
                
            case 'get_table_info':
                $tableName = $_GET['table'] ?? '';
                
                if (empty($tableName)) {
                    throw new Exception('Table name is required');
                }
                
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
                    throw new Exception('Invalid table name');
                }
                
                $rowCount = DB::table($tableName)->count();
                
                $columns = DB::select("
                    SELECT 
                        column_name,
                        data_type,
                        character_maximum_length,
                        is_nullable,
                        column_default
                    FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = ?
                    ORDER BY ordinal_position
                ", [$tableName]);
                
                echo json_encode([
                    'row_count' => $rowCount,
                    'columns' => $columns
                ]);
                exit;
                
            case 'get_primary_keys':
                $tableName = $_GET['table'] ?? '';
                
                if (empty($tableName)) {
                    throw new Exception('Table name is required');
                }
                
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
                    throw new Exception('Invalid table name');
                }
                
                // Get primary key columns
                $primaryKeys = DB::select("
                    SELECT a.attname as column_name
                    FROM pg_index i
                    JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                    WHERE i.indrelid = ?::regclass
                    AND i.indisprimary
                ", [$tableName]);
                
                $pkColumns = array_map(function($pk) {
                    return $pk->column_name;
                }, $primaryKeys);
                
                echo json_encode(['primary_keys' => $pkColumns]);
                exit;
                
            case 'update_record':
                $tableName = $_POST['table'] ?? '';
                $data = json_decode($_POST['data'] ?? '{}', true);
                $where = json_decode($_POST['where'] ?? '{}', true);
                
                if (empty($tableName)) {
                    throw new Exception('Table name is required');
                }
                
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
                    throw new Exception('Invalid table name');
                }
                
                if (empty($where)) {
                    throw new Exception('Where clause is required');
                }
                
                // Validate column names
                foreach (array_keys($data) as $col) {
                    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
                        throw new Exception('Invalid column name: ' . $col);
                    }
                }
                
                foreach (array_keys($where) as $col) {
                    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
                        throw new Exception('Invalid column name in where: ' . $col);
                    }
                }
                
                // Build update query
                $query = DB::table($tableName);
                foreach ($where as $col => $val) {
                    $query->where($col, $val);
                }
                
                $affected = $query->update($data);
                
                echo json_encode(['success' => true, 'affected_rows' => $affected]);
                exit;
                
            case 'delete_record':
                $tableName = $_POST['table'] ?? '';
                $where = json_decode($_POST['where'] ?? '{}', true);
                
                if (empty($tableName)) {
                    throw new Exception('Table name is required');
                }
                
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
                    throw new Exception('Invalid table name');
                }
                
                if (empty($where)) {
                    throw new Exception('Where clause is required');
                }
                
                // Validate column names
                foreach (array_keys($where) as $col) {
                    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
                        throw new Exception('Invalid column name: ' . $col);
                    }
                }
                
                // Build delete query
                $query = DB::table($tableName);
                foreach ($where as $col => $val) {
                    $query->where($col, $val);
                }
                
                $affected = $query->delete();
                
                echo json_encode(['success' => true, 'affected_rows' => $affected]);
                exit;
                
            case 'insert_record':
                $tableName = $_POST['table'] ?? '';
                $data = json_decode($_POST['data'] ?? '{}', true);
                
                if (empty($tableName)) {
                    throw new Exception('Table name is required');
                }
                
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
                    throw new Exception('Invalid table name');
                }
                
                // Validate column names
                foreach (array_keys($data) as $col) {
                    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
                        throw new Exception('Invalid column name: ' . $col);
                    }
                }
                
                $id = DB::table($tableName)->insertGetId($data);
                
                echo json_encode(['success' => true, 'id' => $id]);
                exit;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Get database name
$config = require __DIR__.'/../bootstrap/config.php';
$dbName = $config['db_database'] ?? 'streaming_db';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Viewer - <?php echo htmlspecialchars($dbName); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 20px;
            font-weight: 500;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            display: flex;
            height: calc(100vh - 60px);
        }
        
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
            padding: 15px;
        }
        
        .sidebar h2 {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 10px;
            padding: 0 10px;
        }
        
        .table-list {
            list-style: none;
        }
        
        .table-item {
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 4px;
            margin-bottom: 2px;
            transition: background 0.2s;
            font-size: 14px;
        }
        
        .table-item:hover {
            background: #f0f0f0;
        }
        
        .table-item.active {
            background: #3498db;
            color: white;
        }
        
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .toolbar {
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .search-box {
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .table-info {
            color: #666;
            font-size: 14px;
        }
        
        .content-area {
            flex: 1;
            overflow: auto;
            background: white;
            padding: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
        }
        
        .pagination-info {
            color: #666;
            font-size: 14px;
        }
        
        .pagination-controls {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn:hover:not(:disabled) {
            background: #f0f0f0;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: #2980b9;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .cell-content {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            position: relative;
        }
        
        .cell-content.truncated {
            color: #3498db;
        }
        
        .cell-content.truncated:hover {
            text-decoration: underline;
        }
        
        .cell-null {
            color: #999;
            font-style: italic;
        }
        
        .per-page-select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn-edit, .btn-delete {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 0 2px;
            background: transparent;
        }
        
        .btn-edit:hover {
            background: #e3f2fd;
        }
        
        .btn-delete:hover {
            background: #ffebee;
        }
        
        .edit-form-group {
            margin-bottom: 15px;
        }
        
        .edit-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .edit-form-group input,
        .edit-form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .edit-form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .edit-form-group input[readonly] {
            background: #f5f5f5;
            color: #666;
        }
        
        /* Modal for full content */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.2s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            animation: slideDown 0.3s;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            line-height: 30px;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 20px;
            overflow: auto;
            flex: 1;
        }
        
        .modal-body pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.5;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-copy {
            padding: 8px 16px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-copy:hover {
            background: #2980b9;
        }
        
        .field-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 8px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Database Viewer - <?php echo htmlspecialchars($dbName); ?></h1>
        <a href="?logout=1" class="logout-btn">Logout</a>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <h2>Tables</h2>
            <ul class="table-list" id="tableList">
                <li class="loading">Loading tables...</li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="toolbar" id="toolbar" style="display: none;">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search in table...">
                </div>
                <div class="table-info" id="tableInfo"></div>
                <button class="btn btn-primary" onclick="addNewRecord()" style="margin-left: auto;">+ Add New</button>
            </div>
            
            <div class="content-area" id="contentArea">
                <div class="empty-state">
                    <h3>Select a table to view data</h3>
                    <p>Click on a table name from the left menu</p>
                </div>
            </div>
            
            <div class="pagination" id="pagination" style="display: none;">
                <div class="pagination-info" id="paginationInfo"></div>
                <div class="pagination-controls">
                    <button class="btn" id="prevBtn" onclick="changePage(-1)">Previous</button>
                    <span id="pageInfo" style="padding: 0 15px; font-size: 14px;"></span>
                    <button class="btn" id="nextBtn" onclick="changePage(1)">Next</button>
                    <select class="per-page-select" id="perPageSelect" onchange="changePerPage()">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal for full content -->
    <div id="contentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalColumn">Column Name</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="field-label">Full Content:</div>
                <pre id="modalContent"></pre>
            </div>
            <div class="modal-footer">
                <button class="btn-copy" onclick="copyToClipboard()">Copy to Clipboard</button>
                <button class="btn" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentTable = null;
        let currentPage = 1;
        let perPage = 50;
        let totalPages = 1;
        let searchTimeout = null;
        let primaryKeys = [];
        let tableColumns = [];
        
        fetch('?action=get_tables')
            .then(res => res.json())
            .then(tables => {
                const list = document.getElementById('tableList');
                list.innerHTML = '';
                
                if (tables.length === 0) {
                    list.innerHTML = '<li style="padding: 15px; color: #999;">No tables found</li>';
                    return;
                }
                
                tables.forEach(table => {
                    const li = document.createElement('li');
                    li.className = 'table-item';
                    li.textContent = table;
                    li.onclick = () => loadTable(table);
                    list.appendChild(li);
                });
            })
            .catch(err => {
                document.getElementById('tableList').innerHTML = 
                    '<li style="padding: 15px; color: #d32f2f;">Error loading tables</li>';
            });
        
        function loadTable(tableName) {
            currentTable = tableName;
            currentPage = 1;
            
            document.querySelectorAll('.table-item').forEach(item => {
                item.classList.remove('active');
                if (item.textContent === tableName) {
                    item.classList.add('active');
                }
            });
            
            document.getElementById('toolbar').style.display = 'flex';
            document.getElementById('pagination').style.display = 'flex';
            
            loadTableInfo(tableName);
            loadPrimaryKeys(tableName);
            loadTableData(tableName, currentPage, perPage);
        }
        
        function loadPrimaryKeys(tableName) {
            fetch(`?action=get_primary_keys&table=${encodeURIComponent(tableName)}`)
                .then(res => res.json())
                .then(data => {
                    primaryKeys = data.primary_keys || [];
                })
                .catch(err => {
                    console.error('Error loading primary keys:', err);
                    primaryKeys = [];
                });
        }
        
        function loadTableInfo(tableName) {
            fetch(`?action=get_table_info&table=${encodeURIComponent(tableName)}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('tableInfo').textContent = 
                        `${tableName} (${data.row_count.toLocaleString()} rows, ${data.columns.length} columns)`;
                })
                .catch(err => {
                    console.error('Error loading table info:', err);
                });
        }
        
        function loadTableData(tableName, page, perPage, search = '') {
            const contentArea = document.getElementById('contentArea');
            contentArea.innerHTML = '<div class="loading">Loading data...</div>';
            
            let url = `?action=get_table_data&table=${encodeURIComponent(tableName)}&page=${page}&per_page=${perPage}`;
            if (search) {
                url += `&search=${encodeURIComponent(search)}`;
            }
            
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        contentArea.innerHTML = `<div class="empty-state"><h3>Error</h3><p>${data.error}</p></div>`;
                        return;
                    }
                    
                    currentPage = data.page;
                    totalPages = data.total_pages;
                    tableColumns = data.columns;
                    
                    renderTable(data.columns, data.data);
                    updatePagination(data);
                })
                .catch(err => {
                    contentArea.innerHTML = `<div class="empty-state"><h3>Error</h3><p>${err.message}</p></div>`;
                });
        }
        
        function renderTable(columns, data) {
            const contentArea = document.getElementById('contentArea');
            
            if (data.length === 0) {
                contentArea.innerHTML = '<div class="empty-state"><h3>No data found</h3><p>This table is empty or no results match your search</p></div>';
                return;
            }
            
            let html = '<div class="table-wrapper"><table><thead><tr>';
            columns.forEach(col => {
                html += `<th>${escapeHtml(col)}</th>`;
            });
            html += '<th style="width: 120px;">Actions</th>';
            html += '</tr></thead><tbody>';
            
            data.forEach((row, rowIndex) => {
                html += '<tr>';
                columns.forEach((col, colIndex) => {
                    const value = row[col];
                    if (value === null || value === undefined) {
                        html += '<td class="cell-null">NULL</td>';
                    } else {
                        const displayValue = typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value);
                        const isLong = displayValue.length > 50;
                        const truncatedClass = isLong ? ' truncated' : '';
                        const cellId = `cell-${rowIndex}-${colIndex}`;
                        html += `<td><div class="cell-content${truncatedClass}" id="${cellId}" data-full="${escapeHtml(displayValue)}" data-column="${escapeHtml(col)}" title="${isLong ? 'Click to view full content' : escapeHtml(displayValue)}">${escapeHtml(displayValue)}</div></td>`;
                    }
                });
                // Add action buttons
                const rowId = `row-${rowIndex}`;
                html += `<td>
                    <button class="btn-edit" data-row-id="${rowId}" data-row-data='${JSON.stringify(row)}' onclick="editRecordClick(this)" title="Edit">✏️</button>
                    <button class="btn-delete" data-row-id="${rowId}" data-row-data='${JSON.stringify(row)}' onclick="deleteRecordClick(this)" title="Delete">🗑️</button>
                </td>`;
                html += '</tr>';
            });
            
            html += '</tbody></table></div>';
            contentArea.innerHTML = html;
            
            // Add click handlers for truncated cells
            document.querySelectorAll('.cell-content.truncated').forEach(cell => {
                cell.addEventListener('click', function() {
                    showFullContent(this.dataset.column, this.dataset.full);
                });
            });
        }
        
        function showFullContent(columnName, fullContent) {
            const modal = document.getElementById('contentModal');
            const modalColumn = document.getElementById('modalColumn');
            const modalContent = document.getElementById('modalContent');
            
            modalColumn.textContent = columnName;
            modalContent.textContent = fullContent;
            
            modal.style.display = 'block';
            
            // Close on background click
            modal.onclick = function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            };
        }
        
        function closeModal() {
            document.getElementById('contentModal').style.display = 'none';
        }
        
        function copyToClipboard() {
            const content = document.getElementById('modalContent').textContent;
            navigator.clipboard.writeText(content).then(() => {
                const btn = document.querySelector('.btn-copy');
                const originalText = btn.textContent;
                btn.textContent = 'Copied!';
                btn.style.background = '#27ae60';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = '#3498db';
                }, 2000);
            }).catch(err => {
                alert('Failed to copy to clipboard');
            });
        }
        
        function updatePagination(data) {
            const start = (data.page - 1) * data.per_page + 1;
            const end = Math.min(data.page * data.per_page, data.total);
            
            document.getElementById('paginationInfo').textContent = 
                `Showing ${start.toLocaleString()} - ${end.toLocaleString()} of ${data.total.toLocaleString()} rows`;
            
            document.getElementById('pageInfo').textContent = 
                `Page ${data.page} of ${data.total_pages}`;
            
            document.getElementById('prevBtn').disabled = data.page <= 1;
            document.getElementById('nextBtn').disabled = data.page >= data.total_pages;
        }
        
        function changePage(delta) {
            const newPage = currentPage + delta;
            if (newPage >= 1 && newPage <= totalPages) {
                const search = document.getElementById('searchInput').value;
                loadTableData(currentTable, newPage, perPage, search);
            }
        }
        
        function changePerPage() {
            perPage = parseInt(document.getElementById('perPageSelect').value);
            currentPage = 1;
            const search = document.getElementById('searchInput').value;
            loadTableData(currentTable, currentPage, perPage, search);
        }
        
        document.getElementById('searchInput').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (currentTable) {
                    currentPage = 1;
                    loadTableData(currentTable, currentPage, perPage, e.target.value);
                }
            }, 500);
        });
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function editRecordClick(btn) {
            const rowData = JSON.parse(btn.dataset.rowData);
            editRecord(rowData);
        }
        
        function deleteRecordClick(btn) {
            const rowData = JSON.parse(btn.dataset.rowData);
            if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
                return;
            }
            deleteRecord(rowData);
        }
        
        function editRecord(rowData) {
            const modal = document.getElementById('editModal');
            const form = document.getElementById('editForm');
            const modalTitle = document.getElementById('editModalTitle');
            
            modalTitle.textContent = `Edit Record - ${currentTable}`;
            form.innerHTML = '';
            
            tableColumns.forEach(col => {
                const value = rowData[col] !== null && rowData[col] !== undefined ? rowData[col] : '';
                const isPrimaryKey = primaryKeys.includes(col);
                
                const div = document.createElement('div');
                div.className = 'edit-form-group';
                div.innerHTML = `
                    <label>${escapeHtml(col)} ${isPrimaryKey ? '<span style="color: #d32f2f;">(Primary Key)</span>' : ''}</label>
                    <input type="text" name="${escapeHtml(col)}" value="${escapeHtml(String(value))}" ${isPrimaryKey ? 'readonly' : ''} class="edit-input">
                `;
                form.appendChild(div);
            });
            
            form.dataset.originalData = JSON.stringify(rowData);
            modal.style.display = 'block';
        }
        
        function addNewRecord() {
            const modal = document.getElementById('editModal');
            const form = document.getElementById('editForm');
            const modalTitle = document.getElementById('editModalTitle');
            
            modalTitle.textContent = `Add New Record - ${currentTable}`;
            form.innerHTML = '';
            
            tableColumns.forEach(col => {
                const isPrimaryKey = primaryKeys.includes(col);
                const div = document.createElement('div');
                div.className = 'edit-form-group';
                div.innerHTML = `
                    <label>${escapeHtml(col)} ${isPrimaryKey ? '<span style="color: #d32f2f;">(Primary Key - Auto)</span>' : ''}</label>
                    <input type="text" name="${escapeHtml(col)}" value="" class="edit-input">
                `;
                form.appendChild(div);
            });
            
            form.dataset.originalData = '';
            modal.style.display = 'block';
        }
        
        function saveRecord() {
            const form = document.getElementById('editForm');
            const formData = new FormData(form);
            const data = {};
            const isNew = !form.dataset.originalData;
            
            formData.forEach((value, key) => {
                if (value.trim() !== '') {
                    data[key] = value;
                }
            });
            
            if (isNew) {
                const postData = new URLSearchParams();
                postData.append('action', 'insert_record');
                postData.append('table', currentTable);
                postData.append('data', JSON.stringify(data));
                
                fetch('', {
                    method: 'POST',
                    body: postData
                })
                .then(res => res.json())
                .then(result => {
                    if (result.error) {
                        alert('Error: ' + result.error);
                    } else {
                        alert('Record added successfully!');
                        closeEditModal();
                        loadTableData(currentTable, currentPage, perPage);
                        loadTableInfo(currentTable);
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                });
            } else {
                const where = {};
                primaryKeys.forEach(pk => {
                    if (form.dataset.originalData) {
                        const original = JSON.parse(form.dataset.originalData);
                        where[pk] = original[pk];
                    }
                });
                
                const postData = new URLSearchParams();
                postData.append('action', 'update_record');
                postData.append('table', currentTable);
                postData.append('data', JSON.stringify(data));
                postData.append('where', JSON.stringify(where));
                
                fetch('', {
                    method: 'POST',
                    body: postData
                })
                .then(res => res.json())
                .then(result => {
                    if (result.error) {
                        alert('Error: ' + result.error);
                    } else {
                        alert('Record updated successfully!');
                        closeEditModal();
                        loadTableData(currentTable, currentPage, perPage);
                        loadTableInfo(currentTable);
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                });
            }
        }
        
        function deleteRecord(rowData) {
            const where = {};
            primaryKeys.forEach(pk => {
                if (rowData[pk] !== undefined) {
                    where[pk] = rowData[pk];
                }
            });
            
            if (Object.keys(where).length === 0) {
                alert('Cannot delete: No primary key found');
                return;
            }
            
            const postData = new URLSearchParams();
            postData.append('action', 'delete_record');
            postData.append('table', currentTable);
            postData.append('where', JSON.stringify(where));
            
            fetch('', {
                method: 'POST',
                body: postData
            })
            .then(res => res.json())
            .then(result => {
                if (result.error) {
                    alert('Error: ' + result.error);
                } else {
                    alert('Record deleted successfully!');
                    loadTableData(currentTable, currentPage, perPage);
                    loadTableInfo(currentTable);
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
            });
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="editModalTitle">Edit Record</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm"></form>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeEditModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveRecord()">Save</button>
            </div>
        </div>
    </div>
</body>
</html>
