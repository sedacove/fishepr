<?php

/**
 * Файл инициализации приложения
 * 
 * Выполняет начальную настройку приложения:
 * - загрузка конфигурации
 * - регистрация автозагрузчика классов
 * - настройка системы представлений (views)
 * - создание и возврат экземпляра маршрутизатора
 * 
 * Этот файл подключается в index.php и api/*.php для инициализации
 * всех необходимых компонентов приложения
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/debug.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/Support/Autoloader.php';
require_once __DIR__ . '/Support/Router.php';
require_once __DIR__ . '/Support/View.php';

use App\Support\Autoloader;
use App\Support\Router;
use App\Support\View;

// Регистрация автозагрузчика классов (PSR-4)
$autoloader = new Autoloader();
$autoloader->addNamespace('App', __DIR__);
$autoloader->register();

// Настройка базового пути для представлений
View::setBasePath(__DIR__ . '/Views');

$debugModeEnabled = (bool)getSettingInt('debug_mode', 0);
DebugProfiler::enable($debugModeEnabled);
View::share('debugModeEnabled', $debugModeEnabled);

// Создание и возврат маршрутизатора
$router = new Router();

return $router;

