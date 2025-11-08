<?php
/**
 * API для скачивания файлов посадок
 * Доступно только авторизованным пользователям
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

// Требуем авторизацию
requireAuth();

$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$fileId) {
    http_response_code(400);
    die('ID файла не указан');
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM planting_files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    
    if (!$file) {
        http_response_code(404);
        die('Файл не найден');
    }
    
    $filePath = __DIR__ . '/../' . $file['file_path'];
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('Файл не найден на сервере');
    }
    
    // Логирование скачивания с данными
    $changes = [
        'original_name' => $file['original_name'],
        'file_size' => $file['file_size'],
        'mime_type' => $file['mime_type'],
        'planting_id' => $file['planting_id']
    ];
    logActivity('download', 'planting_file', $fileId, "Скачан файл: {$file['original_name']}", $changes);
    
    // Отправка файла
    header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . addslashes($file['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    die('Ошибка: ' . $e->getMessage());
}
