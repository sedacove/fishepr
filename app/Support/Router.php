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
        $debug = true; // Установить в false после отладки
        $logFile = __DIR__ . '/../../storage/debug.log';
        
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $originalPath = $path;
        $path = $this->stripBasePath($path);
        $key = strtoupper($method) . ' ' . $this->normalizePath($path);

        if ($debug) {
            @mkdir(dirname($logFile), 0775, true);
            $log = function($msg) use ($logFile) {
                file_put_contents($logFile, "[Router::dispatch] " . date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
            };
            $log("URI: $uri");
            $log("Original path: $originalPath");
            $log("BasePath: " . $this->basePath);
            $log("Path after stripBasePath: $path");
            $log("Normalized path: " . $this->normalizePath($path));
            $log("Route key: $key");
            $log("Available routes: " . implode(', ', array_keys($this->routes)));
        }

        if (!isset($this->routes[$key])) {
            if ($debug) {
                $log("Route not found! Looking for: $key");
            }
            throw new HttpException(404, 'Route not found: ' . $key);
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

        $debug = true; // Установить в false после отладки
        $logFile = __DIR__ . '/../../storage/debug.log';
        
        if ($debug) {
            @mkdir(dirname($logFile), 0775, true);
            $log = function($msg) use ($logFile) {
                file_put_contents($logFile, "[Router::stripBasePath] " . date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
            };
            $log("Input path: $path");
            $log("BasePath: " . $this->basePath);
        }

        if (strpos($path, $this->basePath) === 0) {
            $path = substr($path, strlen($this->basePath));
            if ($debug) {
                $log("Path starts with basePath, stripped to: $path");
            }
        } else {
            if ($debug) {
                $log("Path does NOT start with basePath, keeping original");
            }
        }

        if ($path === '') {
            if ($debug) {
                $log("Path is empty after strip, returning '/'");
            }
            return '/';
        }

        if ($debug) {
            $log("Final path: $path");
        }

        return $path;
    }
}

