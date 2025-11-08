<?php
/**
 * API для управления задачами
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

// Требуем авторизацию
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();
    $userId = getCurrentUserId();
    $isAdmin = isAdmin();
    
    switch ($action) {
        case 'list':
            // Получить список задач
            $tab = $_GET['tab'] ?? 'my'; // 'my' или 'assigned' (для админов)
            
            if ($tab === 'assigned' && !$isAdmin) {
                throw new Exception('Доступ запрещен');
            }
            
            if ($tab === 'my') {
                // Мои задачи (где я ответственный)
                $stmt = $pdo->prepare("
                    SELECT 
                        t.*,
                        u1.login as assigned_to_login,
                        u1.full_name as assigned_to_name,
                        u2.login as created_by_login,
                        u2.full_name as created_by_name,
                        u3.login as completed_by_login,
                        u3.full_name as completed_by_name,
                        (SELECT COUNT(*) FROM task_items ti WHERE ti.task_id = t.id) as items_count,
                        (SELECT COUNT(*) FROM task_items ti WHERE ti.task_id = t.id AND ti.is_completed = 1) as items_completed_count
                    FROM tasks t
                    LEFT JOIN users u1 ON t.assigned_to = u1.id
                    LEFT JOIN users u2 ON t.created_by = u2.id
                    LEFT JOIN users u3 ON t.completed_by = u3.id
                    WHERE t.assigned_to = ? AND u1.deleted_at IS NULL
                    ORDER BY t.is_completed ASC, t.due_date ASC, t.created_at DESC
                ");
                $stmt->execute([$userId]);
            } else {
                // Задачи, которые я поставил (только для админов)
                $stmt = $pdo->prepare("
                    SELECT 
                        t.*,
                        u1.login as assigned_to_login,
                        u1.full_name as assigned_to_name,
                        u2.login as created_by_login,
                        u2.full_name as created_by_name,
                        u3.login as completed_by_login,
                        u3.full_name as completed_by_name,
                        (SELECT COUNT(*) FROM task_items ti WHERE ti.task_id = t.id) as items_count,
                        (SELECT COUNT(*) FROM task_items ti WHERE ti.task_id = t.id AND ti.is_completed = 1) as items_completed_count
                    FROM tasks t
                    LEFT JOIN users u1 ON t.assigned_to = u1.id
                    LEFT JOIN users u2 ON t.created_by = u2.id
                    LEFT JOIN users u3 ON t.completed_by = u3.id
                    WHERE t.created_by = ? AND u1.deleted_at IS NULL
                    ORDER BY t.is_completed ASC, t.due_date ASC, t.created_at DESC
                ");
                $stmt->execute([$userId]);
            }
            
            $tasks = $stmt->fetchAll();
            
            // Преобразование дат
            foreach ($tasks as &$task) {
                $task['due_date'] = $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : null;
                $task['created_at'] = date('Y-m-d H:i', strtotime($task['created_at']));
                $task['updated_at'] = date('Y-m-d H:i', strtotime($task['updated_at']));
                $task['completed_at'] = $task['completed_at'] ? date('Y-m-d H:i', strtotime($task['completed_at'])) : null;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $tasks
            ]);
            break;
            
        case 'get':
            // Получить задачу с чеклистом и файлами
            $taskId = $_GET['id'] ?? null;
            
            if (!$taskId) {
                throw new Exception('ID задачи не указан');
            }
            
            // Получаем задачу
            $stmt = $pdo->prepare("
                SELECT 
                    t.*,
                    u1.login as assigned_to_login,
                    u1.full_name as assigned_to_name,
                    u2.login as created_by_login,
                    u2.full_name as created_by_name,
                    u3.login as completed_by_login,
                    u3.full_name as completed_by_name
                FROM tasks t
                LEFT JOIN users u1 ON t.assigned_to = u1.id
                LEFT JOIN users u2 ON t.created_by = u2.id
                LEFT JOIN users u3 ON t.completed_by = u3.id
                WHERE t.id = ?
            ");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            
            if (!$task) {
                throw new Exception('Задача не найдена');
            }
            
            // Проверка доступа: только ответственный или создатель (если админ)
            if ($task['assigned_to'] != $userId && ($task['created_by'] != $userId || !$isAdmin)) {
                throw new Exception('Доступ запрещен');
            }
            
            // Получаем элементы чеклиста
            $stmt = $pdo->prepare("
                SELECT 
                    ti.*,
                    u.login as completed_by_login,
                    u.full_name as completed_by_name
                FROM task_items ti
                LEFT JOIN users u ON ti.completed_by = u.id
                WHERE ti.task_id = ?
                ORDER BY ti.sort_order ASC, ti.created_at ASC
            ");
            $stmt->execute([$taskId]);
            $items = $stmt->fetchAll();
            
            // Преобразование дат для элементов
            foreach ($items as &$item) {
                $item['completed_at'] = $item['completed_at'] ? date('Y-m-d H:i', strtotime($item['completed_at'])) : null;
            }
            
            // Получаем файлы
            $stmt = $pdo->prepare("
                SELECT 
                    tf.*,
                    u.login as uploaded_by_login,
                    u.full_name as uploaded_by_name
                FROM task_files tf
                LEFT JOIN users u ON tf.uploaded_by = u.id
                WHERE tf.task_id = ?
                ORDER BY tf.created_at DESC
            ");
            $stmt->execute([$taskId]);
            $files = $stmt->fetchAll();
            
            // Преобразование дат для задачи
            $task['due_date'] = $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : null;
            $task['created_at'] = date('Y-m-d H:i', strtotime($task['created_at']));
            $task['updated_at'] = date('Y-m-d H:i', strtotime($task['updated_at']));
            $task['completed_at'] = $task['completed_at'] ? date('Y-m-d H:i', strtotime($task['completed_at'])) : null;
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'task' => $task,
                    'items' => $items,
                    'files' => $files
                ]
            ]);
            break;
            
        case 'create':
            // Создать задачу (только для админов)
            if (!$isAdmin) {
                throw new Exception('Доступ запрещен');
            }
            
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $assignedTo = isset($data['assigned_to']) ? (int)$data['assigned_to'] : 0;
            $dueDate = $data['due_date'] ?? null;
            
            if (empty($title)) {
                throw new Exception('Название задачи обязательно');
            }
            
            if (!$assignedTo) {
                throw new Exception('Ответственный не указан');
            }
            
            // Проверка существования пользователя
            $stmt = $pdo->prepare("SELECT id, login, full_name FROM users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL");
            $stmt->execute([$assignedTo]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Пользователь не найден или неактивен');
            }
            
            // Создание задачи
            $stmt = $pdo->prepare("
                INSERT INTO tasks (title, description, assigned_to, created_by, due_date) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $description ?: null, $assignedTo, $userId, $dueDate ?: null]);
            $taskId = $pdo->lastInsertId();
            
            // Создание элементов чеклиста, если они есть
            if (isset($data['items']) && is_array($data['items'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO task_items (task_id, title, sort_order) 
                    VALUES (?, ?, ?)
                ");
                foreach ($data['items'] as $index => $itemTitle) {
                    $itemTitle = trim($itemTitle);
                    if (!empty($itemTitle)) {
                        $stmt->execute([$taskId, $itemTitle, $index]);
                    }
                }
            }
            
            $changes = [
                'title' => $title,
                'assigned_to' => $assignedTo,
                'assigned_to_name' => $user['full_name'] ?: $user['login'],
                'due_date' => $dueDate
            ];
            
            // logActivity('create', 'task', $taskId, "Создана задача: {$title}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Задача успешно создана',
                'data' => ['id' => $taskId]
            ]);
            break;
            
        case 'update':
            // Обновить задачу (только для админов)
            if (!$isAdmin) {
                throw new Exception('Доступ запрещен');
            }
            
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $taskId = isset($data['id']) ? (int)$data['id'] : 0;
            
            if (!$taskId) {
                throw new Exception('ID задачи не указан');
            }
            
            // Получаем текущую задачу
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $oldTask = $stmt->fetch();
            
            if (!$oldTask) {
                throw new Exception('Задача не найдена');
            }
            
            $title = trim($data['title'] ?? $oldTask['title']);
            $description = trim($data['description'] ?? $oldTask['description']);
            $assignedTo = isset($data['assigned_to']) ? (int)$data['assigned_to'] : $oldTask['assigned_to'];
            $dueDate = $data['due_date'] ?? $oldTask['due_date'];
            
            if (empty($title)) {
                throw new Exception('Название задачи обязательно');
            }
            
            // Проверка существования пользователя
            $stmt = $pdo->prepare("SELECT id, login, full_name FROM users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL");
            $stmt->execute([$assignedTo]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Пользователь не найден или неактивен');
            }
            
            // Обновление задачи
            $stmt = $pdo->prepare("
                UPDATE tasks 
                SET title = ?, description = ?, assigned_to = ?, due_date = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $description ?: null, $assignedTo, $dueDate ?: null, $taskId]);
            
            // Обновление элементов чеклиста
            if (isset($data['items']) && is_array($data['items'])) {
                // Удаляем старые элементы
                $stmt = $pdo->prepare("DELETE FROM task_items WHERE task_id = ?");
                $stmt->execute([$taskId]);
                
                // Создаем новые элементы
                $stmt = $pdo->prepare("
                    INSERT INTO task_items (task_id, title, sort_order) 
                    VALUES (?, ?, ?)
                ");
                foreach ($data['items'] as $index => $item) {
                    $itemTitle = is_array($item) ? trim($item['title'] ?? '') : trim($item);
                    if (!empty($itemTitle)) {
                        $stmt->execute([$taskId, $itemTitle, $index]);
                    }
                }
            }
            
            $changes = [
                'title' => ['old' => $oldTask['title'], 'new' => $title],
                'assigned_to' => ['old' => $oldTask['assigned_to'], 'new' => $assignedTo],
                'due_date' => ['old' => $oldTask['due_date'], 'new' => $dueDate]
            ];
            
            // logActivity('update', 'task', $taskId, "Обновлена задача: {$title}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Задача успешно обновлена'
            ]);
            break;
            
        case 'complete':
            // Отметить задачу как выполненную
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $taskId = isset($data['id']) ? (int)$data['id'] : 0;
            $isCompleted = isset($data['is_completed']) ? (bool)$data['is_completed'] : true;
            
            if (!$taskId) {
                throw new Exception('ID задачи не указан');
            }
            
            // Получаем задачу
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            
            if (!$task) {
                throw new Exception('Задача не найдена');
            }
            
            // Проверка доступа: только ответственный
            if ($task['assigned_to'] != $userId) {
                throw new Exception('Доступ запрещен');
            }
            
            // Обновление статуса
            if ($isCompleted) {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET is_completed = 1, completed_at = NOW(), completed_by = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$userId, $taskId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET is_completed = 0, completed_at = NULL, completed_by = NULL, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$taskId]);
            }
            
            // logActivity('update', 'task', $taskId, $isCompleted ? "Задача выполнена: {$task['title']}" : "Задача возвращена в работу: {$task['title']}", [
            //     'is_completed' => $isCompleted
            // ]);
            
            echo json_encode([
                'success' => true,
                'message' => $isCompleted ? 'Задача отмечена как выполненная' : 'Задача возвращена в работу'
            ]);
            break;
            
        case 'update_items_order':
            // Обновить порядок элементов чеклиста
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $taskId = isset($data['task_id']) ? (int)$data['task_id'] : 0;
            $items = $data['items'] ?? [];
            
            if (!$taskId) {
                throw new Exception('ID задачи не указан');
            }
            
            // Проверка доступа к задаче
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            
            if (!$task) {
                throw new Exception('Задача не найдена');
            }
            
            // Проверка прав: только ответственный или создатель (если админ)
            if ($task['assigned_to'] != $userId && ($task['created_by'] != $userId || !$isAdmin)) {
                throw new Exception('Доступ запрещен');
            }
            
            // Обновление порядка элементов
            $stmt = $pdo->prepare("UPDATE task_items SET sort_order = ? WHERE id = ? AND task_id = ?");
            foreach ($items as $item) {
                $itemId = isset($item['id']) ? (int)$item['id'] : 0;
                $sortOrder = isset($item['sort_order']) ? (int)$item['sort_order'] : 0;
                if ($itemId > 0) {
                    $stmt->execute([$sortOrder, $itemId, $taskId]);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Порядок элементов обновлен'
            ]);
            break;
            
        case 'complete_item':
            // Отметить элемент чеклиста как выполненный
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $itemId = isset($data['id']) ? (int)$data['id'] : 0;
            $isCompleted = isset($data['is_completed']) ? (bool)$data['is_completed'] : true;
            
            if (!$itemId) {
                throw new Exception('ID элемента не указан');
            }
            
            // Получаем элемент и задачу
            $stmt = $pdo->prepare("
                SELECT ti.*, t.assigned_to 
                FROM task_items ti
                INNER JOIN tasks t ON ti.task_id = t.id
                WHERE ti.id = ?
            ");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            
            if (!$item) {
                throw new Exception('Элемент не найден');
            }
            
            // Проверка доступа: только ответственный по задаче
            if ($item['assigned_to'] != $userId) {
                throw new Exception('Доступ запрещен');
            }
            
            // Обновление статуса элемента
            if ($isCompleted) {
                $stmt = $pdo->prepare("
                    UPDATE task_items 
                    SET is_completed = 1, completed_at = NOW(), completed_by = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$userId, $itemId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE task_items 
                    SET is_completed = 0, completed_at = NULL, completed_by = NULL, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$itemId]);
            }
            
            // Проверяем, все ли элементы выполнены, и обновляем статус задачи
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
                FROM task_items
                WHERE task_id = ?
            ");
            $stmt->execute([$item['task_id']]);
            $stats = $stmt->fetch();
            
            // Если все элементы выполнены, автоматически отмечаем задачу как выполненную
            if ($stats['total'] > 0 && $stats['completed'] == $stats['total']) {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET is_completed = 1, completed_at = NOW(), completed_by = ?, updated_at = NOW()
                    WHERE id = ? AND is_completed = 0
                ");
                $stmt->execute([$userId, $item['task_id']]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => $isCompleted ? 'Элемент отмечен как выполненный' : 'Элемент возвращен в работу'
            ]);
            break;
            
        case 'delete':
            // Удалить задачу (только для админов)
            if (!$isAdmin) {
                throw new Exception('Доступ запрещен');
            }
            
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $taskId = isset($data['id']) ? (int)$data['id'] : 0;
            
            if (!$taskId) {
                throw new Exception('ID задачи не указан');
            }
            
            // Получаем задачу для лога
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            
            if (!$task) {
                throw new Exception('Задача не найдена');
            }
            
            // Удаление файлов (физически)
            $stmt = $pdo->prepare("SELECT file_path FROM task_files WHERE task_id = ?");
            $stmt->execute([$taskId]);
            $files = $stmt->fetchAll();
            
            foreach ($files as $file) {
                $filePath = __DIR__ . '/../' . $file['file_path'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            
            // Удаление задачи (каскадно удалятся элементы и файлы)
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            
            // logActivity('delete', 'task', $taskId, "Удалена задача: {$task['title']}", [
            //     'title' => $task['title']
            // ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Задача успешно удалена'
            ]);
            break;
            
        case 'get_users':
            // Получить список пользователей для выбора ответственного (только для админов)
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

