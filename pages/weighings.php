<?php
require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../app/Support/Autoloader.php';

if (!class_exists(App\Support\Autoloader::class, false)) {
    $autoloader = new App\Support\Autoloader();
    $autoloader->addNamespace('App', __DIR__ . '/../app');
    $autoloader->register();
}

$controller = new App\Controllers\WeighingsController();
echo $controller->index();

