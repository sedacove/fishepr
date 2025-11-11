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

