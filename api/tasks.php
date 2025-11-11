<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../app/Support/Autoloader.php';

requireAuth();

$autoloader = new App\Support\Autoloader();
$autoloader->addNamespace('App', __DIR__ . '/../app');
$autoloader->register();

use App\Controllers\Api\TasksController;
use App\Support\Request;
use App\Support\JsonResponse;

$request = Request::fromGlobals();
$controller = new TasksController();
$controller->handle($request);

