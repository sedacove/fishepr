<?php
/**
 * API для работы с показаниями приборов учета
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../includes/settings.php';

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

try {
    $pdo = getDBConnection();
    $userId = getCurrentUserId();
    $isAdmin = isAdmin();
    $editTimeoutMinutes = max(0, getSettingInt('meter_reading_edit_timeout_minutes', 30));

    $canModify = function (array $reading) use ($userId, $isAdmin, $editTimeoutMinutes): bool {
        if ($isAdmin) {
            return true;
        }
        if ((int)$reading['recorded_by'] !== $userId) {
            return false;
        }
        $recordedAt = strtotime($reading['recorded_at']);
        $diffMinutes = (time() - $recordedAt) / 60;
        return $diffMinutes <= $editTimeoutMinutes;
    };

    $getReading = function (PDO $pdo, int $id): array {
        $stmt = $pdo->prepare("
            SELECT 
                mr.*,
                u.login AS recorded_by_login,
                u.full_name AS recorded_by_name
            FROM meter_readings mr
            LEFT JOIN users u ON u.id = mr.recorded_by
            WHERE mr.id = ?
        ");
        $stmt->execute([$id]);
        $reading = $stmt->fetch();
        if (!$reading) {
            throw new Exception('Показание не найдено');
        }
        return $reading;
    };

    switch ($action) {
        case 'list':
            $meterId = isset($_GET['meter_id']) ? (int)$_GET['meter_id'] : 0;
            if ($meterId <= 0) {
                throw new Exception('ID прибора не указан');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    mr.*,
                    u.login AS recorded_by_login,
                    u.full_name AS recorded_by_name
                FROM meter_readings mr
                LEFT JOIN users u ON u.id = mr.recorded_by
                WHERE mr.meter_id = ?
                ORDER BY mr.recorded_at DESC, mr.id DESC
            ");
            $stmt->execute([$meterId]);
            $readings = $stmt->fetchAll();

            foreach ($readings as &$reading) {
                $reading['recorded_at_display'] = date('d.m.Y H:i', strtotime($reading['recorded_at']));
                $reading['recorded_by_label'] = $reading['recorded_by_name']
                    ? $reading['recorded_by_name'] . ' (' . $reading['recorded_by_login'] . ')'
                    : $reading['recorded_by_login'];
                $reading['can_edit'] = $canModify($reading);
                $reading['can_delete'] = $reading['can_edit'];
            }

            echo json_encode([
                'success' => true,
                'data' => $readings
            ]);
            break;

        case 'get':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID показания не указан');
            }

            $reading = $getReading($pdo, $id);
            $reading['can_edit'] = $canModify($reading);
            $reading['recorded_at_display'] = date('d.m.Y H:i', strtotime($reading['recorded_at']));

            echo json_encode([
                'success' => true,
                'data' => $reading
            ]);
            break;

        case 'create':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Пустые данные');
            }

            $meterId = isset($data['meter_id']) ? (int)$data['meter_id'] : 0;
            $value = isset($data['reading_value']) ? (float)$data['reading_value'] : null;

            if ($meterId <= 0) {
                throw new Exception('Прибор учета не выбран');
            }
            if ($value === null) {
                throw new Exception('Введите показание');
            }

            $stmt = $pdo->prepare("SELECT id FROM meters WHERE id = ?");
            $stmt->execute([$meterId]);
            if (!$stmt->fetch()) {
                throw new Exception('Прибор учета не найден');
            }

            $stmt = $pdo->prepare("
                INSERT INTO meter_readings (meter_id, reading_value, recorded_at, recorded_by)
                VALUES (?, ?, NOW(), ?)
            ");
            $stmt->execute([$meterId, $value, $userId]);

            $readingId = (int)$pdo->lastInsertId();

            logActivity('create', 'meter_reading', $readingId, 'Добавлено показание прибора учета', [
                'meter_id' => $meterId,
                'reading_value' => $value
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Показание добавлено'
            ]);
            break;

        case 'update':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Пустые данные');
            }

            $id = isset($data['id']) ? (int)$data['id'] : 0;
            $value = isset($data['reading_value']) ? (float)$data['reading_value'] : null;

            if ($id <= 0) {
                throw new Exception('ID показания не указан');
            }
            if ($value === null) {
                throw new Exception('Введите показание');
            }

            $reading = $getReading($pdo, $id);
            if (!$canModify($reading)) {
                throw new Exception('Вы не можете редактировать это показание');
            }

            $stmt = $pdo->prepare("
                UPDATE meter_readings
                SET reading_value = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$value, $id]);

            logActivity('update', 'meter_reading', $id, 'Обновлено показание прибора учета', [
                'reading_value' => ['old' => $reading['reading_value'], 'new' => $value]
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Показание обновлено'
            ]);
            break;

        case 'delete':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID показания не указан');
            }

            $reading = $getReading($pdo, $id);
            if (!$isAdmin && !$canModify($reading)) {
                throw new Exception('Вы не можете удалить это показание');
            }

            $stmt = $pdo->prepare("DELETE FROM meter_readings WHERE id = ?");
            $stmt->execute([$id]);

            logActivity('delete', 'meter_reading', $id, 'Удалено показание прибора учета', [
                'meter_id' => $reading['meter_id'],
                'reading_value' => $reading['reading_value']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Показание удалено'
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
}

