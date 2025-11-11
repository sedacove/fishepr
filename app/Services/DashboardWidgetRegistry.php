<?php

namespace App\Services;

class DashboardWidgetRegistry
{
    /**
     * @var array<string, array>
     */
    private array $widgets;

    public function __construct(?array $widgets = null)
    {
        if ($widgets !== null) {
            $this->widgets = $widgets;
            return;
        }

        $path = __DIR__ . '/../../includes/dashboard_widgets.php';
        $this->widgets = file_exists($path) ? require $path : [];
    }

    /**
     * @return array<string, array>
     */
    public function all(): array
    {
        return $this->widgets;
    }

    public function get(string $key): ?array
    {
        return $this->widgets[$key] ?? null;
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->widgets);
    }

    /**
     * @return string[]
     */
    public function defaultKeys(): array
    {
        $defaults = [];
        foreach ($this->widgets as $key => $definition) {
            if (!empty($definition['default'])) {
                $defaults[] = $key;
            }
        }
        return $defaults;
    }
}

