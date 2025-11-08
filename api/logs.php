<?php
/**
 * API для получения логов действий
 * Доступно только администраторам
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Требуем права администратора
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Метод не поддерживается'
    ]);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Параметры пагинации
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 50;
    $offset = ($page - 1) * $perPage;
    
    // Параметры фильтрации
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $action = $_GET['action'] ?? null;
    $entityType = $_GET['entity_type'] ?? null;
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    
    // Построение SQL запроса
    $where = [];
    $params = [];
    
    if ($dateFrom) {
        $where[] = "DATE(al.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $where[] = "DATE(al.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    if ($action) {
        $where[] = "al.action = ?";
        $params[] = $action;
    }
    
    if ($entityType) {
        $where[] = "al.entity_type = ?";
        $params[] = $entityType;
    }
    
    if ($userId) {
        $where[] = "al.user_id = ?";
        $params[] = $userId;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // Получение общего количества записей
    $countSql = "
        SELECT COUNT(*) as total
        FROM activity_log al
        {$whereClause}
    ";
    
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Получение логов
    $sql = "
        SELECT 
            al.*,
            u.login as user_login,
            u.full_name as user_full_name,
            u.user_type as user_type
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        {$whereClause}
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Преобразование данных для вывода
    foreach ($logs as &$log) {
        $createdAt = $log['created_at'];
        $log['created_at'] = date('d.m.Y H:i:s', strtotime($createdAt));
        $log['date'] = date('d.m.Y', strtotime($createdAt));
        $log['time'] = date('H:i:s', strtotime($createdAt));
    }
    
    // Получение уникальных значений для фильтров
    $actions = $pdo->query("SELECT DISTINCT action FROM activity_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    $entityTypes = $pdo->query("SELECT DISTINCT entity_type FROM activity_log ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => (int)$total,
            'total_pages' => (int)ceil($total / $perPage)
        ],
        'filters' => [
            'actions' => $actions,
            'entity_types' => $entityTypes
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
