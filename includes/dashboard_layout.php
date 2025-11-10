<?php

require_once __DIR__ . '/dashboard_widgets.php';

/**
 * Получить список всех доступных виджетов.
 *
 * @return array
 */
function getAllDashboardWidgets(): array
{
    static $widgets = null;
    if ($widgets === null) {
        $widgets = require __DIR__ . '/dashboard_widgets.php';
    }
    return $widgets;
}

/**
 * Получить виджеты по умолчанию (ключи в порядке отображения).
 *
 * @return array
 */
function getDefaultDashboardLayout(): array
{
    $widgets = getAllDashboardWidgets();
    $defaults = [];
    foreach ($widgets as $key => $widget) {
        if (!empty($widget['default'])) {
            $defaults[] = $key;
        }
    }
    return [
        'columns' => distributeWidgetsAcrossColumns($defaults, 2),
    ];
}

/**
 * Получить текущий макет пользователя.
 *
 * @param PDO $pdo
 * @param int $userId
 * @return array
 */
function getUserDashboardLayout(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT layout FROM user_dashboard_layouts WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row && !empty($row['layout'])) {
        $layout = json_decode($row['layout'], true);
        if (is_array($layout)) {
            return normalizeDashboardLayout($layout);
        }
    }
    return getDefaultDashboardLayout();
}

/**
 * Сохранить макет пользователя.
 *
 * @param PDO $pdo
 * @param int $userId
 * @param array $layout
 * @return void
 */
function saveUserDashboardLayout(PDO $pdo, int $userId, array $layout): void
{
    $normalized = normalizeDashboardLayout($layout);
    $jsonLayout = json_encode($normalized);
    if ($jsonLayout === false) {
        throw new Exception('Не удалось сохранить макет виджетов');
    }
    $stmt = $pdo->prepare('INSERT INTO user_dashboard_layouts (user_id, layout) VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE layout = VALUES(layout), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([$userId, $jsonLayout]);
}

/**
 * Вернуть список доступных для добавления виджетов.
 *
 * @param array $currentLayout
 * @return array
 */
function getAvailableWidgetsForUser(array $layout): array
{
    $widgets = getAllDashboardWidgets();
    $current = flattenDashboardColumns(normalizeDashboardLayout($layout)['columns']);
    $available = [];
    foreach ($widgets as $key => $definition) {
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
 * Распределяет список виджетов по колонкам.
 *
 * @param array $widgetKeys
 * @param int $columnsCount
 * @return array
 */
function distributeWidgetsAcrossColumns(array $widgetKeys, int $columnsCount = 2): array
{
    $columnsCount = max(1, $columnsCount);
    $columns = array_fill(0, $columnsCount, []);
    $index = 0;
    $allWidgets = getAllDashboardWidgets();
    foreach ($widgetKeys as $key) {
        if (!is_string($key) || !isset($allWidgets[$key])) {
            continue;
        }
        $columns[$index % $columnsCount][] = $key;
        $index++;
    }
    return $columns;
}

/**
 * Нормализует макет колонок.
 *
 * @param array $layout
 * @return array{columns: array}
 */
function normalizeDashboardLayout(array $layout): array
{
    $widgets = getAllDashboardWidgets();
    $columnsCount = 2;
    $columns = [];

    if (isset($layout['columns']) && is_array($layout['columns'])) {
        $columns = $layout['columns'];
    } elseif (array_is_list($layout)) {
        $columns = distributeWidgetsAcrossColumns($layout, $columnsCount);
    }

    $columns = sanitizeLayoutColumns($columns, $columnsCount, $widgets);

    // Ensure default widgets exist
    $present = flattenDashboardColumns($columns);
    foreach ($widgets as $key => $definition) {
        if (!empty($definition['default']) && !in_array($key, $present, true)) {
            $targetIndex = getShortestColumnIndex($columns);
            $columns[$targetIndex][] = $key;
            $present[] = $key;
        }
    }

    return ['columns' => $columns];
}

/**
 * Очищает колонки от дубликатов и невалидных значений.
 */
function sanitizeLayoutColumns(array $columns, int $columnsCount, array $allWidgets): array
{
    $sanitized = [];
    $used = [];

    foreach ($columns as $column) {
        $list = [];
        if (is_array($column)) {
            foreach ($column as $key) {
                if (!is_string($key) || !isset($allWidgets[$key])) {
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

    while (count($sanitized) < $columnsCount) {
        $sanitized[] = [];
    }

    if (count($sanitized) > $columnsCount) {
        $sanitized = array_slice($sanitized, 0, $columnsCount);
    }

    return $sanitized;
}

/**
 * Возвращает самый короткий столбец.
 */
function getShortestColumnIndex(array $columns): int
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

/**
 * Преобразует колонки в плоский массив.
 */
function flattenDashboardColumns(array $columns): array
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
