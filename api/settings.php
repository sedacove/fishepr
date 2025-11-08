<?php
/**
 * API для управления настройками
 * Доступно только администраторам
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

// Требуем авторизацию и права администратора
requireAuth();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'list':
            // Получить все настройки
            $stmt = $pdo->query("
                SELECT 
                    s.*,
                    u.login as updated_by_login,
                    u.full_name as updated_by_name
                FROM settings s
                LEFT JOIN users u ON s.updated_by = u.id
                ORDER BY s.key ASC
            ");
            $settings = $stmt->fetchAll();
            
            // Преобразование дат
            foreach ($settings as &$setting) {
                $setting['updated_at'] = $setting['updated_at'] ? date('d.m.Y H:i', strtotime($setting['updated_at'])) : null;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
            break;
            
        case 'get':
            // Получить значение настройки по ключу
            $key = $_GET['key'] ?? '';
            
            if (!$key) {
                throw new Exception('Ключ настройки не указан');
            }
            
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $setting = $stmt->fetch();
            
            if (!$setting) {
                throw new Exception('Настройка не найдена');
            }
            
            echo json_encode([
                'success' => true,
                'value' => $setting['value']
            ]);
            break;
            
        case 'update':
            // Обновить настройку
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $key = $data['key'] ?? '';
            $value = $data['value'] ?? '';
            
            if (!$key) {
                throw new Exception('Ключ настройки не указан');
            }
            
            // Получаем старую настройку
            $stmt = $pdo->prepare("SELECT * FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $oldSetting = $stmt->fetch();
            
            if (!$oldSetting) {
                throw new Exception('Настройка не найдена');
            }
            
            $currentUserId = getCurrentUserId();
            
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE settings SET
                    value = ?,
                    updated_by = ?
                WHERE `key` = ?
            ");
            
            $stmt->execute([
                $value,
                $currentUserId,
                $key
            ]);
            
            // Формируем данные об изменениях
            $changes = [
                'key' => $key,
                'old_value' => $oldSetting['value'],
                'new_value' => $value
            ];
            
            // Логирование
            logActivity('update', 'setting', $oldSetting['id'], "Обновлена настройка: {$key}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Настройка успешно обновлена'
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
