<?php
/**
 * Функции для логирования действий пользователей
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

/**
 * Логирование действия пользователя
 * 
 * @param string $action Действие (create, update, delete, etc.)
 * @param string $entityType Тип сущности (pool, user, etc.)
 * @param int|null $entityId ID сущности
 * @param string|null $description Описание действия
 * @param array|string|null $changes Измененные данные (массив или JSON строка)
 * @return bool
 */
function logActivity($action, $entityType, $entityId = null, $description = null, $changes = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    try {
        $pdo = getDBConnection();
        
        $userId = getCurrentUserId();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Ограничение длины user_agent
        if ($userAgent && strlen($userAgent) > 255) {
            $userAgent = substr($userAgent, 0, 255);
        }
        
        // Преобразование изменений в JSON если это массив
        $changesJson = null;
        if ($changes !== null) {
            if (is_array($changes)) {
                $changesJson = json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                $changesJson = $changes;
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, entity_type, entity_id, description, changes, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $description,
            $changesJson,
            $ipAddress,
            $userAgent
        ]);
        
        return true;
    } catch (PDOException $e) {
        // Не прерываем выполнение при ошибке логирования
        error_log("Ошибка логирования: " . $e->getMessage());
        return false;
    }
}

/**
 * Получить логи действий
 * 
 * @param int $limit Лимит записей
 * @param int $offset Смещение
 * @param string|null $entityType Фильтр по типу сущности
 * @return array
 */
function getActivityLogs($limit = 100, $offset = 0, $entityType = null) {
    try {
        $pdo = getDBConnection();
        
        $sql = "
            SELECT 
                al.*,
                u.login as user_login,
                u.full_name as user_full_name
            FROM activity_log al
            LEFT JOIN users u ON al.user_id = u.id
        ";
        
        $params = [];
        
        if ($entityType) {
            $sql .= " WHERE al.entity_type = ?";
            $params[] = $entityType;
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Ошибка получения логов: " . $e->getMessage());
        return [];
    }
}
