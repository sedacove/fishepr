<?php

use App\Services\DashboardLayoutService;
use App\Services\DashboardWidgetRegistry;

function dashboardWidgetRegistry(): DashboardWidgetRegistry
{
    static $instance = null;
    if ($instance === null) {
        $instance = new DashboardWidgetRegistry();
    }
    return $instance;
}

function dashboardLayoutService(?\PDO $pdo = null, int $columnsCount = 2): DashboardLayoutService
{
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    return new DashboardLayoutService($pdo, dashboardWidgetRegistry(), $columnsCount);
}

function isSequentialSettingsArray(array $array): bool
{
    return array_keys($array) === range(0, count($array) - 1);
}

/**
 * Получить список всех доступных виджетов.
 *
 * @return array
 */
function getAllDashboardWidgets(): array
{
    return dashboardWidgetRegistry()->all();
}

/**
 * Получить виджеты по умолчанию (ключи в порядке отображения).
 *
 * @return array
 */
function getDefaultDashboardLayout(): array
{
    return dashboardLayoutService()->getDefaultLayout();
}

/**
 * Получить текущий макет пользователя.
 *
 * @param PDO $pdo
 * @param int $userId
 * @return array
 */
function getUserDashboardLayout(\PDO $pdo, int $userId): array
{
    return dashboardLayoutService($pdo)->getUserLayout($userId);
}

/**
 * Сохранить макет пользователя.
 *
 * @param PDO $pdo
 * @param int $userId
 * @param array $layout
 * @return void
 */
function saveUserDashboardLayout(\PDO $pdo, int $userId, array $layout): void
{
    dashboardLayoutService($pdo)->saveUserLayout($userId, $layout);
}

/**
 * Вернуть список доступных для добавления виджетов.
 */
function getAvailableWidgetsForUser(array $layout): array
{
    return dashboardLayoutService()->getAvailableWidgets($layout);
}

/**
 * Распределяет список виджетов по колонкам.
 */
function distributeWidgetsAcrossColumns(array $widgetKeys, int $columnsCount = 2): array
{
    return dashboardLayoutService(null, $columnsCount)->distributeWidgetsAcrossColumns($widgetKeys);
}

/**
 * Нормализует макет колонок.
 */
function normalizeDashboardLayout(array $layout): array
{
    return dashboardLayoutService()->normalizeLayout($layout);
}

/**
 * Очищает колонки от дубликатов и невалидных значений.
 */
function sanitizeLayoutColumns(array $columns, int $columnsCount, array $allWidgets = []): array
{
    return dashboardLayoutService(null, $columnsCount)->sanitizeColumns($columns);
}

/**
 * Возвращает самый короткий столбец.
 */
function getShortestColumnIndex(array $columns): int
{
    return dashboardLayoutService()->getShortestColumnIndex($columns);
}

/**
 * Преобразует колонки в плоский массив.
 */
function flattenDashboardColumns(array $columns): array
{
    return dashboardLayoutService()->flattenColumns($columns);
}
