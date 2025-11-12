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
        // Убеждаемся, что функция asset_url доступна в глобальной области видимости
        self::ensureAssetUrlFunction();

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

    private static function ensureAssetUrlFunction(): void
    {
        // Проверяем в глобальной области видимости
        if (function_exists('asset_url')) {
            return;
        }

        $debug = true; // Установить в false после отладки
        $logFile = __DIR__ . '/../../storage/debug.log';
        
        if ($debug) {
            @mkdir(dirname($logFile), 0775, true);
            $log = function($msg) use ($logFile) {
                file_put_contents($logFile, "[View::ensureAssetUrlFunction] " . date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
            };
            $log("asset_url() not found, trying to load config");
        }
        
        // Пытаемся загрузить config/config.php
        // Используем require вместо require_once, чтобы гарантировать выполнение кода
        $configPath = __DIR__ . '/../../config/config.php';
        if (is_file($configPath)) {
            // Проверяем, был ли файл уже загружен
            $alreadyIncluded = in_array(realpath($configPath), get_included_files());
            if ($debug) {
                $log("Config file exists, already included: " . ($alreadyIncluded ? 'YES' : 'NO'));
            }
            
            // Если файл уже был загружен, но функция не определена, 
            // значит была ошибка при первой загрузке - просто определяем fallback
            if ($alreadyIncluded && !function_exists('asset_url')) {
                if ($debug) {
                    $log("Config was loaded but asset_url() not defined - likely error in config file");
                }
            } else if (!$alreadyIncluded) {
                // Загружаем в глобальной области видимости
                // Используем try-catch для перехвата возможных ошибок
                try {
                    require_once $configPath;
                    if ($debug) {
                        $log("Config loaded, asset_url() exists: " . (function_exists('asset_url') ? 'YES' : 'NO'));
                    }
                } catch (Throwable $e) {
                    if ($debug) {
                        $log("Error loading config: " . $e->getMessage());
                    }
                    error_log("Error loading config/config.php in View::ensureAssetUrlFunction: " . $e->getMessage());
                }
            }
        } else {
            if ($debug) {
                $log("Config file not found at: $configPath");
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
            
            // Определяем функцию в глобальной области видимости
            // Проблема: функции, определенные внутри методов, могут не работать правильно
            // Используем eval для определения в глобальной области видимости
            $baseUrl = BASE_URL;
            if (!function_exists('asset_url')) {
                // Сохраняем BASE_URL в глобальной переменной для использования в функции
                $GLOBALS['_asset_url_base'] = $baseUrl;
                
                // Определяем функцию через eval в глобальной области видимости
                // Важно: eval выполняется в текущей области видимости, но функции всегда глобальны
                $funcDefinition = 'if (!function_exists("asset_url")) { function asset_url(string $relativePath): string { $baseUrl = isset($GLOBALS["_asset_url_base"]) ? $GLOBALS["_asset_url_base"] : (defined("BASE_URL") ? BASE_URL : "http://localhost/"); $relativePath = ltrim($relativePath, "/"); if ($relativePath === "") { return $baseUrl; } $separator = strpos($relativePath, "?") === false ? "?" : "&"; return $baseUrl . $relativePath . $separator . "v=" . time(); } }';
                
                // Выполняем eval в глобальной области видимости
                $result = eval($funcDefinition);
                
                if ($debug) {
                    $log("After eval, asset_url() exists: " . (function_exists('asset_url') ? 'YES' : 'NO'));
                    $log("Eval result: " . ($result === false ? 'FALSE (error)' : 'OK'));
                }
                
                // Если eval не сработал, пробуем через создание временного файла
                if (!function_exists('asset_url')) {
                    if ($debug) {
                        $log("Eval failed, trying temporary file approach");
                    }
                    
                    // Создаем временный файл с определением функции
                    $tempFile = sys_get_temp_dir() . '/asset_url_' . md5(__FILE__) . '.php';
                    $funcCode = "<?php\nif (!function_exists('asset_url')) {\n    function asset_url(string \$relativePath): string {\n        \$baseUrl = isset(\$GLOBALS['_asset_url_base']) ? \$GLOBALS['_asset_url_base'] : (defined('BASE_URL') ? BASE_URL : 'http://localhost/');\n        \$relativePath = ltrim(\$relativePath, '/');\n        if (\$relativePath === '') {\n            return \$baseUrl;\n        }\n        \$separator = strpos(\$relativePath, '?') === false ? '?' : '&';\n        return \$baseUrl . \$relativePath . \$separator . 'v=' . time();\n    }\n}\n";
                    
                    if (file_put_contents($tempFile, $funcCode)) {
                        require_once $tempFile;
                        @unlink($tempFile); // Удаляем временный файл
                        if ($debug) {
                            $log("After temp file, asset_url() exists: " . (function_exists('asset_url') ? 'YES' : 'NO'));
                        }
                    }
                }
            }
            
            if ($debug) {
                $log("Fallback asset_url() defined, exists: " . (function_exists('asset_url') ? 'YES' : 'NO'));
                // Финальная проверка - пробуем вызвать функцию
                if (function_exists('asset_url')) {
                    try {
                        $test = asset_url('test');
                        $log("Function call test successful: $test");
                    } catch (Throwable $e) {
                        $log("Function call test failed: " . $e->getMessage());
                    }
                } else {
                    $log("ERROR: Function still not defined after all attempts!");
                }
            }
        }
        
        // Финальная проверка перед возвратом
        if (!function_exists('asset_url')) {
            error_log("CRITICAL: asset_url() still not defined in View::ensureAssetUrlFunction()");
        }
    }
}

