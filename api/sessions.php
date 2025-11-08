<?php
/**
 * API для управления сессиями
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
            // Получить список сессий
            $isCompleted = isset($_GET['completed']) ? (int)$_GET['completed'] : 0;
            
            $stmt = $pdo->prepare("
                SELECT 
                    s.*,
                    p.name as pool_name,
                    pl.name as planting_name,
                    pl.fish_breed as planting_fish_breed,
                    u.login as created_by_login,
                    u.full_name as created_by_name
                FROM sessions s
                LEFT JOIN pools p ON s.pool_id = p.id
                LEFT JOIN plantings pl ON s.planting_id = pl.id
                LEFT JOIN users u ON s.created_by = u.id
                WHERE s.is_completed = ?
                ORDER BY s.start_date DESC, s.created_at DESC
            ");
            $stmt->execute([$isCompleted]);
            $sessions = $stmt->fetchAll();
            
            // Преобразование дат и вычисление FCR
            foreach ($sessions as &$session) {
                $session['start_date'] = date('d.m.Y', strtotime($session['start_date']));
                $session['end_date'] = $session['end_date'] ? date('d.m.Y', strtotime($session['end_date'])) : null;
                $session['created_at'] = date('d.m.Y H:i', strtotime($session['created_at']));
                $session['updated_at'] = date('d.m.Y H:i', strtotime($session['updated_at']));
                
                // Вычисление FCR если есть все данные
                if ($session['end_mass'] && $session['feed_amount'] && $session['start_mass']) {
                    $massGain = $session['end_mass'] - $session['start_mass'];
                    if ($massGain > 0) {
                        $session['fcr'] = round($session['feed_amount'] / $massGain, 4);
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $sessions
            ]);
            break;
            
        case 'get':
            // Получить данные одной сессии
            $id = $_GET['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID сессии не указан');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    s.*,
                    p.name as pool_name,
                    pl.name as planting_name
                FROM sessions s
                LEFT JOIN pools p ON s.pool_id = p.id
                LEFT JOIN plantings pl ON s.planting_id = pl.id
                WHERE s.id = ?
            ");
            $stmt->execute([$id]);
            $session = $stmt->fetch();
            
            if (!$session) {
                throw new Exception('Сессия не найдена');
            }
            
            // Преобразование дат
            $session['start_date'] = date('Y-m-d', strtotime($session['start_date']));
            $session['end_date'] = $session['end_date'] ? date('Y-m-d', strtotime($session['end_date'])) : '';
            
            // Вычисление FCR
            if ($session['end_mass'] && $session['feed_amount'] && $session['start_mass']) {
                $massGain = $session['end_mass'] - $session['start_mass'];
                if ($massGain > 0) {
                    $session['fcr'] = round($session['feed_amount'] / $massGain, 4);
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $session
            ]);
            break;
            
        case 'get_pools':
            // Получить список активных бассейнов
            $stmt = $pdo->query("SELECT id, name FROM pools WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
            $pools = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $pools
            ]);
            break;
            
        case 'get_plantings':
            // Получить список активных посадок
            $stmt = $pdo->query("SELECT id, name, fish_breed FROM plantings WHERE is_archived = 0 ORDER BY planting_date DESC, name ASC");
            $plantings = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $plantings
            ]);
            break;
            
        case 'create':
            // Создать новую сессию
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $name = trim($data['name'] ?? '');
            $poolId = isset($data['pool_id']) ? (int)$data['pool_id'] : 0;
            $plantingId = isset($data['planting_id']) ? (int)$data['planting_id'] : 0;
            $startDate = $data['start_date'] ?? date('Y-m-d');
            $startMass = isset($data['start_mass']) ? (float)$data['start_mass'] : 0;
            $startFishCount = isset($data['start_fish_count']) ? (int)$data['start_fish_count'] : 0;
            $previousFcr = isset($data['previous_fcr']) && $data['previous_fcr'] !== null && $data['previous_fcr'] !== '' ? (float)$data['previous_fcr'] : null;
            
            // Валидация
            if (empty($name)) {
                throw new Exception('Название обязательно для заполнения');
            }
            
            if (!$poolId) {
                throw new Exception('Бассейн обязателен для выбора');
            }
            
            if (!$plantingId) {
                throw new Exception('Посадка обязательна для выбора');
            }
            
            if (empty($startDate)) {
                throw new Exception('Дата начала обязательна для заполнения');
            }
            
            if ($startMass <= 0) {
                throw new Exception('Масса посадки должна быть больше 0');
            }
            
            if ($startFishCount <= 0) {
                throw new Exception('Количество рыб должно быть больше 0');
            }
            
            // Проверка существования бассейна
            $stmt = $pdo->prepare("SELECT id FROM pools WHERE id = ? AND is_active = 1");
            $stmt->execute([$poolId]);
            if (!$stmt->fetch()) {
                throw new Exception('Бассейн не найден или неактивен');
            }
            
            // Проверка существования посадки
            $stmt = $pdo->prepare("SELECT id FROM plantings WHERE id = ? AND is_archived = 0");
            $stmt->execute([$plantingId]);
            if (!$stmt->fetch()) {
                throw new Exception('Посадка не найдена или архивирована');
            }
            
            // Проверка наличия активной сессии в бассейне
            $stmt = $pdo->prepare("SELECT id, name FROM sessions WHERE pool_id = ? AND is_completed = 0");
            $stmt->execute([$poolId]);
            $activeSession = $stmt->fetch();
            
            if ($activeSession) {
                throw new Exception('В этом бассейне уже есть текущая сессия. Завершите ее прежде, чем добавить новую.');
            }
            
            $createdBy = getCurrentUserId();
            
            // Вставка
            $stmt = $pdo->prepare("
                INSERT INTO sessions (name, pool_id, planting_id, start_date, start_mass, start_fish_count, previous_fcr, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name,
                $poolId,
                $plantingId,
                $startDate,
                $startMass,
                $startFishCount,
                $previousFcr,
                $createdBy
            ]);
            
            $sessionId = $pdo->lastInsertId();
            
            // Логирование с данными
            $changes = [
                'name' => $name,
                'pool_id' => $poolId,
                'planting_id' => $plantingId,
                'start_date' => $startDate,
                'start_mass' => $startMass,
                'start_fish_count' => $startFishCount,
                'previous_fcr' => $previousFcr
            ];
            logActivity('create', 'session', $sessionId, "Создана сессия: {$name}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Сессия успешно создана',
                'id' => $sessionId
            ]);
            break;
            
        case 'update':
            // Обновить сессию
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID сессии не указан');
            }
            
            // Проверка существования
            $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ?");
            $stmt->execute([$id]);
            $oldSession = $stmt->fetch();
            
            if (!$oldSession) {
                throw new Exception('Сессия не найдена');
            }
            
            // Если сессия завершена, можно обновлять только поля завершения
            if ($oldSession['is_completed']) {
                $endMass = isset($data['end_mass']) ? (float)$data['end_mass'] : null;
                $feedAmount = isset($data['feed_amount']) ? (float)$data['feed_amount'] : null;
                $endDate = $data['end_date'] ?? null;
                
                $updates = [];
                $params = [];
                
                if ($endMass !== null) {
                    $updates[] = "end_mass = ?";
                    $params[] = $endMass;
                }
                
                if ($feedAmount !== null) {
                    $updates[] = "feed_amount = ?";
                    $params[] = $feedAmount;
                }
                
                if ($endDate) {
                    $updates[] = "end_date = ?";
                    $params[] = $endDate;
                }
                
                if (empty($updates)) {
                    throw new Exception('Нет данных для обновления');
                }
                
                // Вычисление FCR
                if ($endMass && $feedAmount) {
                    $stmt = $pdo->prepare("SELECT start_mass FROM sessions WHERE id = ?");
                    $stmt->execute([$id]);
                    $sessionData = $stmt->fetch();
                    
                    if ($sessionData && $sessionData['start_mass']) {
                        $massGain = $endMass - $sessionData['start_mass'];
                        if ($massGain > 0) {
                            $fcr = round($feedAmount / $massGain, 4);
                            $updates[] = "fcr = ?";
                            $params[] = $fcr;
                        }
                    }
                }
                
                $params[] = $id;
                $sql = "UPDATE sessions SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Формируем данные об изменениях
                $changes = [];
                if ($endMass !== null) {
                    $changes['end_mass'] = ['old' => $oldSession['end_mass'], 'new' => $endMass];
                }
                if ($feedAmount !== null) {
                    $changes['feed_amount'] = ['old' => $oldSession['feed_amount'], 'new' => $feedAmount];
                }
                if ($endDate) {
                    $changes['end_date'] = ['old' => $oldSession['end_date'], 'new' => $endDate];
                }
                if (isset($fcr)) {
                    $changes['fcr'] = ['old' => $oldSession['fcr'], 'new' => $fcr];
                }
                
                logActivity('update', 'session', $id, "Обновлены данные завершения сессии: {$oldSession['name']}", $changes);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Данные завершения сессии обновлены'
                ]);
                break;
            }
            
            // Обновление незавершенной сессии
            $name = trim($data['name'] ?? '');
            $poolId = isset($data['pool_id']) ? (int)$data['pool_id'] : 0;
            $plantingId = isset($data['planting_id']) ? (int)$data['planting_id'] : 0;
            $startDate = $data['start_date'] ?? '';
            $startMass = isset($data['start_mass']) ? (float)$data['start_mass'] : 0;
            $startFishCount = isset($data['start_fish_count']) ? (int)$data['start_fish_count'] : 0;
            $previousFcr = isset($data['previous_fcr']) && $data['previous_fcr'] !== null && $data['previous_fcr'] !== '' ? (float)$data['previous_fcr'] : null;
            
            // Валидация
            if (empty($name)) {
                throw new Exception('Название обязательно для заполнения');
            }
            
            if (!$poolId) {
                throw new Exception('Бассейн обязателен для выбора');
            }
            
            if (!$plantingId) {
                throw new Exception('Посадка обязательна для выбора');
            }
            
            if (empty($startDate)) {
                throw new Exception('Дата начала обязательна для заполнения');
            }
            
            if ($startMass <= 0) {
                throw new Exception('Масса посадки должна быть больше 0');
            }
            
            if ($startFishCount <= 0) {
                throw new Exception('Количество рыб должно быть больше 0');
            }
            
            // Проверка наличия активной сессии в бассейне (если бассейн изменился)
            if ($oldSession['pool_id'] != $poolId) {
                $stmt = $pdo->prepare("SELECT id, name FROM sessions WHERE pool_id = ? AND is_completed = 0 AND id != ?");
                $stmt->execute([$poolId, $id]);
                $activeSession = $stmt->fetch();
                
                if ($activeSession) {
                    throw new Exception('В этом бассейне уже есть текущая сессия. Завершите ее прежде, чем добавить новую.');
                }
            }
            
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE sessions SET
                    name = ?,
                    pool_id = ?,
                    planting_id = ?,
                    start_date = ?,
                    start_mass = ?,
                    start_fish_count = ?,
                    previous_fcr = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name,
                $poolId,
                $plantingId,
                $startDate,
                $startMass,
                $startFishCount,
                $previousFcr,
                $id
            ]);
            
            // Формируем данные об изменениях
            $changes = [];
            if ($oldSession['name'] !== $name) {
                $changes['name'] = ['old' => $oldSession['name'], 'new' => $name];
            }
            if ($oldSession['pool_id'] != $poolId) {
                $changes['pool_id'] = ['old' => $oldSession['pool_id'], 'new' => $poolId];
            }
            if ($oldSession['planting_id'] != $plantingId) {
                $changes['planting_id'] = ['old' => $oldSession['planting_id'], 'new' => $plantingId];
            }
            if ($oldSession['start_date'] !== $startDate) {
                $changes['start_date'] = ['old' => $oldSession['start_date'], 'new' => $startDate];
            }
            if ((float)$oldSession['start_mass'] != (float)$startMass) {
                $changes['start_mass'] = ['old' => $oldSession['start_mass'], 'new' => $startMass];
            }
            if ($oldSession['start_fish_count'] != $startFishCount) {
                $changes['start_fish_count'] = ['old' => $oldSession['start_fish_count'], 'new' => $startFishCount];
            }
            if ((float)($oldSession['previous_fcr'] ?? 0) != (float)($previousFcr ?? 0)) {
                $changes['previous_fcr'] = ['old' => $oldSession['previous_fcr'], 'new' => $previousFcr];
            }
            
            // Логирование с данными об изменениях
            logActivity('update', 'session', $id, "Обновлена сессия: {$name}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Сессия успешно обновлена'
            ]);
            break;
            
        case 'complete':
            // Завершить сессию
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = $data['id'] ?? 0;
            $endMass = isset($data['end_mass']) ? (float)$data['end_mass'] : null;
            $feedAmount = isset($data['feed_amount']) ? (float)$data['feed_amount'] : null;
            $endDate = $data['end_date'] ?? date('Y-m-d');
            
            if (!$id) {
                throw new Exception('ID сессии не указан');
            }
            
            // Проверка существования
            $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ?");
            $stmt->execute([$id]);
            $session = $stmt->fetch();
            
            if (!$session) {
                throw new Exception('Сессия не найдена');
            }
            
            if ($session['is_completed']) {
                throw new Exception('Сессия уже завершена');
            }
            
            // Валидация
            if ($endMass === null || $endMass <= 0) {
                throw new Exception('Масса в конце обязательна и должна быть больше 0');
            }
            
            if ($feedAmount === null || $feedAmount < 0) {
                throw new Exception('Количество внесенного корма обязательно');
            }
            
            // Вычисление FCR
            $fcr = null;
            if ($endMass && $feedAmount && $session['start_mass']) {
                $massGain = $endMass - $session['start_mass'];
                if ($massGain > 0) {
                    $fcr = round($feedAmount / $massGain, 4);
                }
            }
            
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE sessions SET
                    is_completed = 1,
                    end_date = ?,
                    end_mass = ?,
                    feed_amount = ?,
                    fcr = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $endDate,
                $endMass,
                $feedAmount,
                $fcr,
                $id
            ]);
            
            // Логирование с данными
            $changes = [
                'is_completed' => ['old' => false, 'new' => true],
                'end_date' => ['old' => $session['end_date'], 'new' => $endDate],
                'end_mass' => ['old' => $session['end_mass'], 'new' => $endMass],
                'feed_amount' => ['old' => $session['feed_amount'], 'new' => $feedAmount],
                'fcr' => ['old' => $session['fcr'], 'new' => $fcr],
                'mass_gain' => $endMass - $session['start_mass']
            ];
            logActivity('update', 'session', $id, "Завершена сессия: {$session['name']} (FCR: " . ($fcr ?? 'N/A') . ")", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Сессия успешно завершена',
                'fcr' => $fcr
            ]);
            break;
            
        case 'delete':
            // Удалить сессию
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID сессии не указан');
            }
            
            // Получение данных для лога
            $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ?");
            $stmt->execute([$id]);
            $session = $stmt->fetch();
            
            if (!$session) {
                throw new Exception('Сессия не найдена');
            }
            
            // Удаление
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE id = ?");
            $stmt->execute([$id]);
            
            // Логирование с данными
            $changes = [
                'name' => $session['name'],
                'pool_id' => $session['pool_id'],
                'planting_id' => $session['planting_id'],
                'start_date' => $session['start_date'],
                'start_mass' => $session['start_mass'],
                'start_fish_count' => $session['start_fish_count'],
                'is_completed' => (bool)$session['is_completed']
            ];
            if ($session['is_completed']) {
                $changes['end_date'] = $session['end_date'];
                $changes['end_mass'] = $session['end_mass'];
                $changes['feed_amount'] = $session['feed_amount'];
                $changes['fcr'] = $session['fcr'];
            }
            logActivity('delete', 'session', $id, "Удалена сессия: {$session['name']}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Сессия успешно удалена'
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
