<?php
require_once __DIR__ . '/../app/bootstrap.php';

$controller = new App\Controllers\NewsController();
echo $controller->index();

