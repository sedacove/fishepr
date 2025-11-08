<?php
/**
 * API для загрузки файлов задач
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

// Требуем авторизацию
requireAuth();

try {
    $pdo = getDBConnection();
    $userId = getCurrentUserId();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }
    
    $taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
    
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
    $isAdmin = isAdmin();
    if ($task['assigned_to'] != $userId && ($task['created_by'] != $userId || !$isAdmin)) {
        throw new Exception('Доступ запрещен');
    }
    
    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        throw new Exception('Файлы не выбраны');
    }
    
    $uploadDir = __DIR__ . '/../uploads/tasks/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $uploadedFiles = [];
    $files = $_FILES['files'];
    $fileCount = count($files['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        $originalName = $files['name'][$i];
        $tmpName = $files['tmp_name'][$i];
        $fileSize = $files['size'][$i];
        $mimeType = $files['type'][$i];
        
        // Генерируем уникальное имя файла
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileName = uniqid('task_', true) . '.' . $extension;
        $filePath = 'uploads/tasks/' . $fileName;
        $fullPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($tmpName, $fullPath)) {
            // Сохраняем информацию о файле в БД
            $stmt = $pdo->prepare("
                INSERT INTO task_files (task_id, original_name, file_name, file_path, file_size, mime_type, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$taskId, $originalName, $fileName, $filePath, $fileSize, $mimeType, $userId]);
            
            $fileId = $pdo->lastInsertId();
            $uploadedFiles[] = [
                'id' => $fileId,
                'original_name' => $originalName,
                'file_size' => $fileSize
            ];
            
            // logActivity('create', 'task_file', $fileId, "Загружен файл к задаче: {$originalName}", [
            //     'task_id' => $taskId,
            //     'file_name' => $originalName
            // ]);
        }
    }
    
    if (empty($uploadedFiles)) {
        throw new Exception('Не удалось загрузить файлы');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Файлы успешно загружены',
        'data' => $uploadedFiles
    ]);
    
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

