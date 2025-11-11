<?php
/**
 * API для настройки главного экрана (виджеты)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_layout.php';
require_once __DIR__ . '/../app/Support/Autoloader.php';

use App\Services\DashboardLayoutService;
use App\Services\DashboardWidgetRegistry;
use App\Support\Autoloader;

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'layout';

try {
    $autoloader = new Autoloader();
    $autoloader->addNamespace('App', __DIR__ . '/../app');
    $autoloader->register();

    $pdo = getDBConnection();
    $userId = getCurrentUserId();

    $registry = dashboardWidgetRegistry();
    $layoutService = new DashboardLayoutService($pdo, $registry);

    switch ($action) {
        case 'layout':
            $layout = $layoutService->getUserLayout($userId);
            $available = $layoutService->getAvailableWidgets($layout);

            echo json_encode([
                'success' => true,
                'data' => [
                    'layout' => $layout,
                    'widgets' => $available,
                ],
            ]);
            break;

        case 'save_layout':
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['layout'])) {
                throw new Exception('Некорректные данные макета');
            }

            $normalized = $layoutService->normalizeLayout($data['layout']);
            $layoutService->saveUserLayout($userId, $normalized);

            echo json_encode([
                'success' => true,
                'message' => 'Макет сохранён',
            ]);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

