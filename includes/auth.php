<?php
/**
 * Функции авторизации
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Проверка авторизации пользователя
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_login']);
}

/**
 * Получить ID текущего пользователя
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Получить тип текущего пользователя
 * @return string|null
 */
function getCurrentUserType() {
    return $_SESSION['user_type'] ?? null;
}

/**
 * Проверка, является ли пользователь администратором
 * @return bool
 */
function isAdmin() {
    return getCurrentUserType() === USER_TYPE_ADMIN;
}

/**
 * Проверка, является ли пользователь обычным пользователем
 * @return bool
 */
function isUser() {
    return getCurrentUserType() === USER_TYPE_USER;
}

/**
 * Требовать авторизацию (редирект на страницу входа, если не авторизован)
 */
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

/**
 * Требовать права администратора
 */
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL);
        exit;
    }
}

/**
 * Авторизация пользователя
 * @param string $login
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function loginUser($login, $password) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ? AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Неверный логин или пароль',
                'user' => null
            ];
        }
        
        if (!password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Неверный логин или пароль',
                'user' => null
            ];
        }
        
        // Установка сессии
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_login'] = $user['login'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['user_full_name'] = $user['full_name'];
        
        return [
            'success' => true,
            'message' => 'Успешная авторизация',
            'user' => $user
        ];
        
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Ошибка базы данных: ' . $e->getMessage(),
            'user' => null
        ];
    }
}

/**
 * Выход пользователя
 */
function logoutUser() {
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}
