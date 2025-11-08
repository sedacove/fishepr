<?php
/**
 * Основная конфигурация приложения
 */

// Настройки сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Установить в 1 для HTTPS

// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Часовой пояс
date_default_timezone_set('Europe/Moscow');

// Кодировка
mb_internal_encoding('UTF-8');

// Базовый URL (измените при необходимости)
define('BASE_URL', 'http://localhost/fisherp/');

// Типы пользователей
define('USER_TYPE_ADMIN', 'admin');
define('USER_TYPE_USER', 'user');

// Подключение к БД
require_once __DIR__ . '/database.php';
