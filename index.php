<?php

use App\Support\Exceptions\HttpException;

$router = require __DIR__ . '/app/bootstrap.php';

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = str_replace('\\', '/', dirname($scriptName));
$basePath = $basePath === '\\' ? '/' : $basePath;
$router->setBasePath($basePath);

$router->get('/', 'DashboardController@index');
$router->get('/work', 'WorkController@index');
$router->get('/tasks', 'TasksController@index');
$router->get('/meter-readings', 'MeterReadingsController@index');
$router->get('/session-details', 'SessionDetailsController@show');
$router->get('/measurements', 'MeasurementsController@index');
$router->get('/mortality', 'MortalityController@index');
$router->get('/counterparties', 'CounterpartiesController@index');
$router->get('/harvests', 'HarvestsController@index');
$router->get('/weighings', 'WeighingsController@index');
$router->get('/news', 'NewsController@index');
$router->get('/meters', 'MetersController@index');
$router->get('/pools', 'PoolsController@index');
$router->get('/plantings', 'PlantingsController@index');
$router->get('/feeds', 'FeedsController@index');
$router->get('/sessions', 'SessionsController@index');
$router->get('/users', 'UsersController@index');
$router->get('/settings', 'SettingsController@index');
$router->get('/logs', 'LogsController@index');
$router->get('/finances', 'FinancesController@index');
$router->get('/payroll', 'PayrollController@index');
$router->get('/duty-calendar', 'DutyCalendarController@index');
$router->get('/shift-tasks', 'ShiftTasksController@index');
$router->get('/partial-transplants', 'PartialTransplantsController@index');
$router->get('/reports/harvests', 'ReportsController@harvests');

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
    
    // Логируем ошибку
    error_log("Unhandled exception in index.php: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine() . "\n" . $exception->getTraceAsString());
    
    if (function_exists('logMessage')) {
        logMessage('error', 'Unhandled exception on front controller', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
    
    // В режиме разработки показываем детали ошибки
    $isDev = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;
    
    if ($isDev) {
        echo '<h1>Внутренняя ошибка сервера</h1>';
        echo '<pre>';
        echo htmlspecialchars($exception->getMessage() . "\n\n" . $exception->getFile() . ':' . $exception->getLine() . "\n\n" . $exception->getTraceAsString());
        echo '</pre>';
    } else {
        echo 'Произошла внутренняя ошибка сервера.';
    }
}

