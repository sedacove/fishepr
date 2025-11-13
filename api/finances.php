<?php
/**
 * API для управления финансами (доходы и расходы)
 * Доступно только администраторам
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
        case 'list_expenses':
            echo json_encode(listFinanceRecords($pdo, 'expenses'));
            break;
        case 'list_incomes':
            echo json_encode(listFinanceRecords($pdo, 'incomes'));
            break;
        case 'summary':
            echo json_encode(getFinanceSummary($pdo));
            break;
        case 'get_expense':
            echo json_encode(getFinanceRecord($pdo, 'expenses'));
            break;
        case 'get_income':
            echo json_encode(getFinanceRecord($pdo, 'incomes'));
            break;
        case 'create_expense':
            requirePost();
            echo json_encode(saveFinanceRecord($pdo, 'expenses', $currentUserId));
            break;
        case 'create_income':
            requirePost();
            echo json_encode(saveFinanceRecord($pdo, 'incomes', $currentUserId));
            break;
        case 'update_expense':
            requirePost();
            echo json_encode(updateFinanceRecord($pdo, 'expenses'));
            break;
        case 'update_income':
            requirePost();
            echo json_encode(updateFinanceRecord($pdo, 'incomes'));
            break;
        case 'delete_expense':
            requirePost();
            echo json_encode(deleteFinanceRecord($pdo, 'expenses'));
            break;
        case 'delete_income':
            requirePost();
            echo json_encode(deleteFinanceRecord($pdo, 'incomes'));
            break;
        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

function requirePost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }
}

function getFilterDates(string $filter): array
{
    $filter = in_array($filter, ['week', 'month', 'all'], true) ? $filter : 'month';
    $startDate = null;
    $endDate = (new DateTimeImmutable('today'))->format('Y-m-d');

    if ($filter === 'week') {
        $startDate = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
    } elseif ($filter === 'month') {
        $startDate = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
    }

    return [$startDate, $endDate, $filter];
}

function listFinanceRecords(PDO $pdo, string $type): array
{
    $table = $type === 'expenses' ? 'finance_expenses' : 'finance_incomes';
    $filter = $_GET['filter'] ?? 'month';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 30;
    $offset = ($page - 1) * $limit;

    list($startDate, $endDate, $filter) = getFilterDates($filter);

    $where = [];
    $params = [];

    if ($startDate) {
        $where[] = "record_date >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $where[] = "record_date <= ?";
        $params[] = $endDate;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT id, record_date, title, amount, comment
            FROM {$table}
            {$whereSql}
            ORDER BY record_date DESC, id DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    return [
        'success' => true,
        'data' => $records,
        'pagination' => [
            'page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'pages' => (int)ceil($total / $limit),
        ],
        'filter' => $filter,
    ];
}

function getFinanceRecord(PDO $pdo, string $type): array
{
    $table = $type === 'expenses' ? 'finance_expenses' : 'finance_incomes';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        throw new Exception('ID записи не указан');
    }

    $stmt = $pdo->prepare("SELECT id, record_date, title, amount, comment FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        throw new Exception('Запись не найдена');
    }

    return [
        'success' => true,
        'data' => $record,
    ];
}

function saveFinanceRecord(PDO $pdo, string $type, int $userId): array
{
    $table = $type === 'expenses' ? 'finance_expenses' : 'finance_incomes';
    $payload = json_decode(file_get_contents('php://input'), true);
    $data = validateFinancePayload($payload, $type);

    $stmt = $pdo->prepare("INSERT INTO {$table} (record_date, title, amount, comment, created_by)
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['record_date'],
        $data['title'],
        $data['amount'],
        $data['comment'],
        $userId,
    ]);

    $id = (int)$pdo->lastInsertId();

    logActivity(
        'create',
        getFinanceEntityType($type),
        $id,
        getFinanceEntityLabel($type) . ' добавлен',
        [
            'record_date' => $data['record_date'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'comment' => $data['comment'],
        ]
    );

    return [
        'success' => true,
        'message' => ($type === 'expenses' ? 'Расход' : 'Приход') . ' успешно добавлен',
        'id' => $id,
    ];
}

function updateFinanceRecord(PDO $pdo, string $type): array
{
    $table = $type === 'expenses' ? 'finance_expenses' : 'finance_incomes';
    $payload = json_decode(file_get_contents('php://input'), true);
    $id = isset($payload['id']) ? (int)$payload['id'] : 0;
    if (!$id) {
        throw new Exception('ID записи не указан');
    }

    $existingStmt = $pdo->prepare("SELECT record_date, title, amount, comment FROM {$table} WHERE id = ?");
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch();
    if (!$existing) {
        throw new Exception('Запись не найдена');
    }

    $data = validateFinancePayload($payload, $type);

    $stmt = $pdo->prepare("UPDATE {$table} SET record_date = ?, title = ?, amount = ?, comment = ? WHERE id = ?");
    $stmt->execute([
        $data['record_date'],
        $data['title'],
        $data['amount'],
        $data['comment'],
        $id,
    ]);

    $changes = [];
    if ($existing['record_date'] !== $data['record_date']) {
        $changes['record_date'] = ['old' => $existing['record_date'], 'new' => $data['record_date']];
    }
    if ($existing['title'] !== $data['title']) {
        $changes['title'] = ['old' => $existing['title'], 'new' => $data['title']];
    }
    if ((float)$existing['amount'] !== (float)$data['amount']) {
        $changes['amount'] = ['old' => (float)$existing['amount'], 'new' => (float)$data['amount']];
    }
    $existingComment = $existing['comment'] ?? null;
    $newComment = $data['comment'] ?? null;
    if ($existingComment !== $newComment) {
        $changes['comment'] = ['old' => $existingComment, 'new' => $newComment];
    }

    if (!empty($changes)) {
        logActivity(
            'update',
            getFinanceEntityType($type),
            $id,
            getFinanceEntityLabel($type) . ' обновлён',
            $changes
        );
    }

    return [
        'success' => true,
        'message' => ($type === 'expenses' ? 'Расход' : 'Приход') . ' успешно обновлён',
    ];
}

function deleteFinanceRecord(PDO $pdo, string $type): array
{
    $table = $type === 'expenses' ? 'finance_expenses' : 'finance_incomes';
    $payload = json_decode(file_get_contents('php://input'), true);
    $id = isset($payload['id']) ? (int)$payload['id'] : 0;
    if (!$id) {
        throw new Exception('ID записи не указан');
    }

    $existingStmt = $pdo->prepare("SELECT record_date, title, amount, comment FROM {$table} WHERE id = ?");
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch();
    if (!$existing) {
        throw new Exception('Запись не найдена');
    }

    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);

    logActivity(
        'delete',
        getFinanceEntityType($type),
        $id,
        getFinanceEntityLabel($type) . ' удалён',
        $existing
    );

    return [
        'success' => true,
        'message' => ($type === 'expenses' ? 'Расход' : 'Приход') . ' удалён',
    ];
}

function getFinanceSummary(PDO $pdo): array
{
    $filter = $_GET['filter'] ?? 'month';
    list($startDate, $endDate, $filter) = getFilterDates($filter);

    $params = [];
    $where = [];

    if ($startDate) {
        $where[] = 'record_date >= ?';
        $params[] = $startDate;
    }
    if ($endDate) {
        $where[] = 'record_date <= ?';
        $params[] = $endDate;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM finance_expenses {$whereSql}");
    $expenseStmt->execute($params);
    $expensesTotal = (float)$expenseStmt->fetchColumn();

    $incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM finance_incomes {$whereSql}");
    $incomeStmt->execute($params);
    $incomesTotal = (float)$incomeStmt->fetchColumn();

    return [
        'success' => true,
        'data' => [
            'filter' => $filter,
            'expenses' => $expensesTotal,
            'incomes' => $incomesTotal,
            'balance' => $incomesTotal - $expensesTotal,
        ],
    ];
}

function validateFinancePayload(array $payload, string $type): array
{
    $date = trim($payload['record_date'] ?? '');
    $title = trim($payload['title'] ?? '');
    $amount = $payload['amount'] ?? null;
    $comment = trim($payload['comment'] ?? '');

    if ($date === '') {
        throw new Exception('Дата обязательна');
    }
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        throw new Exception('Неверный формат даты');
    }

    if ($title === '') {
        $title = $type === 'expenses' ? 'Без цели' : 'Без источника';
    }

    if ($amount === null || !is_numeric($amount)) {
        throw new Exception('Сумма должна быть числом');
    }
    $amount = round((float)$amount, 2);
    if ($amount <= 0) {
        throw new Exception('Сумма должна быть положительной');
    }

    if ($comment === '') {
        $comment = null;
    }

    return [
        'record_date' => $date,
        'title' => $title,
        'amount' => $amount,
        'comment' => $comment,
    ];
}

function getFinanceEntityType(string $type): string
{
    return $type === 'expenses' ? 'finance_expense' : 'finance_income';
}

function getFinanceEntityLabel(string $type): string
{
    return $type === 'expenses' ? 'Расход' : 'Приход';
}


