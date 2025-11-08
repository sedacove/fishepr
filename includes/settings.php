<?php
/**
 * Функции для работы с настройками
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Получить значение настройки по ключу
 * 
 * @param string $key Ключ настройки
 * @param mixed $default Значение по умолчанию, если настройка не найдена
 * @return mixed Значение настройки
 */
function getSetting($key, $default = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $setting = $stmt->fetch();
        
        if ($setting) {
            return $setting['value'];
        }
        
        return $default;
    } catch (PDOException $e) {
        error_log("Ошибка получения настройки {$key}: " . $e->getMessage());
        return $default;
    }
}

/**
 * Получить значение настройки как целое число
 * 
 * @param string $key Ключ настройки
 * @param int $default Значение по умолчанию
 * @return int Значение настройки
 */
function getSettingInt($key, $default = 0) {
    $value = getSetting($key, $default);
    return (int)$value;
}
