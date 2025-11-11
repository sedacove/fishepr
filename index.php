<?php

use App\Support\Exceptions\HttpException;

$router = require __DIR__ . '/app/bootstrap.php';

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = str_replace('\\', '/', dirname($scriptName));
$basePath = $basePath === '\\' ? '/' : $basePath;
$router->setBasePath($basePath);

$router->get('/', 'DashboardController@index');
$router->get('/index.php', 'DashboardController@index');
$router->get('/work', 'WorkController@index');
$router->get('/work.php', 'WorkController@index');
$router->get('/tasks', 'TasksController@index');
$router->get('/tasks.php', 'TasksController@index');
$router->get('/meter-readings', 'MeterReadingsController@index');
$router->get('/meter_readings.php', 'MeterReadingsController@index');
$router->get('/session-details', 'SessionDetailsController@show');
$router->get('/session_details.php', 'SessionDetailsController@show');
$router->get('/measurements', 'MeasurementsController@index');
$router->get('/measurements.php', 'MeasurementsController@index');
$router->get('/mortality', 'MortalityController@index');
$router->get('/mortality.php', 'MortalityController@index');
$router->get('/counterparties', 'CounterpartiesController@index');
$router->get('/counterparties.php', 'CounterpartiesController@index');
$router->get('/harvests', 'HarvestsController@index');
$router->get('/harvests.php', 'HarvestsController@index');
$router->get('/weighings', 'WeighingsController@index');
$router->get('/weighings.php', 'WeighingsController@index');

try {
    $response = $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

    if (is_string($response)) {
        echo $response;
    } elseif (is_array($response)) {
        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
} catch (HttpException $httpException) {
    http_response_code($httpException->getStatusCode());
    echo $httpException->getMessage() ?: 'Ошибка';
} catch (Throwable $exception) {
    http_response_code(500);
    if (function_exists('logMessage')) {
        logMessage('error', 'Unhandled exception on front controller', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
    echo 'Произошла внутренняя ошибка сервера.';
}

