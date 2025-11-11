<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../app/Support/Autoloader.php';

$autoloader = new App\Support\Autoloader();
$autoloader->addNamespace('App', __DIR__ . '/../app');
$autoloader->register();

use App\Controllers\Api\SessionDetailsController;
use App\Support\Request;

$request = Request::fromGlobals();
$controller = new SessionDetailsController();
$controller->handle($request);

