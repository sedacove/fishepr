<?php
/**
 * API для управления бассейнами
 * Доступно только администраторам
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

// Требуем права администратора
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'list':
            // Получить список всех бассейнов
            $stmt = $pdo->query("
                SELECT 
                    p.*,
                    u.login as created_by_login,
                    u.full_name as created_by_name
                FROM pools p
                LEFT JOIN users u ON p.created_by = u.id
                ORDER BY p.sort_order ASC, p.created_at ASC
            ");
            $pools = $stmt->fetchAll();
            
            // Преобразование дат для вывода
            foreach ($pools as &$pool) {
                $pool['created_at'] = date('d.m.Y H:i', strtotime($pool['created_at']));
                $pool['updated_at'] = date('d.m.Y H:i', strtotime($pool['updated_at']));
            }
            
            echo json_encode([
                'success' => true,
                'data' => $pools
            ]);
            break;
            
        case 'get':
            // Получить данные одного бассейна
            $id = $_GET['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID бассейна не указан');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    u.login as created_by_login,
                    u.full_name as created_by_name
                FROM pools p
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $pool = $stmt->fetch();
            
            if (!$pool) {
                throw new Exception('Бассейн не найден');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $pool
            ]);
            break;
            
        case 'create':
            // Создать новый бассейн
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $name = trim($data['name'] ?? '');
            
            // Валидация
            if (empty($name)) {
                throw new Exception('Название бассейна обязательно для заполнения');
            }
            
            if (strlen($name) > 255) {
                throw new Exception('Название бассейна слишком длинное (максимум 255 символов)');
            }
            
            // Получение максимального sort_order
            $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM pools");
            $result = $stmt->fetch();
            $sortOrder = ($result['max_order'] ?? -1) + 1;
            
            $createdBy = getCurrentUserId();
            
            // Вставка
            $stmt = $pdo->prepare("INSERT INTO pools (name, sort_order, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$name, $sortOrder, $createdBy]);
            
            $poolId = $pdo->lastInsertId();
            
            // Логирование с данными
            $changes = [
                'name' => $name,
                'sort_order' => $sortOrder
            ];
            logActivity('create', 'pool', $poolId, "Создан бассейн: {$name}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Бассейн успешно создан',
                'id' => $poolId
            ]);
            break;
            
        case 'update':
            // Обновить бассейн
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID бассейна не указан');
            }
            
            // Проверка существования
            $stmt = $pdo->prepare("SELECT name, is_active FROM pools WHERE id = ?");
            $stmt->execute([$id]);
            $oldPool = $stmt->fetch();
            
            if (!$oldPool) {
                throw new Exception('Бассейн не найден');
            }
            
            $name = trim($data['name'] ?? '');
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : null;
            
            // Валидация
            if (empty($name)) {
                throw new Exception('Название бассейна обязательно для заполнения');
            }
            
            if (strlen($name) > 255) {
                throw new Exception('Название бассейна слишком длинное (максимум 255 символов)');
            }
            
            // Построение запроса обновления
            $updates = [];
            $params = [];
            $changes = [];
            
            if ($oldPool['name'] !== $name) {
                $updates[] = "name = ?";
                $params[] = $name;
                $changes['name'] = ['old' => $oldPool['name'], 'new' => $name];
            }
            
            if ($isActive !== null && $oldPool['is_active'] != $isActive) {
                $updates[] = "is_active = ?";
                $params[] = $isActive;
                $changes['is_active'] = ['old' => (bool)$oldPool['is_active'], 'new' => (bool)$isActive];
            }
            
            if (empty($updates)) {
                throw new Exception('Нет изменений для сохранения');
            }
            
            $params[] = $id;
            $sql = "UPDATE pools SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Логирование с данными об изменениях
            logActivity('update', 'pool', $id, "Обновлен бассейн: {$oldPool['name']}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Бассейн успешно обновлен'
            ]);
            break;
            
        case 'delete':
            // Удалить бассейн
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID бассейна не указан');
            }
            
            // Получение данных для лога
            $stmt = $pdo->prepare("SELECT name, sort_order, is_active FROM pools WHERE id = ?");
            $stmt->execute([$id]);
            $pool = $stmt->fetch();
            
            if (!$pool) {
                throw new Exception('Бассейн не найден');
            }
            
            // Удаление
            $stmt = $pdo->prepare("DELETE FROM pools WHERE id = ?");
            $stmt->execute([$id]);
            
            // Логирование с данными
            $changes = [
                'name' => $pool['name'],
                'sort_order' => $pool['sort_order'],
                'is_active' => (bool)$pool['is_active']
            ];
            logActivity('delete', 'pool', $id, "Удален бассейн: {$pool['name']}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Бассейн успешно удален'
            ]);
            break;
            
        case 'update_order':
            // Обновить порядок сортировки
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $order = $data['order'] ?? [];
            
            if (empty($order) || !is_array($order)) {
                throw new Exception('Неверный формат данных');
            }
            
            $pdo->beginTransaction();
            
            try {
                foreach ($order as $index => $poolId) {
                    $stmt = $pdo->prepare("UPDATE pools SET sort_order = ? WHERE id = ?");
                    $stmt->execute([$index, $poolId]);
                }
                
                $pdo->commit();
                
                // Логирование с данными
                $changes = ['new_order' => $order];
                logActivity('update', 'pool', null, 'Изменен порядок сортировки бассейнов', $changes);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Порядок сортировки обновлен'
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
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
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
}
