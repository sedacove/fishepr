<?php
/**
 * API для управления посадками
 * Доступно только администраторам
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

// Требуем права администратора
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'list':
            // Получить список посадок
            $isArchived = isset($_GET['archived']) ? (int)$_GET['archived'] : 0;
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    u.login as created_by_login,
                    u.full_name as created_by_name,
                    (SELECT COUNT(*) FROM planting_files WHERE planting_id = p.id) as files_count
                FROM plantings p
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.is_archived = ?
                ORDER BY p.planting_date DESC, p.created_at DESC
            ");
            $stmt->execute([$isArchived]);
            $plantings = $stmt->fetchAll();
            
            // Преобразование дат
            foreach ($plantings as &$planting) {
                $planting['hatch_date'] = $planting['hatch_date'] ? date('d.m.Y', strtotime($planting['hatch_date'])) : null;
                $planting['planting_date'] = date('d.m.Y', strtotime($planting['planting_date']));
                $planting['created_at'] = date('d.m.Y H:i', strtotime($planting['created_at']));
                $planting['updated_at'] = date('d.m.Y H:i', strtotime($planting['updated_at']));
            }
            
            echo json_encode([
                'success' => true,
                'data' => $plantings
            ]);
            break;
            
        case 'get':
            // Получить данные одной посадки
            $id = $_GET['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID посадки не указан');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    u.login as created_by_login,
                    u.full_name as created_by_name
                FROM plantings p
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $planting = $stmt->fetch();
            
            if (!$planting) {
                throw new Exception('Посадка не найдена');
            }
            
            // Получение файлов
            $stmt = $pdo->prepare("
                SELECT * FROM planting_files WHERE planting_id = ? ORDER BY created_at ASC
            ");
            $stmt->execute([$id]);
            $planting['files'] = $stmt->fetchAll();
            
            // Преобразование дат
            $planting['hatch_date'] = $planting['hatch_date'] ? date('Y-m-d', strtotime($planting['hatch_date'])) : '';
            $planting['planting_date'] = date('Y-m-d', strtotime($planting['planting_date']));
            
            echo json_encode([
                'success' => true,
                'data' => $planting
            ]);
            break;
            
        case 'create':
            // Создать новую посадку
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $name = trim($data['name'] ?? '');
            $fishBreed = trim($data['fish_breed'] ?? '');
            $hatchDate = $data['hatch_date'] ?? null;
            $plantingDate = $data['planting_date'] ?? '';
            $fishCount = isset($data['fish_count']) ? (int)$data['fish_count'] : 0;
            $biomassWeight = isset($data['biomass_weight']) ? (float)$data['biomass_weight'] : null;
            $supplier = trim($data['supplier'] ?? '');
            $price = isset($data['price']) ? (float)$data['price'] : null;
            $deliveryCost = isset($data['delivery_cost']) ? (float)$data['delivery_cost'] : null;
            
            // Валидация
            if (empty($name)) {
                throw new Exception('Название обязательно для заполнения');
            }
            
            if (empty($fishBreed)) {
                throw new Exception('Порода рыбы обязательна для заполнения');
            }
            
            if (empty($plantingDate)) {
                throw new Exception('Дата посадки обязательна для заполнения');
            }
            
            if ($fishCount <= 0) {
                throw new Exception('Количество рыб должно быть больше 0');
            }
            
            $createdBy = getCurrentUserId();
            
            // Вставка
            $stmt = $pdo->prepare("
                INSERT INTO plantings (
                    name, fish_breed, hatch_date, planting_date, fish_count, 
                    biomass_weight, supplier, price, delivery_cost, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name,
                $fishBreed,
                $hatchDate ?: null,
                $plantingDate,
                $fishCount,
                $biomassWeight,
                $supplier ?: null,
                $price,
                $deliveryCost,
                $createdBy
            ]);
            
            $plantingId = $pdo->lastInsertId();
            
            // Логирование с данными
            $changes = [
                'name' => $name,
                'fish_breed' => $fishBreed,
                'hatch_date' => $hatchDate,
                'planting_date' => $plantingDate,
                'fish_count' => $fishCount,
                'biomass_weight' => $biomassWeight,
                'supplier' => $supplier,
                'price' => $price,
                'delivery_cost' => $deliveryCost
            ];
            logActivity('create', 'planting', $plantingId, "Создана посадка: {$name}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Посадка успешно создана',
                'id' => $plantingId
            ]);
            break;
            
        case 'update':
            // Обновить посадку
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID посадки не указан');
            }
            
            // Проверка существования и получение старых данных
            $stmt = $pdo->prepare("SELECT * FROM plantings WHERE id = ?");
            $stmt->execute([$id]);
            $oldPlanting = $stmt->fetch();
            
            if (!$oldPlanting) {
                throw new Exception('Посадка не найдена');
            }
            
            $name = trim($data['name'] ?? '');
            $fishBreed = trim($data['fish_breed'] ?? '');
            $hatchDate = $data['hatch_date'] ?? null;
            $plantingDate = $data['planting_date'] ?? '';
            $fishCount = isset($data['fish_count']) ? (int)$data['fish_count'] : 0;
            $biomassWeight = isset($data['biomass_weight']) ? (float)$data['biomass_weight'] : null;
            $supplier = trim($data['supplier'] ?? '');
            $price = isset($data['price']) ? (float)$data['price'] : null;
            $deliveryCost = isset($data['delivery_cost']) ? (float)$data['delivery_cost'] : null;
            
            // Валидация
            if (empty($name)) {
                throw new Exception('Название обязательно для заполнения');
            }
            
            if (empty($fishBreed)) {
                throw new Exception('Порода рыбы обязательна для заполнения');
            }
            
            if (empty($plantingDate)) {
                throw new Exception('Дата посадки обязательна для заполнения');
            }
            
            if ($fishCount <= 0) {
                throw new Exception('Количество рыб должно быть больше 0');
            }
            
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE plantings SET
                    name = ?,
                    fish_breed = ?,
                    hatch_date = ?,
                    planting_date = ?,
                    fish_count = ?,
                    biomass_weight = ?,
                    supplier = ?,
                    price = ?,
                    delivery_cost = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name,
                $fishBreed,
                $hatchDate ?: null,
                $plantingDate,
                $fishCount,
                $biomassWeight,
                $supplier ?: null,
                $price,
                $deliveryCost,
                $id
            ]);
            
            // Формируем данные об изменениях
            $changes = [];
            if ($oldPlanting['name'] !== $name) {
                $changes['name'] = ['old' => $oldPlanting['name'], 'new' => $name];
            }
            if ($oldPlanting['fish_breed'] !== $fishBreed) {
                $changes['fish_breed'] = ['old' => $oldPlanting['fish_breed'], 'new' => $fishBreed];
            }
            if ($oldPlanting['hatch_date'] !== $hatchDate) {
                $changes['hatch_date'] = ['old' => $oldPlanting['hatch_date'], 'new' => $hatchDate];
            }
            if ($oldPlanting['planting_date'] !== $plantingDate) {
                $changes['planting_date'] = ['old' => $oldPlanting['planting_date'], 'new' => $plantingDate];
            }
            if ($oldPlanting['fish_count'] != $fishCount) {
                $changes['fish_count'] = ['old' => $oldPlanting['fish_count'], 'new' => $fishCount];
            }
            if ((float)$oldPlanting['biomass_weight'] != (float)$biomassWeight) {
                $changes['biomass_weight'] = ['old' => $oldPlanting['biomass_weight'], 'new' => $biomassWeight];
            }
            if ($oldPlanting['supplier'] !== $supplier) {
                $changes['supplier'] = ['old' => $oldPlanting['supplier'], 'new' => $supplier];
            }
            if ((float)$oldPlanting['price'] != (float)$price) {
                $changes['price'] = ['old' => $oldPlanting['price'], 'new' => $price];
            }
            if ((float)$oldPlanting['delivery_cost'] != (float)$deliveryCost) {
                $changes['delivery_cost'] = ['old' => $oldPlanting['delivery_cost'], 'new' => $deliveryCost];
            }
            
            // Логирование с данными об изменениях
            logActivity('update', 'planting', $id, "Обновлена посадка: {$name}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Посадка успешно обновлена'
            ]);
            break;
            
        case 'delete':
            // Удалить посадку
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID посадки не указан');
            }
            
            // Получение данных для лога
            $stmt = $pdo->prepare("SELECT * FROM plantings WHERE id = ?");
            $stmt->execute([$id]);
            $planting = $stmt->fetch();
            
            if (!$planting) {
                throw new Exception('Посадка не найдена');
            }
            
            // Получение информации о файлах
            $stmt = $pdo->prepare("SELECT id, original_name FROM planting_files WHERE planting_id = ?");
            $stmt->execute([$id]);
            $files = $stmt->fetchAll();
            
            // Удаление файлов
            $stmt = $pdo->prepare("SELECT file_path FROM planting_files WHERE planting_id = ?");
            $stmt->execute([$id]);
            $filePaths = $stmt->fetchAll();
            
            foreach ($filePaths as $file) {
                $filePath = __DIR__ . '/../' . $file['file_path'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            
            // Удаление
            $stmt = $pdo->prepare("DELETE FROM plantings WHERE id = ?");
            $stmt->execute([$id]);
            
            // Логирование с данными
            $changes = [
                'name' => $planting['name'],
                'fish_breed' => $planting['fish_breed'],
                'planting_date' => $planting['planting_date'],
                'fish_count' => $planting['fish_count'],
                'files_count' => count($files),
                'files' => array_column($files, 'original_name')
            ];
            logActivity('delete', 'planting', $id, "Удалена посадка: {$planting['name']}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Посадка успешно удалена'
            ]);
            break;
            
        case 'archive':
            // Архивировать/разархивировать посадку
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            $isArchived = isset($data['is_archived']) ? (int)$data['is_archived'] : 1;
            
            if (!$id) {
                throw new Exception('ID посадки не указан');
            }
            
            // Получение данных
            $stmt = $pdo->prepare("SELECT name, is_archived FROM plantings WHERE id = ?");
            $stmt->execute([$id]);
            $planting = $stmt->fetch();
            
            if (!$planting) {
                throw new Exception('Посадка не найдена');
            }
            
            // Обновление статуса
            $stmt = $pdo->prepare("UPDATE plantings SET is_archived = ? WHERE id = ?");
            $stmt->execute([$isArchived, $id]);
            
            // Логирование с данными
            $action = $isArchived ? 'архивирована' : 'разархивирована';
            $changes = [
                'is_archived' => ['old' => (bool)$planting['is_archived'], 'new' => (bool)$isArchived]
            ];
            logActivity('update', 'planting', $id, "Посадка {$action}: {$planting['name']}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => $isArchived ? 'Посадка архивирована' : 'Посадка разархивирована',
                'is_archived' => $isArchived
            ]);
            break;
            
        case 'delete_file':
            // Удалить файл
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $fileId = $data['file_id'] ?? 0;
            
            if (!$fileId) {
                throw new Exception('ID файла не указан');
            }
            
            // Получение данных файла
            $stmt = $pdo->prepare("SELECT * FROM planting_files WHERE id = ?");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch();
            
            if (!$file) {
                throw new Exception('Файл не найден');
            }
            
            // Удаление файла с диска
            $filePath = __DIR__ . '/../' . $file['file_path'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            
            // Удаление записи
            $stmt = $pdo->prepare("DELETE FROM planting_files WHERE id = ?");
            $stmt->execute([$fileId]);
            
            // Логирование с данными
            $changes = [
                'original_name' => $file['original_name'],
                'file_size' => $file['file_size'],
                'mime_type' => $file['mime_type'],
                'planting_id' => $file['planting_id']
            ];
            logActivity('delete', 'planting_file', $fileId, "Удален файл: {$file['original_name']}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Файл успешно удален'
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
