<?php
session_start();
require_once '../config/database.php';

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 检查超级管理员权限
if (!isset($_SESSION['super_admin_verified']) || $_SESSION['super_admin_verified'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => '需要超级管理员权限']);
    exit;
}

// 获取数据库连接
$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

switch($action) {
    case 'status':
        getDatabaseStatus();
        break;
    case 'tables':
        getTablesList();
        break;
    case 'execute':
        executeSql();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => '无效的操作']);
        break;
}

// 获取数据库状态
function getDatabaseStatus() {
    global $db;
    
    try {
        // 获取数据库基本信息
        $stmt = $db->query("SELECT DATABASE() as db_name");
        $dbName = $stmt->fetch()['db_name'];
        
        // 获取 MySQL 版本
        $stmt = $db->query("SELECT VERSION() as version");
        $version = $stmt->fetch()['version'];
        
        // 获取表数量
        $stmt = $db->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()");
        $tableCount = $stmt->fetch()['count'];
        
        // 获取数据库大小
        $stmt = $db->query("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        $sizeResult = $stmt->fetch();
        $sizeFormatted = ($sizeResult['size_mb'] ?? 0) . ' MB';
        
        echo json_encode([
            'success' => true,
            'status' => [
                'database' => $dbName,
                'version' => $version,
                'table_count' => $tableCount,
                'database_size' => $sizeFormatted
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取数据库状态失败: ' . $e->getMessage()]);
    }
}

// 获取数据表列表
function getTablesList() {
    global $db;
    
    try {
        $stmt = $db->query("
            SELECT 
                t.table_name as name,
                t.`table_rows` as `rows`,
                ROUND((t.data_length + t.index_length) / 1024, 2) as size_kb
            FROM information_schema.tables t
            WHERE t.table_schema = DATABASE()
            ORDER BY t.table_name
        ");
        
        $tables = [];
        while ($row = $stmt->fetch()) {
            $tables[] = [
                'name' => $row['name'],
                'rows' => $row['rows'] ?? 0,
                'size' => ($row['size_kb'] ?? 0) . ' KB'
            ];
        }
        
        echo json_encode([
            'success' => true,
            'tables' => $tables
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取数据表列表失败: ' . $e->getMessage()]);
    }
}

// 执行 SQL 命令
function executeSql() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sql = trim($input['sql'] ?? '');
    
    if (empty($sql)) {
        echo json_encode(['error' => 'SQL 命令不能为空']);
        return;
    }
    
    // 记录 SQL 执行日志
    logSqlExecution($sql);
    
    try {
        // 检查是否是查询语句
        $isSelect = stripos($sql, 'SELECT') === 0 || 
                   stripos($sql, 'SHOW') === 0 || 
                   stripos($sql, 'DESCRIBE') === 0 ||
                   stripos($sql, 'DESC') === 0 ||
                   stripos($sql, 'EXPLAIN') === 0;
        
        if ($isSelect) {
            // 执行查询并返回结果
            $stmt = $db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'results' => $results,
                'row_count' => count($results)
            ]);
        } else {
            // 执行非查询语句
            $affectedRows = $db->exec($sql);
            
            echo json_encode([
                'success' => true,
                'affected_rows' => $affectedRows,
                'message' => 'SQL 执行成功'
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'SQL 执行失败: ' . $e->getMessage()]);
    }
}

// 记录 SQL 执行日志
function logSqlExecution($sql) {
    global $db;
    
    try {
        // 创建 SQL 执行日志表（如果不存在）
        $createLogTable = "
            CREATE TABLE IF NOT EXISTS sql_execution_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT,
                admin_username VARCHAR(50),
                sql_command TEXT NOT NULL,
                execution_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                user_agent TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($createLogTable);
        
        // 记录执行日志
        $stmt = $db->prepare("
            INSERT INTO sql_execution_log (admin_id, admin_username, sql_command, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['super_admin_id'] ?? null,
            $_SESSION['super_admin_username'] ?? 'unknown',
            $sql,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
    } catch (Exception $e) {
        // 日志记录失败不影响主要功能
        error_log('SQL 执行日志记录失败: ' . $e->getMessage());
    }
}

// 获取 SQL 执行日志
function getSqlLogs() {
    global $db;
    
    try {
        $stmt = $db->query("
            SELECT * FROM sql_execution_log 
            ORDER BY execution_time DESC 
            LIMIT 100
        ");
        
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'logs' => $logs
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取执行日志失败: ' . $e->getMessage()]);
    }
}
?>
