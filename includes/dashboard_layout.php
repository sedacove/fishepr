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
    return $defaults;
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
            return $layout;
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
    $jsonLayout = json_encode(array_values($layout));
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
function getAvailableWidgetsForUser(array $currentLayout): array
{
    $widgets = getAllDashboardWidgets();
    $available = [];
    foreach ($widgets as $key => $definition) {
        $available[] = [
            'id' => $key,
            'title' => $definition['title'] ?? $key,
            'description' => $definition['description'] ?? '',
            'in_layout' => in_array($key, $currentLayout, true),
            'default' => !empty($definition['default']),
        ];
    }
    return $available;
}

