<?php

namespace App\Support;

class View
{
    private static string $basePath;
    private static ?string $layout = null;
    private static array $shared = [];

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, DIRECTORY_SEPARATOR);
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function make(string $template, array $data = []): string
    {
        // Убеждаемся, что функция asset_url доступна
        if (!function_exists('asset_url')) {
            // Пытаемся загрузить config/config.php
            $configPath = __DIR__ . '/../../config/config.php';
            $debug = true; // Установить в false после отладки
            $logFile = __DIR__ . '/../../storage/debug.log';
            
            if ($debug) {
                @mkdir(dirname($logFile), 0775, true);
                $log = function($msg) use ($logFile) {
                    file_put_contents($logFile, "[View::make] " . date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
                };
                $log("asset_url() not found, trying to load config from: $configPath");
                $log("Config file exists: " . (is_file($configPath) ? 'YES' : 'NO'));
            }
            
            if (is_file($configPath)) {
                require_once $configPath;
                if ($debug) {
                    $log("Config loaded, asset_url() exists: " . (function_exists('asset_url') ? 'YES' : 'NO'));
                }
            }
            
            // Если функция все еще не определена, определяем резервную версию
            if (!function_exists('asset_url')) {
                if ($debug) {
                    $log("Still no asset_url(), defining fallback");
                }
                
                // Резервная функция, если config не загрузился
                if (!defined('BASE_URL')) {
                    // Пытаемся определить BASE_URL из окружения
                    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
                    $basePath = dirname($scriptName);
                    $basePath = $basePath === '/' ? '' : $basePath;
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    define('BASE_URL', $protocol . '://' . $host . $basePath . '/');
                    if ($debug) {
                        $log("Defined BASE_URL: " . BASE_URL);
                    }
                }
                
                if (!function_exists('asset_url')) {
                    function asset_url(string $relativePath): string
                    {
                        $relativePath = ltrim($relativePath, '/');
                        if ($relativePath === '') {
                            return BASE_URL;
                        }
                        $separator = strpos($relativePath, '?') === false ? '?' : '&';
                        return BASE_URL . $relativePath . $separator . 'v=' . time();
                    }
                    if ($debug) {
                        $log("Fallback asset_url() defined");
                    }
                }
            }
        }

        $templatePath = self::templatePath($template);
        if (!is_file($templatePath)) {
            throw new \RuntimeException("View '{$template}' not found at {$templatePath}");
        }

        extract(self::$shared, EXTR_SKIP);
        extract($data, EXTR_SKIP);

        ob_start();
        include $templatePath;
        $content = ob_get_clean();

        if (self::$layout) {
            $layout = self::$layout;
            self::$layout = null;
            return self::make($layout, array_merge($data, ['content' => $content]));
        }

        return $content;
    }

    public static function extends(string $layout): void
    {
        self::$layout = $layout;
    }

    private static function templatePath(string $template): string
    {
        $relative = str_replace(['.', '\\'], DIRECTORY_SEPARATOR, $template);
        return self::$basePath . DIRECTORY_SEPARATOR . $relative . '.php';
    }
}

