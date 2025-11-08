<?php
/**
 * API для управления навесками
 * Доступно всем авторизованным пользователям
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../includes/settings.php';

// Требуем авторизацию
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();
    $isAdmin = isAdmin();
    
    switch ($action) {
        case 'list':
            // Получить список навесок для бассейна
            $poolId = isset($_GET['pool_id']) ? (int)$_GET['pool_id'] : 0;
            
            if (!$poolId) {
                throw new Exception('ID бассейна не указан');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    w.*,
                    u.login as created_by_login,
                    u.full_name as created_by_name
                FROM weighings w
                LEFT JOIN users u ON w.created_by = u.id
                WHERE w.pool_id = ?
                ORDER BY w.recorded_at DESC
            ");
            $stmt->execute([$poolId]);
            $records = $stmt->fetchAll();
            
            // Преобразование дат и проверка прав на редактирование
            $currentUserId = getCurrentUserId();
            foreach ($records as &$record) {
                $recordedAt = $record['recorded_at'];
                $record['recorded_at'] = date('Y-m-d\TH:i', strtotime($recordedAt));
                $record['recorded_at_display'] = date('d.m.Y H:i', strtotime($recordedAt));
                
                // Сохраняем исходное значение created_at для проверки времени
                $createdAtTimestamp = strtotime($record['created_at']);
                $record['created_at'] = date('d.m.Y H:i', $createdAtTimestamp);
                $record['created_by_full_name'] = $record['created_by_name'];
                
                // Проверка, может ли пользователь редактировать эту запись
                if ($isAdmin) {
                    $record['can_edit'] = true;
                } else {
                    if ($record['created_by'] == $currentUserId) {
                        $timeoutMinutes = getSettingInt('weighing_edit_timeout_minutes', 30);
                        $now = time();
                        $minutesPassed = ($now - $createdAtTimestamp) / 60;
                        $record['can_edit'] = $minutesPassed <= $timeoutMinutes;
                    } else {
                        $record['can_edit'] = false;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $records
            ]);
            break;
            
        case 'get':
            // Получить данные одной навески
            $id = $_GET['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID записи не указан');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    w.*,
                    u.login as created_by_login,
                    u.full_name as created_by_name
                FROM weighings w
                LEFT JOIN users u ON w.created_by = u.id
                WHERE w.id = ?
            ");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            
            if (!$record) {
                throw new Exception('Запись не найдена');
            }
            
            // Преобразование даты
            $record['recorded_at'] = date('Y-m-d\TH:i', strtotime($record['recorded_at']));
            
            echo json_encode([
                'success' => true,
                'data' => $record
            ]);
            break;
            
        case 'create':
            // Создать новую навеску
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $poolId = isset($data['pool_id']) ? (int)$data['pool_id'] : 0;
            $weight = isset($data['weight']) ? (float)$data['weight'] : null;
            $fishCount = isset($data['fish_count']) ? (int)$data['fish_count'] : null;
            
            // Для администратора можно указать дату/время, для пользователя - текущее время
            if ($isAdmin && isset($data['recorded_at']) && !empty($data['recorded_at'])) {
                $recordedAt = $data['recorded_at'];
            } else {
                $recordedAt = date('Y-m-d H:i:s');
            }
            
            // Валидация
            if (!$poolId) {
                throw new Exception('Бассейн обязателен для выбора');
            }
            
            if ($weight === null || $weight <= 0) {
                throw new Exception('Вес должен быть положительным числом');
            }
            
            if ($fishCount === null || $fishCount <= 0) {
                throw new Exception('Количество рыб должно быть положительным числом');
            }
            
            // Проверка существования бассейна
            $stmt = $pdo->prepare("SELECT id, name FROM pools WHERE id = ? AND is_active = 1");
            $stmt->execute([$poolId]);
            $pool = $stmt->fetch();
            
            if (!$pool) {
                throw new Exception('Бассейн не найден или неактивен');
            }
            
            $createdBy = getCurrentUserId();
            
            // Вставка
            $stmt = $pdo->prepare("
                INSERT INTO weighings (pool_id, weight, fish_count, recorded_at, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $poolId,
                $weight,
                $fishCount,
                $recordedAt,
                $createdBy
            ]);
            
            $recordId = $pdo->lastInsertId();
            
            // Логирование только для администратора
            if ($isAdmin) {
                logActivity('create', 'weighing', $recordId, "Добавлена навеска для бассейна: {$pool['name']}", [
                    'pool_id' => $poolId,
                    'weight' => $weight,
                    'fish_count' => $fishCount,
                    'recorded_at' => $recordedAt
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Навеска успешно добавлена',
                'id' => $recordId
            ]);
            break;
            
        case 'update':
            // Обновить навеску
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID записи не указан');
            }
            
            // Получение старых данных
            $stmt = $pdo->prepare("
                SELECT w.*, p.name as pool_name
                FROM weighings w
                LEFT JOIN pools p ON w.pool_id = p.id
                WHERE w.id = ?
            ");
            $stmt->execute([$id]);
            $oldRecord = $stmt->fetch();
            
            if (!$oldRecord) {
                throw new Exception('Запись не найдена');
            }
            
            $currentUserId = getCurrentUserId();
            
            // Проверка прав доступа
            if (!$isAdmin) {
                if ($oldRecord['created_by'] != $currentUserId) {
                    throw new Exception('Вы можете редактировать только свои записи');
                }
                
                $timeoutMinutes = getSettingInt('weighing_edit_timeout_minutes', 30);
                $createdAt = strtotime($oldRecord['created_at']);
                $now = time();
                $minutesPassed = ($now - $createdAt) / 60;
                
                if ($minutesPassed > $timeoutMinutes) {
                    throw new Exception("Редактирование возможно только в течение {$timeoutMinutes} минут после создания записи");
                }
            }
            
            // Для пользователя нельзя изменять бассейн и дату/время
            if ($isAdmin) {
                $poolId = isset($data['pool_id']) ? (int)$data['pool_id'] : $oldRecord['pool_id'];
                $recordedAt = isset($data['recorded_at']) ? $data['recorded_at'] : date('Y-m-d H:i', strtotime($oldRecord['recorded_at']));
            } else {
                $poolId = $oldRecord['pool_id'];
                $recordedAt = $oldRecord['recorded_at'];
            }
            
            $weight = isset($data['weight']) ? (float)$data['weight'] : $oldRecord['weight'];
            $fishCount = isset($data['fish_count']) ? (int)$data['fish_count'] : $oldRecord['fish_count'];
            
            // Валидация
            if ($weight <= 0) {
                throw new Exception('Вес должен быть положительным числом');
            }
            
            if ($fishCount <= 0) {
                throw new Exception('Количество рыб должно быть положительным числом');
            }
            
            // Преобразование даты/времени
            if (strpos($recordedAt, 'T') !== false) {
                $recordedAt = str_replace('T', ' ', $recordedAt) . ':00';
            }
            
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE weighings SET
                    pool_id = ?,
                    weight = ?,
                    fish_count = ?,
                    recorded_at = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $poolId,
                $weight,
                $fishCount,
                $recordedAt,
                $id
            ]);
            
            // Формируем данные об изменениях
            $changes = [];
            if ($oldRecord['pool_id'] != $poolId) {
                $changes['pool_id'] = ['old' => $oldRecord['pool_id'], 'new' => $poolId];
            }
            if ((float)$oldRecord['weight'] != (float)$weight) {
                $changes['weight'] = ['old' => $oldRecord['weight'], 'new' => $weight];
            }
            if ((int)$oldRecord['fish_count'] != (int)$fishCount) {
                $changes['fish_count'] = ['old' => $oldRecord['fish_count'], 'new' => $fishCount];
            }
            if ($oldRecord['recorded_at'] !== $recordedAt) {
                $changes['recorded_at'] = ['old' => $oldRecord['recorded_at'], 'new' => $recordedAt];
            }
            
            // Логирование всех изменений
            $description = "Обновлена навеска для бассейна: {$oldRecord['pool_name']}";
            if (!$isAdmin) {
                $description .= " (пользователь)";
            }
            logActivity('update', 'weighing', $id, $description, $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Навеска успешно обновлена'
            ]);
            break;
            
        case 'delete':
            // Удалить навеску (только администратор)
            if (!$isAdmin) {
                throw new Exception('Доступ запрещен');
            }
            
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID записи не указан');
            }
            
            // Получение данных для лога
            $stmt = $pdo->prepare("
                SELECT w.*, p.name as pool_name
                FROM weighings w
                LEFT JOIN pools p ON w.pool_id = p.id
                WHERE w.id = ?
            ");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            
            if (!$record) {
                throw new Exception('Запись не найдена');
            }
            
            // Удаление
            $stmt = $pdo->prepare("DELETE FROM weighings WHERE id = ?");
            $stmt->execute([$id]);
            
            // Логирование
            $changes = [
                'pool_id' => $record['pool_id'],
                'weight' => $record['weight'],
                'fish_count' => $record['fish_count'],
                'recorded_at' => $record['recorded_at']
            ];
            logActivity('delete', 'weighing', $id, "Удалена навеска для бассейна: {$record['pool_name']}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Навеска успешно удалена'
            ]);
            break;
            
        case 'get_pools':
            // Получить список активных бассейнов с их активными сессиями
            $stmt = $pdo->query("
                SELECT 
                    p.id,
                    p.name as pool_name
                FROM pools p
                WHERE p.is_active = 1
                ORDER BY p.sort_order ASC, p.name ASC
            ");
            $pools = $stmt->fetchAll();
            
            // Для каждого бассейна получаем активную сессию
            foreach ($pools as &$pool) {
                $stmt = $pdo->prepare("
                    SELECT 
                        s.id,
                        s.name as session_name
                    FROM sessions s
                    WHERE s.pool_id = ? AND s.is_completed = 0
                    ORDER BY s.start_date DESC
                    LIMIT 1
                ");
                $stmt->execute([$pool['id']]);
                $session = $stmt->fetch();
                
                $pool['active_session'] = $session ?: null;
                $pool['name'] = $pool['pool_name'];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $pools
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
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
}

