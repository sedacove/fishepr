<?php
/**
 * API endpoint для работы с дампами базы данных
 * 
 * Доступно только администраторам
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Controllers\Api\DatabaseBackupController;
use App\Support\Request;

$controller = new DatabaseBackupController();
$request = new Request();
$controller->handle($request);

