<?php
/**
 * API для загрузки файлов посадок
 * Доступно только администраторам
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

// Требуем права администратора
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Метод не поддерживается'
    ]);
    exit;
}

try {
    $plantingId = isset($_POST['planting_id']) ? (int)$_POST['planting_id'] : 0;
    
    if (!$plantingId) {
        throw new Exception('ID посадки не указан');
    }
    
    // Проверка существования посадки
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM plantings WHERE id = ?");
    $stmt->execute([$plantingId]);
    if (!$stmt->fetch()) {
        throw new Exception('Посадка не найдена');
    }
    
    if (!isset($_FILES['files'])) {
        throw new Exception('Файлы не загружены');
    }
    
    // Проверка наличия файлов
    $hasFiles = false;
    if (is_array($_FILES['files']['name'])) {
        $hasFiles = !empty($_FILES['files']['name'][0]);
    } else {
        $hasFiles = !empty($_FILES['files']['name']);
    }
    
    if (!$hasFiles) {
        throw new Exception('Файлы не загружены');
    }
    
    $uploadDir = __DIR__ . '/../uploads/plantings/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $uploadedFiles = [];
    $errors = [];
    
    // Обработка массива файлов
    $files = [];
    if (isset($_FILES['files'])) {
        if (is_array($_FILES['files']['name'])) {
            // Множественная загрузка
            $fileCount = count($_FILES['files']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                $files[] = [
                    'name' => $_FILES['files']['name'][$i],
                    'tmp_name' => $_FILES['files']['tmp_name'][$i],
                    'size' => $_FILES['files']['size'][$i],
                    'type' => $_FILES['files']['type'][$i],
                    'error' => $_FILES['files']['error'][$i]
                ];
            }
        } else {
            // Один файл
            $files[] = $_FILES['files'];
        }
    }
    
    $fileCount = count($files);
    
    for ($i = 0; $i < $fileCount; $i++) {
        $file = $files[$i];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Ошибка загрузки файла: {$file['name']}";
            continue;
        }
        
        $originalName = $file['name'];
        $tmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $mimeType = $file['type'];
        
        // Максимальный размер файла: 10 МБ
        if ($fileSize > 10 * 1024 * 1024) {
            $errors[] = "Файл слишком большой: {$originalName} (максимум 10 МБ)";
            continue;
        }
        
        // Генерация уникального имени файла
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileName = uniqid('planting_' . $plantingId . '_', true) . '.' . $extension;
        $filePath = 'uploads/plantings/' . $fileName;
        $fullPath = $uploadDir . $fileName;
        
        // Перемещение файла
        if (!move_uploaded_file($tmpName, $fullPath)) {
            $errors[] = "Не удалось сохранить файл: {$originalName}";
            continue;
        }
        
        // Сохранение информации о файле в БД
        $uploadedBy = getCurrentUserId();
        $stmt = $pdo->prepare("
            INSERT INTO planting_files (planting_id, original_name, file_name, file_path, file_size, mime_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $plantingId,
            $originalName,
            $fileName,
            $filePath,
            $fileSize,
            $mimeType,
            $uploadedBy
        ]);
        
        $fileId = $pdo->lastInsertId();
        
        // Логирование с данными
        $changes = [
            'original_name' => $originalName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'planting_id' => $plantingId
        ];
        logActivity('create', 'planting_file', $fileId, "Загружен файл для посадки #{$plantingId}: {$originalName}", $changes);
        
        $uploadedFiles[] = [
            'id' => $fileId,
            'original_name' => $originalName,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Файлы успешно загружены',
        'uploaded' => $uploadedFiles,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
