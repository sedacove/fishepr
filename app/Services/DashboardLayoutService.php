<?php

namespace App\Services;

use PDO;
use JsonException;

class DashboardLayoutService
{
    private const DEFAULT_COLUMNS = 2;

    public function __construct(
        private readonly PDO $pdo,
        private readonly DashboardWidgetRegistry $registry,
        private readonly int $columnsCount = self::DEFAULT_COLUMNS
    ) {
    }

    public function getUserLayout(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT layout FROM user_dashboard_layouts WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if ($row && !empty($row['layout'])) {
            $layout = json_decode($row['layout'], true);
            if (is_array($layout)) {
                return $this->normalizeLayout($layout);
            }
        }

        return $this->getDefaultLayout();
    }

    public function saveUserLayout(int $userId, array $layout): void
    {
        $normalized = $this->normalizeLayout($layout);

        try {
            $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Не удалось сохранить макет виджетов: ' . $exception->getMessage(), 0, $exception);
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO user_dashboard_layouts (user_id, layout)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE layout = VALUES(layout), updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([$userId, $json]);
    }

    public function getDefaultLayout(): array
    {
        $defaultKeys = $this->registry->defaultKeys();
        return [
            'columns' => $this->distributeWidgetsAcrossColumns($defaultKeys)
        ];
    }

    /**
     * @param array $layout
     * @return array[]
     */
    public function getAvailableWidgets(array $layout): array
    {
        $normalized = $this->normalizeLayout($layout);
        $current = $this->flattenColumns($normalized['columns']);
        $available = [];
        foreach ($this->registry->all() as $key => $definition) {
            $available[] = [
                'id' => $key,
                'title' => $definition['title'] ?? $key,
                'description' => $definition['description'] ?? '',
                'in_layout' => in_array($key, $current, true),
                'default' => !empty($definition['default']),
            ];
        }
        return $available;
    }

    /**
     * @param array $widgetKeys
     * @return array[]
     */
    public function distributeWidgetsAcrossColumns(array $widgetKeys): array
    {
        $columns = array_fill(0, $this->columnsCount, []);
        $index = 0;
        foreach ($widgetKeys as $key) {
            if (!is_string($key) || !$this->registry->exists($key)) {
                continue;
            }
            $columns[$index % $this->columnsCount][] = $key;
            $index++;
        }
        return $columns;
    }

    public function normalizeLayout(array $layout): array
    {
        $columns = [];

        if (isset($layout['columns']) && is_array($layout['columns'])) {
            $columns = $layout['columns'];
        } elseif ($this->isSequentialArray($layout)) {
            $columns = $this->distributeWidgetsAcrossColumns($layout);
        }

        $columns = $this->sanitizeColumns($columns);
        $present = $this->flattenColumns($columns);

        foreach ($this->registry->defaultKeys() as $defaultKey) {
            if (!in_array($defaultKey, $present, true)) {
                $targetIndex = $this->getShortestColumnIndex($columns);
                $columns[$targetIndex][] = $defaultKey;
                $present[] = $defaultKey;
            }
        }

        return ['columns' => $columns];
    }

    /**
     * @param array $columns
     * @return array[]
     */
    public function sanitizeColumns(array $columns): array
    {
        $sanitized = [];
        $used = [];

        foreach ($columns as $column) {
            $list = [];
            if (is_array($column)) {
                foreach ($column as $key) {
                    if (!is_string($key) || !$this->registry->exists($key)) {
                        continue;
                    }
                    if (in_array($key, $used, true)) {
                        continue;
                    }
                    $list[] = $key;
                    $used[] = $key;
                }
            }
            $sanitized[] = $list;
        }

        while (count($sanitized) < $this->columnsCount) {
            $sanitized[] = [];
        }

        if (count($sanitized) > $this->columnsCount) {
            $sanitized = array_slice($sanitized, 0, $this->columnsCount);
        }

        return $sanitized;
    }

    /**
     * @param array $columns
     * @return string[]
     */
    public function flattenColumns(array $columns): array
    {
        $flat = [];
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            foreach ($column as $widgetKey) {
                if (is_string($widgetKey)) {
                    $flat[] = $widgetKey;
                }
            }
        }
        return $flat;
    }

    public function getShortestColumnIndex(array $columns): int
    {
        $minIndex = 0;
        $minValue = PHP_INT_MAX;
        foreach ($columns as $index => $column) {
            $length = is_array($column) ? count($column) : 0;
            if ($length < $minValue) {
                $minValue = $length;
                $minIndex = $index;
            }
        }
        return $minIndex;
    }

    private function isSequentialArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }
}

