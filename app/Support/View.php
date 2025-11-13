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
        
        // Пытаемся загрузить config/config.php
        $configPath = __DIR__ . '/../../config/config.php';
        if (is_file($configPath)) {
            $alreadyIncluded = in_array(realpath($configPath), get_included_files());
            if (!$alreadyIncluded) {
                try {
                    require_once $configPath;
                } catch (Throwable $e) {
                    error_log("Error loading config/config.php in View::ensureAssetUrlFunction: " . $e->getMessage());
                }
            }
        }
        
        // Если функция все еще не определена, определяем резервную версию
        if (!function_exists('asset_url')) {
            // Резервная функция, если config не загрузился
            if (!defined('BASE_URL')) {
                // Пытаемся определить BASE_URL из окружения
                $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
                $basePath = dirname($scriptName);
                $basePath = $basePath === '/' ? '' : $basePath;
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                define('BASE_URL', $protocol . '://' . $host . $basePath . '/');
            }
            
            // Определяем функцию через временный файл (надежный способ)
            $baseUrl = BASE_URL;
            $GLOBALS['_asset_url_base'] = $baseUrl;
            
            $tempFile = sys_get_temp_dir() . '/asset_url_' . md5(__FILE__) . '.php';
            $funcCode = "<?php\nif (!function_exists('asset_url')) {\n    function asset_url(string \$relativePath): string {\n        \$baseUrl = isset(\$GLOBALS['_asset_url_base']) ? \$GLOBALS['_asset_url_base'] : (defined('BASE_URL') ? BASE_URL : 'http://localhost/');\n        \$relativePath = ltrim(\$relativePath, '/');\n        if (\$relativePath === '') {\n            return \$baseUrl;\n        }\n        \$separator = strpos(\$relativePath, '?') === false ? '?' : '&';\n        return \$baseUrl . \$relativePath . \$separator . 'v=' . time();\n    }\n}\n";
            
            if (file_put_contents($tempFile, $funcCode)) {
                require_once $tempFile;
                @unlink($tempFile);
            }
        }
    }
}

