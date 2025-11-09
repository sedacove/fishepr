<?php
/**
 * API для управления календарем дежурств
 * Доступно только администраторам
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../includes/duty_helpers.php';

// Требуем авторизацию (для просмотра доступно всем, для изменения - только админам)
requireAuth();
$isAdmin = isAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'list':
            // Получить список дежурств за период (только для админов)
            if (!$isAdmin) {
                throw new Exception('Доступ запрещен');
            }
            
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            
            $stmt = $pdo->prepare("
                SELECT 
                    d.*,
                    u.login as user_login,
                    u.full_name as user_full_name,
                    u.user_type as user_type
                FROM duty_schedule d
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.date BETWEEN ? AND ?
                ORDER BY d.date ASC
            ");
            $stmt->execute([$start, $end]);
            $duties = $stmt->fetchAll();
            
            // Преобразование для FullCalendar
            $events = [];
            foreach ($duties as $duty) {
                $events[] = [
                    'id' => $duty['id'],
                    'title' => $duty['user_full_name'] ?: $duty['user_login'],
                    'start' => $duty['date'],
                    'allDay' => true,
                    'backgroundColor' => '#0d6efd',
                    'borderColor' => '#0a58ca',
                    'extendedProps' => [
                        'user_id' => $duty['user_id'],
                        'user_login' => $duty['user_login'],
                        'user_full_name' => $duty['user_full_name'],
                        'is_fasting' => (bool)$duty['is_fasting']
                    ]
                ];
            }
            
            echo json_encode($events);
            break;
            
        case 'get':
            // Получить дежурного на конкретную дату
            // Если дата не указана, используется логика смены в 8:00
            $date = $_GET['date'] ?? getTodayDutyDate();
            
            $stmt = $pdo->prepare("
                SELECT 
                    d.*,
                    u.login as user_login,
                    u.full_name as user_full_name
                FROM duty_schedule d
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.date = ?
            ");
            $stmt->execute([$date]);
            $duty = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'data' => $duty ? array_merge($duty, ['is_fasting' => (bool)$duty['is_fasting']]) : null
            ]);
            break;
            
        case 'get_current':
            // Получить текущего и завтрашнего дежурного с учетом смены в 8:00
            $todayDate = getTodayDutyDate();
            $tomorrowDate = getTomorrowDutyDate();
            
            // Получаем сегодняшнего дежурного
            $stmt = $pdo->prepare("
                SELECT 
                    d.*,
                    u.login as user_login,
                    u.full_name as user_full_name
                FROM duty_schedule d
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.date = ?
            ");
            $stmt->execute([$todayDate]);
            $todayDuty = $stmt->fetch();
            
            // Получаем завтрашнего дежурного
            $stmt->execute([$tomorrowDate]);
            $tomorrowDuty = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'today' => [
                        'date' => $todayDate,
                        'duty' => $todayDuty ? array_merge($todayDuty, ['is_fasting' => (bool)$todayDuty['is_fasting']]) : null
                    ],
                    'tomorrow' => [
                        'date' => $tomorrowDate,
                        'duty' => $tomorrowDuty ? array_merge($tomorrowDuty, ['is_fasting' => (bool)$tomorrowDuty['is_fasting']]) : null
                    ]
                ]
            ]);
            break;
            
        case 'set':
            // Установить/обновить дежурного на дату (только для админов)
            if (!$isAdmin) {
                throw new Exception('Доступ запрещен');
            }
            
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $date = $data['date'] ?? '';
            $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
            $isFasting = !empty($data['is_fasting']);
            
            if (empty($date)) {
                throw new Exception('Дата не указана');
            }
            
            if (!$userId) {
                throw new Exception('Пользователь не выбран');
            }
            
            // Проверка существования пользователя (исключая удаленных)
            $stmt = $pdo->prepare("SELECT id, login, full_name FROM users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Пользователь не найден или неактивен');
            }
            
            // Проверка, есть ли уже дежурный на эту дату
            $stmt = $pdo->prepare("SELECT id, user_id FROM duty_schedule WHERE date = ?");
            $stmt->execute([$date]);
            $existing = $stmt->fetch();
            
            $createdBy = getCurrentUserId();
            
            if ($existing) {
                // Обновление существующего дежурства
                $oldUserId = $existing['user_id'];
                
                // Получаем старого дежурного для лога (исключая удаленных)
                $stmt = $pdo->prepare("SELECT login, full_name FROM users WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$oldUserId]);
                $oldUser = $stmt->fetch();
                
                $stmt = $pdo->prepare("UPDATE duty_schedule SET user_id = ?, is_fasting = ?, updated_at = NOW() WHERE date = ?");
                $stmt->execute([$userId, $isFasting ? 1 : 0, $date]);
                
                $changes = [
                    'date' => $date,
                    'user_id' => ['old' => $oldUserId, 'new' => $userId],
                    'old_user' => $oldUser ? ($oldUser['full_name'] ?: $oldUser['login']) : null,
                    'new_user' => $user['full_name'] ?: $user['login'],
                    'is_fasting' => ['old' => (bool)$existing['is_fasting'], 'new' => $isFasting]
                ];
                
                logActivity('update', 'duty', $existing['id'], "Изменен дежурный на {$date}: {$user['full_name']}", $changes);
            } else {
                // Создание нового дежурства
                $stmt = $pdo->prepare("INSERT INTO duty_schedule (date, user_id, is_fasting, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$date, $userId, $isFasting ? 1 : 0, $createdBy]);
                
                $dutyId = $pdo->lastInsertId();
                
                $changes = [
                    'date' => $date,
                    'user_id' => $userId,
                    'user' => $user['full_name'] ?: $user['login'],
                    'is_fasting' => $isFasting
                ];
                
                logActivity('create', 'duty', $dutyId, "Назначен дежурный на {$date}: {$user['full_name']}", $changes);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Дежурный успешно назначен'
            ]);
            break;
            
        case 'delete':
            // Удалить дежурство (только для админов)
            if (!$isAdmin) {
                throw new Exception('Доступ запрещен');
            }
            
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $date = $data['date'] ?? '';
            
            if (empty($date)) {
                throw new Exception('Дата не указана');
            }
            
            // Получение данных для лога
            $stmt = $pdo->prepare("
                SELECT d.*, u.login as user_login, u.full_name as user_full_name
                FROM duty_schedule d
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.date = ?
            ");
            $stmt->execute([$date]);
            $duty = $stmt->fetch();
            
            if (!$duty) {
                throw new Exception('Дежурство не найдено');
            }
            
            // Удаление
            $stmt = $pdo->prepare("DELETE FROM duty_schedule WHERE date = ?");
            $stmt->execute([$date]);
            
            $changes = [
                'date' => $date,
                'user_id' => $duty['user_id'],
                'user' => $duty['user_full_name'] ?: $duty['user_login'],
                'is_fasting' => (bool)$duty['is_fasting']
            ];
            
            logActivity('delete', 'duty', $duty['id'], "Удален дежурный на {$date}: {$duty['user_full_name']}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Дежурство успешно удалено'
            ]);
            break;
            
        case 'range':
            $start = $_GET['start'] ?? null;
            $end = $_GET['end'] ?? null;
            if (!$start || !$end) {
                throw new Exception('Не указан период');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    d.date,
                    d.user_id,
                    d.is_fasting,
                    u.login AS user_login,
                    u.full_name AS user_full_name
                FROM duty_schedule d
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.date BETWEEN ? AND ?
                ORDER BY d.date ASC
            ");
            $stmt->execute([$start, $end]);
            $duties = $stmt->fetchAll();

            $result = [];
            foreach ($duties as $duty) {
                $result[] = [
                    'date' => $duty['date'],
                    'user_id' => $duty['user_id'],
                    'user_login' => $duty['user_login'],
                    'user_full_name' => $duty['user_full_name'],
                    'is_fasting' => (bool)$duty['is_fasting']
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;

        case 'get_users':
            // Получить список активных пользователей для выбора (только для админов)
            if (!$isAdmin) {
                throw new Exception('Доступ запрещен');
            }
            
            $stmt = $pdo->query("
                SELECT id, login, full_name, user_type 
                FROM users 
                WHERE is_active = 1 AND deleted_at IS NULL
                ORDER BY full_name ASC, login ASC
            ");
            $users = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $users
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

