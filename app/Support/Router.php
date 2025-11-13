<?php

namespace App\Support;

use App\Support\Exceptions\HttpException;

/**
 * Класс маршрутизатора
 * 
 * Обрабатывает маршрутизацию HTTP запросов к контроллерам:
 * - регистрация маршрутов (GET, POST)
 * - диспетчеризация запросов к соответствующим контроллерам
 * - поддержка базового пути (base path)
 */
class Router
{
    /**
     * @var array<string, array{controller: string, action: string}> Зарегистрированные маршруты
     */
    private array $routes = [];
    
    /**
     * @var string Базовый путь для всех маршрутов
     */
    private string $basePath = '';

    /**
     * Устанавливает базовый путь для всех маршрутов
     * 
     * @param string|null $basePath Базовый путь (например, '/fisherp')
     * @return void
     */
    public function setBasePath(?string $basePath): void
    {
        $basePath = $basePath ?? '';
        $basePath = trim($basePath);

        if ($basePath === '.' || $basePath === '/') {
            $this->basePath = '';
            return;
        }

        if ($basePath !== '') {
            $basePath = '/' . ltrim($basePath, '/');
            $basePath = rtrim($basePath, '/');
        }

        $this->basePath = $basePath;
    }

    /**
     * Регистрирует GET маршрут
     * 
     * @param string $path Путь маршрута (например, '/users')
     * @param string $handler Обработчик в формате 'Controller@action' (например, 'UsersController@index')
     * @return void
     */
    public function get(string $path, string $handler): void
    {
        $this->routes['GET ' . $this->normalizePath($path)] = $this->parseHandler($handler);
    }

    /**
     * Регистрирует POST маршрут
     * 
     * @param string $path Путь маршрута (например, '/users')
     * @param string $handler Обработчик в формате 'Controller@action' (например, 'UsersController@store')
     * @return void
     */
    public function post(string $path, string $handler): void
    {
        $this->routes['POST ' . $this->normalizePath($path)] = $this->parseHandler($handler);
    }

    /**
     * Диспетчеризирует запрос к соответствующему контроллеру
     * 
     * @param string $method HTTP метод (GET, POST, etc.)
     * @param string $uri URI запроса
     * @return mixed Результат выполнения действия контроллера
     * @throws HttpException Если маршрут не найден или контроллер/действие не существует
     */
    public function dispatch(string $method, string $uri): mixed
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = $this->stripBasePath($path);
        $key = strtoupper($method) . ' ' . $this->normalizePath($path);

        if (!isset($this->routes[$key])) {
            throw new HttpException(404, 'Route not found');
        }

        $target = $this->routes[$key];
        $controllerClass = $target['controller'];
        $action = $target['action'];

        if (!class_exists($controllerClass)) {
            throw new HttpException(500, "Controller {$controllerClass} not found");
        }

        $controller = new $controllerClass();
        if (!method_exists($controller, $action)) {
            throw new HttpException(500, "Action {$action} not found in {$controllerClass}");
        }

        return $controller->{$action}();
    }

    /**
     * Парсит обработчик маршрута в формат 'Controller@action'
     * 
     * @param string $handler Обработчик в формате 'Controller@action'
     * @return array Массив с ключами 'controller' и 'action'
     * @throws \InvalidArgumentException Если формат обработчика некорректен
     */
    private function parseHandler(string $handler): array
    {
        if (!str_contains($handler, '@')) {
            throw new \InvalidArgumentException('Route handler must be in "Controller@action" format');
        }
        [$controller, $action] = explode('@', $handler, 2);
        $controllerClass = '\\App\\Controllers\\' . $controller;

        return [
            'controller' => $controllerClass,
            'action' => $action,
        ];
    }

    /**
     * Нормализует путь маршрута (убирает лишние слэши)
     * 
     * @param string $path Путь для нормализации
     * @return string Нормализованный путь
     */
    private function normalizePath(string $path): string
    {
        $normalized = '/' . ltrim($path, '/');
        return rtrim($normalized, '/') ?: '/';
    }

    /**
     * Удаляет базовый путь из URI
     * 
     * @param string $path URI путь
     * @return string URI путь без базового пути
     */
    private function stripBasePath(string $path): string
    {
        if ($this->basePath === '') {
            return $path ?: '/';
        }

        if (strpos($path, $this->basePath) === 0) {
            $path = substr($path, strlen($this->basePath));
        }

        if ($path === '') {
            return '/';
        }

        return $path;
    }
}

