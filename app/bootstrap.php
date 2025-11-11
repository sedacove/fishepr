<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Support/Autoloader.php';
require_once __DIR__ . '/Support/Router.php';
require_once __DIR__ . '/Support/View.php';

use App\Support\Autoloader;
use App\Support\Router;
use App\Support\View;

$autoloader = new Autoloader();
$autoloader->addNamespace('App', __DIR__);
$autoloader->register();

View::setBasePath(__DIR__ . '/Views');

$router = new Router();

return $router;

