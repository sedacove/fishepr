<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Controllers\Api\PartialTransplantsController;
use App\Support\Request;

// Проверка прав администратора
if (!isAdmin()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен. Требуются права администратора'], JSON_UNESCAPED_UNICODE);
    exit;
}

$request = Request::fromGlobals();
$controller = new PartialTransplantsController();
$controller->handle($request);

