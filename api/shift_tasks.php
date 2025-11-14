<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/_bootstrap.php';

use App\Controllers\Api\ShiftTasksController;
use App\Support\Request;

try {
    $request = Request::fromGlobals();
    $controller = new ShiftTasksController();
    $controller->handle($request);
} catch (Throwable $e) {
    http_response_code(500);
    $errorMessage = 'Внутренняя ошибка сервера';
    $errorDetails = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();

    error_log("Error in api/shift_tasks.php: " . $errorDetails . "\n" . $e->getTraceAsString());

    $isDev = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || str_contains($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1');

    echo json_encode([
        'success' => false,
        'message' => $isDev ? $errorDetails : $errorMessage,
        'trace' => $isDev ? $e->getTraceAsString() : null,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}


