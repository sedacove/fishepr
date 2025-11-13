<?php
/**
 * API для управления разделом ФЗП
 * Доступен только администраторам
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/_bootstrap.php';

// Проверка прав администратора
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Доступ запрещен. Требуются права администратора.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();
    $currentUserId = getCurrentUserId();

    switch ($action) {
        case 'salary_users':
            $stmt = $pdo->query("
                SELECT 
                    u.id,
                    u.login,
                    u.full_name,
                    u.salary,
                    u.payroll_phone,
                    u.payroll_bank
                FROM users u
                WHERE u.deleted_at IS NULL
                  AND u.is_active = 1
                  AND u.salary IS NOT NULL
                ORDER BY u.full_name IS NULL, u.full_name ASC, u.login ASC
            ");
            $users = $stmt->fetchAll();

            foreach ($users as &$user) {
                $user['salary'] = $user['salary'] !== null ? (float)$user['salary'] : null;
            }

            echo json_encode([
                'success' => true,
                'data' => $users
            ]);
            break;

        case 'users':
            $stmt = $pdo->query("
                SELECT id, login, full_name
                FROM users
                WHERE deleted_at IS NULL AND is_active = 1
                ORDER BY full_name IS NULL, full_name ASC, login ASC
            ");
            $users = $stmt->fetchAll();
            echo json_encode([
                'success' => true,
                'data' => $users
            ]);
            break;

        case 'list':
            $isPaid = isset($_GET['is_paid']) ? (int)$_GET['is_paid'] : 0;
            $stmt = $pdo->prepare("
                SELECT 
                    ew.*,
                    created_by_user.login as created_by_login,
                    created_by_user.full_name as created_by_name,
                    assigned_user.login as assigned_login,
                    assigned_user.full_name as assigned_name,
                    paid_by_user.login as paid_by_login,
                    paid_by_user.full_name as paid_by_name
                FROM extra_works ew
                LEFT JOIN users created_by_user ON ew.created_by = created_by_user.id
                LEFT JOIN users assigned_user ON ew.assigned_to = assigned_user.id
                LEFT JOIN users paid_by_user ON ew.paid_by = paid_by_user.id
                WHERE ew.is_paid = ?
                ORDER BY ew.work_date DESC, ew.created_at DESC
            ");
            $stmt->execute([$isPaid]);
            $records = $stmt->fetchAll();

            foreach ($records as &$record) {
                $record['amount'] = (float)$record['amount'];
                $record['is_paid'] = (bool)$record['is_paid'];
                $record['assigned_to'] = $record['assigned_to'] !== null ? (int)$record['assigned_to'] : null;
            }

            echo json_encode([
                'success' => true,
                'data' => $records
            ]);
            break;

        case 'get':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('ID записи не указан');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    ew.*,
                    created_by_user.login as created_by_login,
                    created_by_user.full_name as created_by_name,
                    assigned_user.login as assigned_login,
                    assigned_user.full_name as assigned_name,
                    paid_by_user.login as paid_by_login,
                    paid_by_user.full_name as paid_by_name
                FROM extra_works ew
                LEFT JOIN users created_by_user ON ew.created_by = created_by_user.id
                LEFT JOIN users assigned_user ON ew.assigned_to = assigned_user.id
                LEFT JOIN users paid_by_user ON ew.paid_by = paid_by_user.id
                WHERE ew.id = ?
            ");
            $stmt->execute([$id]);
            $record = $stmt->fetch();

            if (!$record) {
                throw new Exception('Запись не найдена');
            }

            $record['amount'] = (float)$record['amount'];
            $record['is_paid'] = (bool)$record['is_paid'];
            $record['assigned_to'] = $record['assigned_to'] !== null ? (int)$record['assigned_to'] : null;

            echo json_encode([
                'success' => true,
                'data' => $record
            ]);
            break;

        case 'create':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $date = $data['work_date'] ?? '';
            $amountInput = $data['amount'] ?? null;
            $assignedId = isset($data['assigned_to']) ? (int)$data['assigned_to'] : 0;

            if ($title === '') {
                throw new Exception('Название обязательно для заполнения');
            }

            if (!$date) {
                throw new Exception('Дата обязательна для заполнения');
            }
            $workDate = DateTime::createFromFormat('Y-m-d', $date);
            if (!$workDate) {
                throw new Exception('Неверный формат даты');
            }

            if ($amountInput === null || $amountInput === '') {
                throw new Exception('Стоимость обязательна для заполнения');
            }
            if (!is_numeric($amountInput)) {
                throw new Exception('Стоимость должна быть числом');
            }
            $amount = round((float)$amountInput, 2);
            if ($amount < 0) {
                throw new Exception('Стоимость не может быть отрицательной');
            }

            if (!$assignedId) {
                throw new Exception('Не выбран сотрудник, на которого регистрируется работа');
            }
            $stmt = $pdo->prepare("SELECT id, login, full_name FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$assignedId]);
            $assignedUser = $stmt->fetch();
            if (!$assignedUser) {
                throw new Exception('Указанный сотрудник не найден или удалён');
            }

            $stmt = $pdo->prepare("
                INSERT INTO extra_works (title, description, work_date, amount, assigned_to, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title,
                $description !== '' ? $description : null,
                $workDate->format('Y-m-d'),
                $amount,
                $assignedId,
                $currentUserId
            ]);

            $recordId = $pdo->lastInsertId();

            logActivity('create', 'extra_work', $recordId, "Добавлена доп. работа: {$title}", [
                'title' => $title,
                'description' => $description,
                'work_date' => $workDate->format('Y-m-d'),
                'amount' => $amount,
                'assigned_to' => $assignedId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Дополнительная работа успешно добавлена',
                'id' => $recordId
            ]);
            break;

        case 'update':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID записи не указан');
            }

            $stmt = $pdo->prepare("SELECT * FROM extra_works WHERE id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            if (!$record) {
                throw new Exception('Запись не найдена');
            }

            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $date = $data['work_date'] ?? '';
            $amountInput = $data['amount'] ?? null;
            $assignedId = isset($data['assigned_to']) ? (int)$data['assigned_to'] : 0;

            if ($title === '') {
                throw new Exception('Название обязательно для заполнения');
            }

            if (!$date) {
                throw new Exception('Дата обязательна для заполнения');
            }
            $workDate = DateTime::createFromFormat('Y-m-d', $date);
            if (!$workDate) {
                throw new Exception('Неверный формат даты');
            }

            if ($amountInput === null || $amountInput === '') {
                throw new Exception('Стоимость обязательна для заполнения');
            }
            if (!is_numeric($amountInput)) {
                throw new Exception('Стоимость должна быть числом');
            }
            $amount = round((float)$amountInput, 2);
            if ($amount < 0) {
                throw new Exception('Стоимость не может быть отрицательной');
            }

            if (!$assignedId) {
                throw new Exception('Не выбран сотрудник, на которого регистрируется работа');
            }
            $stmt = $pdo->prepare("SELECT id, login, full_name FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$assignedId]);
            $assignedUser = $stmt->fetch();
            if (!$assignedUser) {
                throw new Exception('Указанный сотрудник не найден или удалён');
            }

            $stmt = $pdo->prepare("
                UPDATE extra_works
                SET title = ?, description = ?, work_date = ?, amount = ?, assigned_to = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $title,
                $description !== '' ? $description : null,
                $workDate->format('Y-m-d'),
                $amount,
                $assignedId,
                $id
            ]);

            logActivity('update', 'extra_work', $id, "Обновлена доп. работа: {$title}", [
                'title' => ['old' => $record['title'], 'new' => $title],
                'description' => ['old' => $record['description'], 'new' => $description],
                'work_date' => ['old' => $record['work_date'], 'new' => $workDate->format('Y-m-d')],
                'amount' => ['old' => (float)$record['amount'], 'new' => $amount],
                'assigned_to' => ['old' => $record['assigned_to'], 'new' => $assignedId]
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Дополнительная работа успешно обновлена'
            ]);
            break;

        case 'delete':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID записи не указан');
            }

            $stmt = $pdo->prepare("SELECT * FROM extra_works WHERE id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            if (!$record) {
                throw new Exception('Запись не найдена');
            }

            $stmt = $pdo->prepare("DELETE FROM extra_works WHERE id = ?");
            $stmt->execute([$id]);

            logActivity('delete', 'extra_work', $id, "Удалена доп. работа: {$record['title']}", [
                'title' => $record['title'],
                'amount' => (float)$record['amount']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Запись успешно удалена'
            ]);
            break;

        case 'mark_paid':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID записи не указан');
            }

            $stmt = $pdo->prepare("SELECT * FROM extra_works WHERE id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            if (!$record) {
                throw new Exception('Запись не найдена');
            }

            if ((int)$record['is_paid'] === 1) {
                throw new Exception('Запись уже помечена как выплаченная');
            }

            $stmt = $pdo->prepare("
                UPDATE extra_works
                SET is_paid = 1, paid_at = NOW(), paid_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$currentUserId, $id]);

            logActivity('update', 'extra_work', $id, "Выплачена доп. работа: {$record['title']}", [
                'is_paid' => ['old' => false, 'new' => true],
                'paid_by' => $currentUserId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Запись успешно помечена как выплаченная'
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

