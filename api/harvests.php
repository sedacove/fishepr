<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Controllers\Api\HarvestsController;
use App\Support\Request;

try {
    $request = Request::fromGlobals();
    $controller = new HarvestsController();
    $controller->handle($request);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Внутренняя ошибка сервера: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log("Error in api/harvests.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

