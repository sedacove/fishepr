<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Controllers\Api\ReportsController;

$action = $_GET['action'] ?? '';

if (empty($action)) {
    \App\Support\JsonResponse::error('Не указано действие', 400);
    exit;
}

$controller = new ReportsController();
$controller->handle($action);

