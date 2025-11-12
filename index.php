<?php

use App\Support\Exceptions\HttpException;

// Отладка (удалить после диагностики)
$debug = true; // Установить в false после отладки
$logFile = __DIR__ . '/storage/debug.log';

if ($debug) {
    @mkdir(dirname($logFile), 0775, true);
    $log = function($message) use ($logFile) {
        $timestamp = date('Y-m-d H:i:s');
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1]['function'] ?? 'unknown';
        $line = $trace[1]['line'] ?? 0;
        file_put_contents($logFile, "[$timestamp] [$caller:$line] $message\n", FILE_APPEND);
    };
    
    $log("=== REQUEST START ===");
    $log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NULL'));
    $log("SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'NULL'));
    $log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'NULL'));
    $log("QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'NULL'));
    $log("SESSION: " . (isset($_SESSION) ? json_encode($_SESSION) : 'NOT_STARTED'));
}

$router = require __DIR__ . '/app/bootstrap.php';

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = str_replace('\\', '/', dirname($scriptName));
$basePath = $basePath === '\\' ? '/' : $basePath;
$router->setBasePath($basePath);

if ($debug) {
    $log("BasePath set to: $basePath");
}

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
$router->get('/news', 'NewsController@index');
$router->get('/news.php', 'NewsController@index');
$router->get('/meters', 'MetersController@index');
$router->get('/meters.php', 'MetersController@index');
$router->get('/pools', 'PoolsController@index');
$router->get('/pools.php', 'PoolsController@index');
$router->get('/plantings', 'PlantingsController@index');
$router->get('/plantings.php', 'PlantingsController@index');
$router->get('/sessions', 'SessionsController@index');
$router->get('/sessions.php', 'SessionsController@index');
$router->get('/users', 'UsersController@index');
$router->get('/users.php', 'UsersController@index');

try {
    if ($debug) {
        $log("Dispatching route...");
    }
    
    $response = $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

    if ($debug) {
        $log("Response type: " . gettype($response));
    }

    if (is_string($response)) {
        echo $response;
    } elseif (is_array($response)) {
        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    
    if ($debug) {
        $log("=== REQUEST END ===");
    }
} catch (HttpException $httpException) {
    if ($debug) {
        $log("HttpException: " . $httpException->getStatusCode() . " - " . $httpException->getMessage());
    }
    http_response_code($httpException->getStatusCode());
    echo $httpException->getMessage() ?: 'Ошибка';
} catch (Throwable $exception) {
    if ($debug) {
        $log("Exception: " . $exception->getMessage());
        $log("Trace: " . $exception->getTraceAsString());
    }
    http_response_code(500);
    if (function_exists('logMessage')) {
        logMessage('error', 'Unhandled exception on front controller', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
    echo 'Произошла внутренняя ошибка сервера.';
}

