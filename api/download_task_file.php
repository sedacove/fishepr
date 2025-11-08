<?php
/**
 * API для скачивания файлов задач
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

// Требуем авторизацию
requireAuth();

try {
    $pdo = getDBConnection();
    $userId = getCurrentUserId();
    
    $fileId = $_GET['id'] ?? null;
    
    if (!$fileId) {
        throw new Exception('ID файла не указан');
    }
    
    // Получаем информацию о файле и задаче
    $stmt = $pdo->prepare("
        SELECT tf.*, t.assigned_to, t.created_by
        FROM task_files tf
        INNER JOIN tasks t ON tf.task_id = t.id
        WHERE tf.id = ?
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    
    if (!$file) {
        throw new Exception('Файл не найден');
    }
    
    // Проверка доступа: только ответственный или создатель (если админ)
    $isAdmin = isAdmin();
    if ($file['assigned_to'] != $userId && ($file['created_by'] != $userId || !$isAdmin)) {
        throw new Exception('Доступ запрещен');
    }
    
    $filePath = __DIR__ . '/../' . $file['file_path'];
    
    if (!file_exists($filePath)) {
        throw new Exception('Файл не найден на сервере');
    }
    
    // Логирование скачивания
    // logActivity('view', 'task_file', $fileId, "Скачан файл: {$file['original_name']}", [
    //     'task_id' => $file['task_id'],
    //     'file_name' => $file['original_name']
    // ]);
    
    // Отправка файла
    header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . addslashes($file['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    http_response_code(404);
    die('Ошибка: ' . $e->getMessage());
}

