<?php

namespace App\Support;

/**
 * Simple PSR-4 compliant autoloader to avoid requiring Composer for now.
 */
class Autoloader
{
    /**
     * @var array<string, string>
     */
    private array $prefixes = [];

    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass'], true, true);
    }

    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $this->prefixes[$prefix] = $baseDir;
    }

    private function loadClass(string $class): void
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (strpos($class, $prefix) !== 0) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (is_file($file)) {
                require_once $file;
            }
        }
    }
}

