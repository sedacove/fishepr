<?php

namespace App\Controllers\Api;

use App\Services\MeterReadingService;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use Exception;
use RuntimeException;

/**
 * API контроллер для работы с показаниями приборов учета
 * 
 * Обрабатывает HTTP запросы к API endpoints для показаний:
 * - list: получение списка показаний для прибора
 * - get: получение одного показания
 * - create: создание нового показания
 * - update: обновление показания
 * - delete: удаление показания
 * - widget_data: данные для виджета дашборда
 * - widget_meters: список приборов для виджета
 * 
 * Авторизация проверяется в api/meter_readings.php
 */
class MeterReadingsController
{
    /**
     * @var MeterReadingService Сервис для работы с показаниями
     */
    private MeterReadingService $service;
    
    /**
     * @var int ID текущего пользователя
     */
    private int $userId;
    
    /**
     * @var bool Является ли пользователь администратором
     */
    private bool $isAdmin;

    /**
     * Конструктор контроллера
     * 
     * Инициализирует сервис и получает информацию о текущем пользователе.
     * Авторизация проверяется в api/meter_readings.php.
     */
    public function __construct()
    {
        // Авторизация проверяется в api/meter_readings.php
        $pdo = \getDBConnection();
        $this->service = new MeterReadingService($pdo);
        $this->userId = \getCurrentUserId();
        $this->isAdmin = \isAdmin();
    }

    /**
     * Обрабатывает входящий запрос и направляет его к соответствующему обработчику
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'list');

        try {
            switch ($action) {
                case 'list':
                    $this->handleList($request);
                    break;
                case 'get':
                    $this->handleGet($request);
                    break;
                case 'create':
                    $this->requirePost($request);
                    $this->handleCreate($request);
                    break;
                case 'update':
                    $this->requirePost($request);
                    $this->handleUpdate($request);
                    break;
                case 'delete':
                    $this->requirePost($request);
                    $this->handleDelete($request);
                    break;
                case 'widget_data':
                    $this->handleWidgetData($request);
                    break;
                case 'widget_meters':
                    $this->handleWidgetMeters($request);
                    break;
                default:
                    throw new Exception('Неизвестное действие');
            }
        } catch (DomainException|RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (Exception $e) {
            JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Обрабатывает запрос на получение списка показаний
     * 
     * GET /api/meter_readings.php?action=list&meter_id=1
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если ID прибора не указан
     */
    private function handleList(Request $request): void
    {
        $meterId = (int)$request->getQuery('meter_id', 0);
        if ($meterId <= 0) {
            throw new DomainException('ID прибора не указан');
        }
        $readings = $this->service->listReadings($meterId, $this->userId, $this->isAdmin);
        JsonResponse::success($readings);
    }

    /**
     * Обрабатывает запрос на получение одного показания
     * 
     * GET /api/meter_readings.php?action=get&id=1
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если ID показания не указан
     */
    private function handleGet(Request $request): void
    {
        $id = (int)$request->getQuery('id', 0);
        if ($id <= 0) {
            throw new DomainException('ID показания не указан');
        }
        $reading = $this->service->getReading($id, $this->userId, $this->isAdmin);
        JsonResponse::success($reading);
    }

    /**
     * Обрабатывает запрос на создание нового показания
     * 
     * POST /api/meter_readings.php?action=create
     * Body: {"meter_id": 1, "reading_value": 123.45, "recorded_at": "2025-11-13T10:00"}
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleCreate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $readingId = $this->service->createReading($payload, $this->userId, $this->isAdmin);
        JsonResponse::success(['id' => $readingId], 'Показание добавлено');
    }

    /**
     * Обрабатывает запрос на обновление показания
     * 
     * POST /api/meter_readings.php?action=update
     * Body: {"id": 1, "reading_value": 123.45, "recorded_at": "2025-11-13T10:00"}
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleUpdate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $this->service->updateReading($payload, $this->userId, $this->isAdmin);
        JsonResponse::success([], 'Показание обновлено');
    }

    /**
     * Обрабатывает запрос на удаление показания
     * 
     * POST /api/meter_readings.php?action=delete
     * Body: {"id": 1}
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если ID показания не указан
     */
    private function handleDelete(Request $request): void
    {
        $payload = $request->getJsonBody();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new DomainException('ID показания не указан');
        }
        $this->service->deleteReading($id, $this->userId, $this->isAdmin);
        JsonResponse::success([], 'Показание удалено');
    }

    /**
     * Обрабатывает запрос на получение данных для виджета дашборда
     * 
     * GET /api/meter_readings.php?action=widget_data&meter_id=1
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если ID прибора не указан
     */
    private function handleWidgetData(Request $request): void
    {
        $meterId = (int)$request->getQuery('meter_id', 0);
        if ($meterId <= 0) {
            throw new DomainException('ID прибора не указан');
        }
        $data = $this->service->getWidgetData($meterId);
        JsonResponse::success($data);
    }

    /**
     * Обрабатывает запрос на получение списка приборов для виджета
     * 
     * GET /api/meter_readings.php?action=widget_meters
     * 
     * Возвращает только приборы с данными за последние 14 дней.
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleWidgetMeters(Request $request): void
    {
        $meters = $this->service->getAllMeters();
        JsonResponse::success($meters);
    }

    /**
     * Проверяет, что запрос использует метод POST
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если метод не POST
     */
    private function requirePost(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new DomainException('Метод не поддерживается');
        }
    }
}
