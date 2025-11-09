<?php
/**
 * API для управления приборами учета
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

try {
    $pdo = getDBConnection();
    $userId = getCurrentUserId();
    $isAdmin = isAdmin();

    $adminActions = [
        'create',
        'update',
        'delete',
        'get',
        'list_admin'
    ];

    if (in_array($action, $adminActions, true) && !$isAdmin) {
        throw new Exception('Доступ запрещен');
    }

    switch ($action) {
        case 'list':
            $stmt = $pdo->query("
                SELECT id, name, description
                FROM meters
                ORDER BY name ASC
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll()
            ]);
            break;

        case 'list_admin':
            $stmt = $pdo->query("
                SELECT 
                    m.*,
                    u.full_name AS created_by_name,
                    u.login AS created_by_login
                FROM meters m
                LEFT JOIN users u ON u.id = m.created_by
                ORDER BY m.created_at DESC
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll()
            ]);
            break;

        case 'get':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID прибора не указан');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    u.full_name AS created_by_name,
                    u.login AS created_by_login
                FROM meters m
                LEFT JOIN users u ON u.id = m.created_by
                WHERE m.id = ?
            ");
            $stmt->execute([$id]);
            $meter = $stmt->fetch();

            if (!$meter) {
                throw new Exception('Прибор учета не найден');
            }

            echo json_encode([
                'success' => true,
                'data' => $meter
            ]);
            break;

        case 'create':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Пустые данные');
            }

            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');

            if ($name === '') {
                throw new Exception('Название прибора обязательно');
            }

            $stmt = $pdo->prepare("
                INSERT INTO meters (name, description, created_by)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $description ?: null, $userId]);

            $meterId = (int)$pdo->lastInsertId();

            logActivity('create', 'meter', $meterId, "Добавлен прибор учета: {$name}", [
                'name' => $name,
                'description' => $description
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Прибор учета добавлен',
                'data' => ['id' => $meterId]
            ]);
            break;

        case 'update':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Пустые данные');
            }

            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID прибора не указан');
            }

            $stmt = $pdo->prepare("SELECT * FROM meters WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();

            if (!$existing) {
                throw new Exception('Прибор учета не найден');
            }

            $name = trim($data['name'] ?? $existing['name']);
            $description = trim($data['description'] ?? ($existing['description'] ?? ''));

            if ($name === '') {
                throw new Exception('Название прибора обязательно');
            }

            $stmt = $pdo->prepare("
                UPDATE meters
                SET name = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $description ?: null, $id]);

            logActivity('update', 'meter', $id, "Обновлен прибор учета: {$name}", [
                'name' => ['old' => $existing['name'], 'new' => $name],
                'description' => ['old' => $existing['description'], 'new' => $description]
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Прибор учета обновлен'
            ]);
            break;

        case 'delete':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID прибора не указан');
            }

            $stmt = $pdo->prepare("SELECT name FROM meters WHERE id = ?");
            $stmt->execute([$id]);
            $meter = $stmt->fetch();

            if (!$meter) {
                throw new Exception('Прибор учета не найден');
            }

            $stmt = $pdo->prepare("DELETE FROM meters WHERE id = ?");
            $stmt->execute([$id]);

            logActivity('delete', 'meter', $id, "Удален прибор учета: {$meter['name']}");

            echo json_encode([
                'success' => true,
                'message' => 'Прибор учета удален'
            ]);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

