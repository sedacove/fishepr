<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/duty_helpers.php';

use App\Services\DashboardLayoutService;
use App\Services\DashboardWidgetRegistry;

/**
 * Контроллер главной страницы (дашборда)
 * 
 * Отвечает за отображение главной страницы с виджетами:
 * - загрузка макета виджетов пользователя
 * - подготовка данных для виджетов
 * - рендеринг страницы дашборда
 */
class DashboardController extends Controller
{
    /**
     * Конструктор контроллера
     * 
     * Проверяет авторизацию пользователя
     */
    public function __construct()
    {
        requireAuth();
    }

    /**
     * Отображает главную страницу с виджетами
     * 
     * Загружает макет виджетов пользователя, доступные виджеты,
     * настраивает диапазон дат для виджета дежурств и рендерит страницу.
     * 
     * @return string HTML содержимое страницы дашборда
     */
    public function index(): string
    {
        $pdo = getDBConnection();
        $currentUserId = getCurrentUserId();

        $registry = new DashboardWidgetRegistry();
        $layoutService = new DashboardLayoutService($pdo, $registry);

        $widgetDefinitions = $registry->all();
        $dashboardLayout = $layoutService->getUserLayout($currentUserId);
        $availableWidgets = $layoutService->getAvailableWidgets($dashboardLayout);

        $todayDutyDate = getTodayDutyDate();
        $dutyRangeStart = new \DateTime($todayDutyDate);
        $dutyRangeStart->modify('-1 day');
        $dutyRangeEnd = clone $dutyRangeStart;
        $dutyRangeEnd->modify('+6 day');

        $widgetsPayload = [];
        foreach ($widgetDefinitions as $key => $definition) {
            $widgetsPayload[$key] = [
                'title' => $definition['title'] ?? $key,
                'description' => $definition['description'] ?? '',
                'default' => !empty($definition['default']),
                'subtitle' => $key === 'duty_week'
                    ? $dutyRangeStart->format('d.m.Y') . ' — ' . $dutyRangeEnd->format('d.m.Y')
                    : ($definition['subtitle'] ?? ''),
            ];
        }

        $dashboardConfig = [
            'layout' => $dashboardLayout,
            'widgets' => $widgetsPayload,
            'dutyRange' => [
                'start' => $dutyRangeStart->format('Y-m-d'),
                'end' => $dutyRangeEnd->format('Y-m-d'),
                'label' => $dutyRangeStart->format('d.m.Y') . ' — ' . $dutyRangeEnd->format('d.m.Y'),
            ],
            'available' => $availableWidgets,
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
        ];

        $userDisplayName = $_SESSION['user_full_name'] ?? $_SESSION['user_login'] ?? 'Пользователь';

        return $this->view('dashboard.index', [
            'pageTitle' => 'Главная страница',
            'userName' => $userDisplayName,
            'dashboardConfig' => $dashboardConfig,
            'extra_styles' => ['assets/css/dashboard.css'],
            'extra_body_scripts' => ['assets/js/pages/dashboard.js'],
        ]);
    }
}

