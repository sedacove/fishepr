<?php

namespace App\Support;

use App\Support\Exceptions\HttpException;

class Router
{
    /**
     * @var array<string, array{controller: string, action: string}>
     */
    private array $routes = [];
    private string $basePath = '';

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
     * Register a GET route.
     */
    public function get(string $path, string $handler): void
    {
        $this->routes['GET ' . $this->normalizePath($path)] = $this->parseHandler($handler);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, string $handler): void
    {
        $this->routes['POST ' . $this->normalizePath($path)] = $this->parseHandler($handler);
    }

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

    private function normalizePath(string $path): string
    {
        $normalized = '/' . ltrim($path, '/');
        return rtrim($normalized, '/') ?: '/';
    }

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

