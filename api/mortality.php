<?php

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/telegram.php';

use App\Controllers\Api\MortalityController;
use App\Support\Request;

try {
    $request = Request::fromGlobals();
    $controller = new MortalityController();
    $controller->handle($request);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Внутренняя ошибка сервера: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log("Error in api/mortality.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

