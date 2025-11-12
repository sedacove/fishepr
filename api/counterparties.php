<?php

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

use App\Controllers\Api\CounterpartiesController;
use App\Support\Request;

try {
    $request = Request::fromGlobals();
    $controller = new CounterpartiesController();
    $controller->handle($request);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Внутренняя ошибка сервера: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log("Error in api/counterparties.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}


