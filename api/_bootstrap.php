<?php
/**
 * Общий bootstrap для API endpoints
 * Проверяет авторизацию и возвращает JSON ошибку вместо редиректа
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/debug.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../app/Support/Autoloader.php';

$debugModeEnabled = (bool) getSettingInt('debug_mode', 0);
DebugProfiler::enable($debugModeEnabled);

// Проверка авторизации для API (возвращаем JSON вместо редиректа)
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Требуется авторизация'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$autoloader = new App\Support\Autoloader();
$autoloader->addNamespace('App', __DIR__ . '/../app');
$autoloader->register();

