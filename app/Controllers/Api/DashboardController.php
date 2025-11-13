<?php

namespace App\Controllers\Api;

use App\Services\DashboardLayoutService;
use App\Services\DashboardWidgetRegistry;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

require_once __DIR__ . '/../../../includes/dashboard_layout.php';

/**
 * API контроллер для работы с дашбордом
 * 
 * Обрабатывает HTTP запросы к API endpoints для дашборда:
 * - layout: получение макета виджетов пользователя
 * - save_layout: сохранение макета виджетов пользователя
 * 
 * Авторизация проверяется в api/dashboard.php
 */
class DashboardController
{
    /**
     * @var DashboardLayoutService Сервис для работы с макетом дашборда
     */
    private DashboardLayoutService $layoutService;
    
    /**
     * @var int ID текущего пользователя
     */
    private int $userId;

    /**
     * Конструктор контроллера
     * 
     * Инициализирует сервис для работы с макетом дашборда.
     * Авторизация проверяется в api/dashboard.php.
     */
    public function __construct()
    {
        // Авторизация проверяется в api/dashboard.php
        $pdo = \getDBConnection();
        $this->userId = \getCurrentUserId();
        
        $registry = dashboardWidgetRegistry();
        $this->layoutService = new DashboardLayoutService($pdo, $registry);
    }

    /**
     * Обрабатывает входящий запрос и направляет его к соответствующему обработчику
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'layout');

        try {
            switch ($action) {
                case 'layout':
                    $this->handleLayout();
                    return;

                case 'save_layout':
                    $this->requirePost($request);
                    $this->handleSaveLayout($request);
                    return;

                default:
                    throw new RuntimeException('Неизвестное действие', 400);
            }
        } catch (RuntimeException $e) {
            $status = $e->getCode() ?: 400;
            JsonResponse::error($e->getMessage(), $status);
        } catch (\Throwable $e) {
            $status = $e->getCode() ?: 500;
            
            // В режиме разработки показываем детали ошибки
            $isDev = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;
            
            if ($status >= 500) {
                $message = $isDev 
                    ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                    : 'Внутренняя ошибка сервера';
            } else {
                $message = $e->getMessage();
            }
            
            error_log("Error in DashboardController::handle(): " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
            
            JsonResponse::error($message, $status);
        }
    }

    /**
     * Обрабатывает запрос на получение макета виджетов пользователя
     * 
     * Возвращает текущий макет виджетов и список доступных виджетов.
     * 
     * @return void
     */
    private function handleLayout(): void
    {
        $layout = $this->layoutService->getUserLayout($this->userId);
        $available = $this->layoutService->getAvailableWidgets($layout);

        JsonResponse::success([
            'layout' => $layout,
            'widgets' => $available,
        ]);
    }

    /**
     * Обрабатывает запрос на сохранение макета виджетов пользователя
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws RuntimeException Если данные макета некорректны
     */
    private function handleSaveLayout(Request $request): void
    {
        $payload = $request->getJsonBody();
        
        if (!is_array($payload) || !isset($payload['layout'])) {
            throw new RuntimeException('Некорректные данные макета', 400);
        }

        $normalized = $this->layoutService->normalizeLayout($payload['layout']);
        $this->layoutService->saveUserLayout($this->userId, $normalized);

        JsonResponse::success(null, 'Макет сохранён');
    }

    /**
     * Проверяет, что запрос использует метод POST
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws RuntimeException Если метод не POST
     */
    private function requirePost(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new RuntimeException('Метод не поддерживается', 405);
        }
    }
}

