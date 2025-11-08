<?php
/**
 * API для скачивания документов контрагентов
 * Доступно только администраторам
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

requireAdmin();

$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$fileId) {
    http_response_code(400);
    die('ID файла не указан');
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT d.*, c.name AS counterparty_name
        FROM counterparty_documents d
        INNER JOIN counterparties c ON d.counterparty_id = c.id
        WHERE d.id = ?
    ");
    $stmt->execute([$fileId]);
    $document = $stmt->fetch();

    if (!$document) {
        http_response_code(404);
        die('Документ не найден');
    }

    $filePath = __DIR__ . '/../' . $document['file_path'];
    if (!is_file($filePath)) {
        http_response_code(404);
        die('Файл не найден на сервере');
    }

    logActivity('download', 'counterparty_document', $fileId, "Скачан документ контрагента: {$document['original_name']}", [
        'counterparty_id' => $document['counterparty_id'],
        'counterparty_name' => $document['counterparty_name'],
        'original_name' => $document['original_name'],
        'file_size' => $document['file_size'],
    ]);

    header('Content-Type: ' . ($document['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . addslashes($document['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    readfile($filePath);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    die('Ошибка: ' . $e->getMessage());
}


