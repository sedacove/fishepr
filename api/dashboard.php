<?php
/**
 * API для настройки главного экрана (виджеты)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_layout.php';

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'layout';

try {
    $pdo = getDBConnection();
    $userId = getCurrentUserId();

    switch ($action) {
        case 'layout':
            $layout = getUserDashboardLayout($pdo, $userId);
            $available = getAvailableWidgetsForUser($layout);

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

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data) || !isset($data['layout'])) {
                throw new Exception('Некорректные данные макета');
            }

            $newLayout = normalizeDashboardLayout($data['layout']);
            saveUserDashboardLayout($pdo, $userId, $newLayout);

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

