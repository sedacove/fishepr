<?php
/**
 * API для управления замерами
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

    $temperatureSettings = [
        'bad_below' => (float)getSetting('temp_bad_below', 10),
        'acceptable_min' => (float)getSetting('temp_acceptable_min', 10),
        'good_min' => (float)getSetting('temp_good_min', 14),
        'good_max' => (float)getSetting('temp_good_max', 17),
        'acceptable_max' => (float)getSetting('temp_acceptable_max', 20),
        'bad_above' => (float)getSetting('temp_bad_above', 20),
    ];

    $oxygenSettings = [
        'bad_below' => (float)getSetting('oxygen_bad_below', 8),
        'acceptable_min' => (float)getSetting('oxygen_acceptable_min', 8),
        'good_min' => (float)getSetting('oxygen_good_min', 11),
        'good_max' => (float)getSetting('oxygen_good_max', 16),
        'acceptable_max' => (float)getSetting('oxygen_acceptable_max', 20),
        'bad_above' => (float)getSetting('oxygen_bad_above', 20),
    ];

    $calculateStratum = static function (?float $value, array $settings): ?string {
        if ($value === null) {
            return null;
        }

        if ($value < $settings['bad_below'] || $value > $settings['bad_above']) {
            return 'bad';
        }

        $isAcceptableLow = ($value >= $settings['acceptable_min'] && $value < $settings['good_min']);
        $isAcceptableHigh = ($value > $settings['good_max'] && $value <= $settings['acceptable_max']);
        if ($isAcceptableLow || $isAcceptableHigh) {
            return 'acceptable';
        }

        if ($value >= $settings['good_min'] && $value <= $settings['good_max']) {
            return 'good';
        }

        return 'bad';
    };

    $enrichMeasurement = static function (array &$measurement) use ($calculateStratum, $temperatureSettings, $oxygenSettings) {
        $temperature = isset($measurement['temperature']) ? (float)$measurement['temperature'] : null;
        $oxygen = isset($measurement['oxygen']) ? (float)$measurement['oxygen'] : null;

        $measurement['temperature_stratum'] = $temperature !== null
            ? $calculateStratum($temperature, $temperatureSettings)
            : null;
        $measurement['oxygen_stratum'] = $oxygen !== null
            ? $calculateStratum($oxygen, $oxygenSettings)
            : null;
    };
    
    switch ($action) {
        case 'list':
            // Получить список замеров для бассейна
            $poolId = isset($_GET['pool_id']) ? (int)$_GET['pool_id'] : 0;
            
            if (!$poolId) {
                throw new Exception('ID бассейна не указан');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    u.login as created_by_login,
                    u.full_name as created_by_name
                FROM measurements m
                LEFT JOIN users u ON m.created_by = u.id
                WHERE m.pool_id = ?
                ORDER BY m.measured_at DESC
            ");
            $stmt->execute([$poolId]);
            $measurements = $stmt->fetchAll();
            
            // Преобразование дат и проверка прав на редактирование
            $currentUserId = getCurrentUserId();
            foreach ($measurements as &$measurement) {
                $measuredAt = $measurement['measured_at'];
                $measurement['measured_at'] = date('Y-m-d\TH:i', strtotime($measuredAt));
                $measurement['measured_at_display'] = date('d.m.Y H:i', strtotime($measuredAt));
                
                // Сохраняем исходное значение created_at для проверки времени
                $createdAtTimestamp = strtotime($measurement['created_at']);
                $measurement['created_at'] = date('d.m.Y H:i', $createdAtTimestamp);
                $measurement['created_by_full_name'] = $measurement['created_by_name'];
                
                // Проверка, может ли пользователь редактировать этот замер
                // Администратор может редактировать все
                // Пользователь может редактировать только свои замеры в течение заданного времени
                if ($isAdmin) {
                    $measurement['can_edit'] = true;
                } else {
                    if ($measurement['created_by'] == $currentUserId) {
                        $timeoutMinutes = getSettingInt('measurement_edit_timeout_minutes', 30);
                        $now = time();
                        $minutesPassed = ($now - $createdAtTimestamp) / 60;
                        $measurement['can_edit'] = $minutesPassed <= $timeoutMinutes;
                    } else {
                        $measurement['can_edit'] = false;
                    }
                }

                $enrichMeasurement($measurement);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $measurements
            ]);
            break;
            
        case 'get':
            // Получить данные одного замера
            $id = $_GET['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID замера не указан');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    u.login as created_by_login,
                    u.full_name as created_by_name
                FROM measurements m
                LEFT JOIN users u ON m.created_by = u.id
                WHERE m.id = ?
            ");
            $stmt->execute([$id]);
            $measurement = $stmt->fetch();
            
            if (!$measurement) {
                throw new Exception('Замер не найден');
            }
            
            // Преобразование даты
            $measurement['measured_at'] = date('Y-m-d\TH:i', strtotime($measurement['measured_at']));
            $enrichMeasurement($measurement);
            
            echo json_encode([
                'success' => true,
                'data' => $measurement
            ]);
            break;
            
        case 'create':
            // Создать новый замер
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $poolId = isset($data['pool_id']) ? (int)$data['pool_id'] : 0;
            $temperature = isset($data['temperature']) ? (float)$data['temperature'] : null;
            $oxygen = isset($data['oxygen']) ? (float)$data['oxygen'] : null;
            
            // Для администратора можно указать дату/время, для пользователя - текущее время
            if ($isAdmin && isset($data['measured_at']) && !empty($data['measured_at'])) {
                $measuredAt = $data['measured_at'];
            } else {
                $measuredAt = date('Y-m-d H:i:s');
            }
            
            // Валидация
            if (!$poolId) {
                throw new Exception('Бассейн обязателен для выбора');
            }
            
            if ($temperature === null) {
                throw new Exception('Температура обязательна для заполнения');
            }
            
            if ($oxygen === null) {
                throw new Exception('Количество кислорода обязательно для заполнения');
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
                INSERT INTO measurements (pool_id, temperature, oxygen, measured_at, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $poolId,
                $temperature,
                $oxygen,
                $measuredAt,
                $createdBy
            ]);
            
            $measurementId = $pdo->lastInsertId();
            
            // Логирование только для администратора
            if ($isAdmin) {
                logActivity('create', 'measurement', $measurementId, "Добавлен замер для бассейна: {$pool['name']}", [
                    'pool_id' => $poolId,
                    'temperature' => $temperature,
                    'oxygen' => $oxygen,
                    'measured_at' => $measuredAt
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Замер успешно добавлен',
                'id' => $measurementId
            ]);
            break;
            
        case 'update':
            // Обновить замер
            // Администратор может обновлять любые замеры
            // Пользователь может обновлять только свои замеры в течение 30 минут после создания
            
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID замера не указан');
            }
            
            // Получение старых данных
            $stmt = $pdo->prepare("
                SELECT m.*, p.name as pool_name
                FROM measurements m
                LEFT JOIN pools p ON m.pool_id = p.id
                WHERE m.id = ?
            ");
            $stmt->execute([$id]);
            $oldMeasurement = $stmt->fetch();
            
            if (!$oldMeasurement) {
                throw new Exception('Замер не найден');
            }
            
            $currentUserId = getCurrentUserId();
            
            // Проверка прав доступа
            if (!$isAdmin) {
                // Пользователь может редактировать только свои замеры
                if ($oldMeasurement['created_by'] != $currentUserId) {
                    throw new Exception('Вы можете редактировать только свои замеры');
                }
                
                // Проверка времени: не более заданного времени с момента создания
                $timeoutMinutes = getSettingInt('measurement_edit_timeout_minutes', 30);
                $createdAt = strtotime($oldMeasurement['created_at']);
                $now = time();
                $minutesPassed = ($now - $createdAt) / 60;
                
                if ($minutesPassed > $timeoutMinutes) {
                    throw new Exception("Редактирование возможно только в течение {$timeoutMinutes} минут после создания замера");
                }
            }
            
            // Для пользователя нельзя изменять бассейн и дату/время
            if ($isAdmin) {
                $poolId = isset($data['pool_id']) ? (int)$data['pool_id'] : $oldMeasurement['pool_id'];
                $measuredAt = isset($data['measured_at']) ? $data['measured_at'] : date('Y-m-d H:i', strtotime($oldMeasurement['measured_at']));
            } else {
                // Пользователь может изменять только температуру и кислород
                $poolId = $oldMeasurement['pool_id'];
                $measuredAt = $oldMeasurement['measured_at'];
            }
            
            $temperature = isset($data['temperature']) ? (float)$data['temperature'] : $oldMeasurement['temperature'];
            $oxygen = isset($data['oxygen']) ? (float)$data['oxygen'] : $oldMeasurement['oxygen'];
            
            // Валидация
            if ($temperature === null) {
                throw new Exception('Температура обязательна для заполнения');
            }
            
            if ($oxygen === null) {
                throw new Exception('Количество кислорода обязательно для заполнения');
            }
            
            // Преобразование даты/времени
            if (strpos($measuredAt, 'T') !== false) {
                $measuredAt = str_replace('T', ' ', $measuredAt) . ':00';
            }
            
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE measurements SET
                    pool_id = ?,
                    temperature = ?,
                    oxygen = ?,
                    measured_at = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $poolId,
                $temperature,
                $oxygen,
                $measuredAt,
                $id
            ]);
            
            // Формируем данные об изменениях
            $changes = [];
            if ($oldMeasurement['pool_id'] != $poolId) {
                $changes['pool_id'] = ['old' => $oldMeasurement['pool_id'], 'new' => $poolId];
            }
            if ((float)$oldMeasurement['temperature'] != (float)$temperature) {
                $changes['temperature'] = ['old' => $oldMeasurement['temperature'], 'new' => $temperature];
            }
            if ((float)$oldMeasurement['oxygen'] != (float)$oxygen) {
                $changes['oxygen'] = ['old' => $oldMeasurement['oxygen'], 'new' => $oxygen];
            }
            if ($oldMeasurement['measured_at'] !== $measuredAt) {
                $changes['measured_at'] = ['old' => $oldMeasurement['measured_at'], 'new' => $measuredAt];
            }
            
            // Логирование всех изменений замеров
            $description = "Обновлен замер для бассейна: {$oldMeasurement['pool_name']}";
            if (!$isAdmin) {
                $description .= " (пользователь)";
            }
            logActivity('update', 'measurement', $id, $description, $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Замер успешно обновлен'
            ]);
            break;
            
        case 'delete':
            // Удалить замер (только администратор)
            if (!$isAdmin) {
                throw new Exception('Доступ запрещен');
            }
            
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID замера не указан');
            }
            
            // Получение данных для лога
            $stmt = $pdo->prepare("
                SELECT m.*, p.name as pool_name
                FROM measurements m
                LEFT JOIN pools p ON m.pool_id = p.id
                WHERE m.id = ?
            ");
            $stmt->execute([$id]);
            $measurement = $stmt->fetch();
            
            if (!$measurement) {
                throw new Exception('Замер не найден');
            }
            
            // Удаление
            $stmt = $pdo->prepare("DELETE FROM measurements WHERE id = ?");
            $stmt->execute([$id]);
            
            // Логирование
            $changes = [
                'pool_id' => $measurement['pool_id'],
                'temperature' => $measurement['temperature'],
                'oxygen' => $measurement['oxygen'],
                'measured_at' => $measurement['measured_at']
            ];
            logActivity('delete', 'measurement', $id, "Удален замер для бассейна: {$measurement['pool_name']}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Замер успешно удален'
            ]);
            break;

        case 'latest_temperatures':
        case 'latest_oxygen':
            $isTemperature = $action === 'latest_temperatures';
            $column = $isTemperature ? 'temperature' : 'oxygen';
            $limit = 20;

            $stmt = $pdo->query("
                SELECT 
                    m.id,
                    m.pool_id,
                    m.{$column} AS target_value,
                    m.temperature,
                    m.oxygen,
                    m.measured_at,
                    p.name AS pool_name
                FROM measurements m
                LEFT JOIN pools p ON m.pool_id = p.id
                WHERE m.{$column} IS NOT NULL
                ORDER BY m.measured_at DESC
                LIMIT {$limit}
            ");
            $rows = $stmt->fetchAll();

            $result = [];
            foreach ($rows as $row) {
                $measurement = $row;
                $measurement['temperature'] = $row['temperature'];
                $measurement['oxygen'] = $row['oxygen'];
                $enrichMeasurement($measurement);

                $dateTime = new DateTime($row['measured_at']);
                $result[] = [
                    'id' => (int)$row['id'],
                    'pool_id' => (int)$row['pool_id'],
                    'pool_name' => $row['pool_name'] ?? null,
                    'value' => (float)$row['target_value'],
                    'measured_at' => $row['measured_at'],
                    'label' => $dateTime->format('d.m H:i'),
                    'stratum' => $isTemperature
                        ? ($measurement['temperature_stratum'] ?? null)
                        : ($measurement['oxygen_stratum'] ?? null),
                ];
            }

            $result = array_reverse($result);

            echo json_encode([
                'success' => true,
                'data' => $result,
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
                $pool['name'] = $pool['pool_name']; // Для обратной совместимости
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
