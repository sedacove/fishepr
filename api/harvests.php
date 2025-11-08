<?php
/**
 * API для управления отборами
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
            // Получить список отборов для бассейна
            $poolId = isset($_GET['pool_id']) ? (int)$_GET['pool_id'] : 0;
            
            if (!$poolId) {
                throw new Exception('ID бассейна не указан');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    h.*,
                    u.login as created_by_login,
                    u.full_name as created_by_name,
                    c.name AS counterparty_name,
                    c.color AS counterparty_color
                FROM harvests h
                LEFT JOIN users u ON h.created_by = u.id
                LEFT JOIN counterparties c ON h.counterparty_id = c.id
                WHERE h.pool_id = ?
                ORDER BY h.recorded_at DESC
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
                $record['counterparty_id'] = $record['counterparty_id'] !== null ? (int)$record['counterparty_id'] : null;
                
                // Проверка, может ли пользователь редактировать эту запись
                if ($isAdmin) {
                    $record['can_edit'] = true;
                } else {
                    if ($record['created_by'] == $currentUserId) {
                        $timeoutMinutes = getSettingInt('measurement_edit_timeout_minutes', 30);
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
            // Получить данные одного отбора
            $id = $_GET['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID отбора не указан');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    h.*,
                    u.login as created_by_login,
                    u.full_name as created_by_name,
                    c.name AS counterparty_name,
                    c.color AS counterparty_color
                FROM harvests h
                LEFT JOIN users u ON h.created_by = u.id
                LEFT JOIN counterparties c ON h.counterparty_id = c.id
                WHERE h.id = ?
            ");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            
            if (!$record) {
                throw new Exception('Отбор не найден');
            }
            
            $record['counterparty_id'] = $record['counterparty_id'] !== null ? (int)$record['counterparty_id'] : null;
            
            // Преобразование даты
            $record['recorded_at'] = date('Y-m-d\TH:i', strtotime($record['recorded_at']));
            
            echo json_encode([
                'success' => true,
                'data' => $record
            ]);
            break;
            
        case 'create':
            // Создать новый отбор
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $poolId = isset($data['pool_id']) ? (int)$data['pool_id'] : 0;
            $weight = isset($data['weight']) ? (float)$data['weight'] : null;
            $fishCount = isset($data['fish_count']) ? (int)$data['fish_count'] : null;
            $counterpartyId = isset($data['counterparty_id']) && $data['counterparty_id'] !== '' ? (int)$data['counterparty_id'] : null;
            
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
            
            if ($fishCount === null || $fishCount < 0) {
                throw new Exception('Количество рыб должно быть неотрицательным числом');
            }
            
            // Проверка существования бассейна
            $stmt = $pdo->prepare("SELECT id, name FROM pools WHERE id = ? AND is_active = 1");
            $stmt->execute([$poolId]);
            $pool = $stmt->fetch();
            
            if (!$pool) {
                throw new Exception('Бассейн не найден или неактивен');
            }
            
            if ($counterpartyId !== null) {
                $stmt = $pdo->prepare("SELECT id FROM counterparties WHERE id = ?");
                $stmt->execute([$counterpartyId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Контрагент не найден');
                }
            }
            
            $createdBy = getCurrentUserId();
            
            // Вставка
            $stmt = $pdo->prepare("
                INSERT INTO harvests (pool_id, weight, fish_count, counterparty_id, recorded_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $poolId,
                $weight,
                $fishCount,
                $counterpartyId,
                $recordedAt,
                $createdBy
            ]);
            
            $recordId = $pdo->lastInsertId();
            
            // Логирование только для администратора
            if ($isAdmin) {
                logActivity('create', 'harvest', $recordId, "Добавлен отбор для бассейна: {$pool['name']}", [
                    'pool_id' => $poolId,
                    'weight' => $weight,
                    'fish_count' => $fishCount,
                    'counterparty_id' => $counterpartyId,
                    'recorded_at' => $recordedAt
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Отбор успешно добавлен',
                'id' => $recordId
            ]);
            break;
            
        case 'update':
            // Обновить отбор
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID отбора не указан');
            }
            
            // Получение старых данных
            $stmt = $pdo->prepare("
                SELECT h.*, p.name as pool_name
                FROM harvests h
                LEFT JOIN pools p ON h.pool_id = p.id
                WHERE h.id = ?
            ");
            $stmt->execute([$id]);
            $oldRecord = $stmt->fetch();
            
            if (!$oldRecord) {
                throw new Exception('Отбор не найден');
            }
            
            $currentUserId = getCurrentUserId();
            
            // Проверка прав доступа
            if (!$isAdmin) {
                if ($oldRecord['created_by'] != $currentUserId) {
                    throw new Exception('Вы можете редактировать только свои записи');
                }
                
                $timeoutMinutes = getSettingInt('measurement_edit_timeout_minutes', 30);
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
            $counterpartyId = isset($data['counterparty_id']) && $data['counterparty_id'] !== '' ? (int)$data['counterparty_id'] : null;
            
            // Валидация
            if ($weight <= 0) {
                throw new Exception('Вес должен быть положительным числом');
            }
            
            if ($fishCount < 0) {
                throw new Exception('Количество рыб должно быть неотрицательным числом');
            }
            
            if ($counterpartyId !== null) {
                $stmt = $pdo->prepare("SELECT id FROM counterparties WHERE id = ?");
                $stmt->execute([$counterpartyId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Контрагент не найден');
                }
            }
            
            // Преобразование даты/времени
            if (strpos($recordedAt, 'T') !== false) {
                $recordedAt = str_replace('T', ' ', $recordedAt) . ':00';
            }
            
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE harvests SET
                    pool_id = ?,
                    weight = ?,
                    fish_count = ?,
                    counterparty_id = ?,
                    recorded_at = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $poolId,
                $weight,
                $fishCount,
                $counterpartyId,
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
            if ((int)$oldRecord['counterparty_id'] !== (int)$counterpartyId) {
                $changes['counterparty_id'] = ['old' => $oldRecord['counterparty_id'], 'new' => $counterpartyId];
            }
            
            // Логирование всех изменений
            $description = "Обновлен отбор для бассейна: {$oldRecord['pool_name']}";
            if (!$isAdmin) {
                $description .= " (пользователь)";
            }
            logActivity('update', 'harvest', $id, $description, $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Отбор успешно обновлен'
            ]);
            break;
            
        case 'delete':
            // Удалить отбор (только администратор)
            if (!$isAdmin) {
                throw new Exception('Доступ запрещен');
            }
            
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID отбора не указан');
            }
            
            // Получение данных для лога
            $stmt = $pdo->prepare("
                SELECT h.*, p.name as pool_name
                FROM harvests h
                LEFT JOIN pools p ON h.pool_id = p.id
                WHERE h.id = ?
            ");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            
            if (!$record) {
                throw new Exception('Отбор не найден');
            }
            
            // Удаление
            $stmt = $pdo->prepare("DELETE FROM harvests WHERE id = ?");
            $stmt->execute([$id]);
            
            // Логирование
            $changes = [
                'pool_id' => $record['pool_id'],
                'weight' => $record['weight'],
                'fish_count' => $record['fish_count'],
                'recorded_at' => $record['recorded_at']
            ];
            logActivity('delete', 'harvest', $id, "Удален отбор для бассейна: {$record['pool_name']}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Отбор успешно удален'
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
