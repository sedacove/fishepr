<?php
/**
 * API для управления настройками
 * Доступно только администраторам
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/_bootstrap.php';

// Проверка прав администратора
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Доступ запрещен. Требуются права администратора.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

use App\Controllers\Api\SettingsController;
use App\Support\Request;

try {
    $request = Request::fromGlobals();
    $controller = new SettingsController();
    $controller->handle($request);
} catch (Throwable $e) {
    http_response_code(500);
    $errorMessage = 'Внутренняя ошибка сервера';
    $errorDetails = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    
    error_log("Error in api/settings.php: " . $errorDetails . "\n" . $e->getTraceAsString());
    
    // В режиме разработки показываем детали ошибки
    $isDev = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;
    
    echo json_encode([
        'success' => false,
        'message' => $isDev ? $errorDetails : $errorMessage,
        'trace' => $isDev ? $e->getTraceAsString() : null
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
