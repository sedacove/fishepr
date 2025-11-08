<?php
/**
 * API для управления контрагентами
 * Доступно только администраторам
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

// Только администраторы
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function sanitizePhoneValue(?string $input): ?string
{
    if ($input === null) {
        return null;
    }
    $input = trim($input);
    if ($input === '') {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $input);

    if (strlen($digits) === 10) {
        $digits = '7' . $digits;
    } elseif (strlen($digits) === 11 && $digits[0] === '8') {
        $digits = '7' . substr($digits, 1);
    }

    if (strlen($digits) !== 11 || $digits[0] !== '7') {
        throw new Exception('Телефон должен быть в формате +7XXXXXXXXXX');
    }

    return '+' . $digits;
}

function normalizeInn(?string $inn): ?string
{
    if ($inn === null) {
        return null;
    }
    $inn = trim($inn);
    if ($inn === '') {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $inn);
    if (!in_array(strlen($digits), [10, 12], true)) {
        throw new Exception('ИНН должен содержать 10 или 12 цифр');
    }

    return $digits;
}

function getCounterpartyColors(): array
{
    return [
        '#0d6efd' => 'Синий',
        '#198754' => 'Зелёный',
        '#fd7e14' => 'Оранжевый',
        '#0dcaf0' => 'Бирюзовый',
        '#6f42c1' => 'Фиолетовый',
        '#d63384' => 'Розовый',
        '#adb5bd' => 'Серый',
        '#343a40' => 'Графитовый',
    ];
}

function validateColor(?string $color): ?string
{
    if ($color === null) {
        return null;
    }
    $color = trim($color);
    if ($color === '') {
        return null;
    }

    $palette = getCounterpartyColors();
    if (!array_key_exists($color, $palette)) {
        throw new Exception('Выберите цвет из предустановленной палитры');
    }

    return $color;
}

try {
    $pdo = getDBConnection();
    $currentUserId = getCurrentUserId();

    switch ($action) {
        case 'palette':
            $palette = [];
            foreach (getCounterpartyColors() as $hex => $label) {
                $palette[] = [
                    'value' => $hex,
                    'label' => $label,
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $palette,
            ]);
            break;

        case 'list':
            $stmt = $pdo->query("
                SELECT 
                    c.*,
                    creator.login AS created_by_login,
                    creator.full_name AS created_by_name,
                    updater.login AS updated_by_login,
                    updater.full_name AS updated_by_name
                FROM counterparties c
                LEFT JOIN users creator ON c.created_by = creator.id
                LEFT JOIN users updater ON c.updated_by = updater.id
                ORDER BY c.name ASC, c.created_at DESC
            ");
            $counterparties = $stmt->fetchAll();

            if (!empty($counterparties)) {
                $ids = array_column($counterparties, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                $stmtDocs = $pdo->prepare("
                    SELECT counterparty_id, COUNT(*) AS documents_count
                    FROM counterparty_documents
                    WHERE counterparty_id IN ($placeholders)
                    GROUP BY counterparty_id
                ");
                $stmtDocs->execute($ids);
                $docCounts = $stmtDocs->fetchAll(PDO::FETCH_KEY_PAIR);

                foreach ($counterparties as &$item) {
                    $item['documents_count'] = (int)($docCounts[$item['id']] ?? 0);
                    $item['created_at'] = date('d.m.Y H:i', strtotime($item['created_at']));
                    $item['updated_at'] = date('d.m.Y H:i', strtotime($item['updated_at']));
                }
                unset($item);
            }

            echo json_encode([
                'success' => true,
                'data' => $counterparties,
            ]);
            break;

        case 'get':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('ID контрагента не указан');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    c.*,
                    creator.login AS created_by_login,
                    creator.full_name AS created_by_name,
                    updater.login AS updated_by_login,
                    updater.full_name AS updated_by_name
                FROM counterparties c
                LEFT JOIN users creator ON c.created_by = creator.id
                LEFT JOIN users updater ON c.updated_by = updater.id
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            $counterparty = $stmt->fetch();

            if (!$counterparty) {
                throw new Exception('Контрагент не найден');
            }

            $stmtDocs = $pdo->prepare("
                SELECT 
                    d.*,
                    u.full_name AS uploaded_by_name,
                    u.login AS uploaded_by_login
                FROM counterparty_documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.counterparty_id = ?
                ORDER BY d.uploaded_at DESC
            ");
            $stmtDocs->execute([$id]);
            $documents = $stmtDocs->fetchAll();

            foreach ($documents as &$document) {
                $document['file_size'] = (int)$document['file_size'];
                $document['uploaded_at'] = date('d.m.Y H:i', strtotime($document['uploaded_at']));
            }
            unset($document);

            $counterparty['documents'] = $documents;

            echo json_encode([
                'success' => true,
                'data' => $counterparty,
            ]);
            break;

        case 'create':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                throw new Exception('Неверный формат данных');
            }

            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $inn = normalizeInn($data['inn'] ?? null);
            $phone = sanitizePhoneValue($data['phone'] ?? null);
            $email = trim($data['email'] ?? '');
            $color = validateColor($data['color'] ?? null);

            if ($name === '') {
                throw new Exception('Название обязательно для заполнения');
            }
            if (strlen($name) > 255) {
                throw new Exception('Название слишком длинное (максимум 255 символов)');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Укажите корректный email');
            }

            $stmt = $pdo->prepare("
                INSERT INTO counterparties (name, description, inn, phone, email, color, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $description !== '' ? $description : null,
                $inn,
                $phone,
                $email !== '' ? $email : null,
                $color,
                $currentUserId,
                $currentUserId,
            ]);

            $counterpartyId = (int)$pdo->lastInsertId();

            logActivity('create', 'counterparty', $counterpartyId, "Создан контрагент: {$name}", [
                'name' => $name,
                'inn' => $inn,
                'phone' => $phone,
                'email' => $email,
                'color' => $color,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Контрагент успешно создан',
                'id' => $counterpartyId,
            ]);
            break;

        case 'update':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                throw new Exception('Неверный формат данных');
            }

            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if (!$id) {
                throw new Exception('ID контрагента не указан');
            }

            $stmt = $pdo->prepare("SELECT * FROM counterparties WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();

            if (!$existing) {
                throw new Exception('Контрагент не найден');
            }

            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $inn = normalizeInn($data['inn'] ?? null);
            $phone = sanitizePhoneValue($data['phone'] ?? null);
            $email = trim($data['email'] ?? '');
            $color = validateColor($data['color'] ?? null);

            if ($name === '') {
                throw new Exception('Название обязательно для заполнения');
            }
            if (strlen($name) > 255) {
                throw new Exception('Название слишком длинное (максимум 255 символов)');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Укажите корректный email');
            }

            $updates = [];
            $params = [];
            $changes = [];

            if ($existing['name'] !== $name) {
                $updates[] = "name = ?";
                $params[] = $name;
                $changes['name'] = ['old' => $existing['name'], 'new' => $name];
            }

            $existingDescription = $existing['description'] ?? '';
            if ($existingDescription !== $description) {
                $updates[] = "description = ?";
                $params[] = $description !== '' ? $description : null;
                $changes['description'] = ['old' => $existingDescription, 'new' => $description];
            }

            if ($existing['inn'] !== $inn) {
                $updates[] = "inn = ?";
                $params[] = $inn;
                $changes['inn'] = ['old' => $existing['inn'], 'new' => $inn];
            }

            if ($existing['phone'] !== $phone) {
                $updates[] = "phone = ?";
                $params[] = $phone;
                $changes['phone'] = ['old' => $existing['phone'], 'new' => $phone];
            }

            $existingEmail = $existing['email'] ?? '';
            if ($existingEmail !== $email) {
                $updates[] = "email = ?";
                $params[] = $email !== '' ? $email : null;
                $changes['email'] = ['old' => $existingEmail, 'new' => $email];
            }

            if ($existing['color'] !== $color) {
                $updates[] = "color = ?";
                $params[] = $color;
                $changes['color'] = ['old' => $existing['color'], 'new' => $color];
            }

            if (empty($updates)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Изменений не обнаружено',
                ]);
                break;
            }

            $updates[] = "updated_by = ?";
            $params[] = $currentUserId;

            $params[] = $id;

            $sql = "UPDATE counterparties SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            logActivity('update', 'counterparty', $id, "Обновлен контрагент: {$existing['name']}", $changes);

            echo json_encode([
                'success' => true,
                'message' => 'Контрагент успешно обновлён',
            ]);
            break;

        case 'delete':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                throw new Exception('Неверный формат данных');
            }

            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if (!$id) {
                throw new Exception('ID контрагента не указан');
            }

            $stmt = $pdo->prepare("SELECT * FROM counterparties WHERE id = ?");
            $stmt->execute([$id]);
            $counterparty = $stmt->fetch();

            if (!$counterparty) {
                throw new Exception('Контрагент не найден');
            }

            $stmtDocs = $pdo->prepare("SELECT file_path FROM counterparty_documents WHERE counterparty_id = ?");
            $stmtDocs->execute([$id]);
            $documents = $stmtDocs->fetchAll();

            foreach ($documents as $document) {
                $filePath = __DIR__ . '/../' . $document['file_path'];
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM counterparties WHERE id = ?");
            $stmt->execute([$id]);

            logActivity('delete', 'counterparty', $id, "Удалён контрагент: {$counterparty['name']}", [
                'name' => $counterparty['name'],
                'inn' => $counterparty['inn'],
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Контрагент удалён',
            ]);
            break;

        case 'upload_document':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $counterpartyId = isset($_POST['counterparty_id']) ? (int)$_POST['counterparty_id'] : 0;
            if (!$counterpartyId) {
                throw new Exception('ID контрагента не указан');
            }

            $stmt = $pdo->prepare("SELECT id, name FROM counterparties WHERE id = ?");
            $stmt->execute([$counterpartyId]);
            $counterparty = $stmt->fetch();

            if (!$counterparty) {
                throw new Exception('Контрагент не найден');
            }

            if (!isset($_FILES['files'])) {
                throw new Exception('Файлы не переданы');
            }

            $files = $_FILES['files'];
            $fileCount = is_array($files['name']) ? count($files['name']) : 0;
            if ($fileCount === 0) {
                throw new Exception('Файлы не выбраны');
            }

            $uploadDir = __DIR__ . '/../uploads/counterparties/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $saved = [];

            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $originalName = $files['name'][$i];
                $tmpPath = $files['tmp_name'][$i];
                $fileSize = (int)$files['size'][$i];
                $mimeType = $files['type'][$i] ?? null;

                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $fileName = uniqid('counterparty_', true) . ($extension ? '.' . $extension : '');
                $relativePath = 'uploads/counterparties/' . $fileName;
                $absolutePath = $uploadDir . $fileName;

                if (!move_uploaded_file($tmpPath, $absolutePath)) {
                    continue;
                }

                $stmtInsert = $pdo->prepare("
                    INSERT INTO counterparty_documents (counterparty_id, original_name, file_name, file_path, file_size, mime_type, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtInsert->execute([
                    $counterpartyId,
                    $originalName,
                    $fileName,
                    $relativePath,
                    $fileSize,
                    $mimeType,
                    $currentUserId,
                ]);

                $fileId = (int)$pdo->lastInsertId();
                $saved[] = [
                    'id' => $fileId,
                    'original_name' => $originalName,
                    'file_size' => $fileSize,
                ];
            }

            if (empty($saved)) {
                throw new Exception('Не удалось загрузить файлы');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Файлы успешно загружены',
                'data' => $saved,
            ]);
            break;

        case 'delete_document':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                throw new Exception('Неверный формат данных');
            }

            $documentId = isset($data['id']) ? (int)$data['id'] : 0;
            if (!$documentId) {
                throw new Exception('ID документа не указан');
            }

            $stmt = $pdo->prepare("SELECT * FROM counterparty_documents WHERE id = ?");
            $stmt->execute([$documentId]);
            $document = $stmt->fetch();

            if (!$document) {
                throw new Exception('Документ не найден');
            }

            $filePath = __DIR__ . '/../' . $document['file_path'];
            if (is_file($filePath)) {
                @unlink($filePath);
            }

            $stmt = $pdo->prepare("DELETE FROM counterparty_documents WHERE id = ?");
            $stmt->execute([$documentId]);

            echo json_encode([
                'success' => true,
                'message' => 'Документ удалён',
            ]);
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
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage(),
    ]);
}


