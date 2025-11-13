<?php

namespace App\Controllers\Api;

use App\Services\MeterService;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use Exception;
use RuntimeException;

/**
 * API контроллер для работы с приборами учета
 * 
 * Обрабатывает HTTP запросы к API endpoints для приборов:
 * - list: получение списка приборов (публичный, доступен всем)
 * - list_admin: получение списка приборов (административный, только для админов)
 * - get: получение одного прибора (только для админов)
 * - create: создание нового прибора (только для админов)
 * - update: обновление прибора (только для админов)
 * - delete: удаление прибора (только для админов)
 * 
 * Авторизация проверяется в api/meters.php
 */
class MetersController
{
    /**
     * @var MeterService Сервис для работы с приборами
     */
    private MeterService $service;
    
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
     * Авторизация проверяется в api/meters.php.
     */
    public function __construct()
    {
        // Авторизация проверяется в api/meters.php
        $pdo = \getDBConnection();
        $this->service = new MeterService($pdo);
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
                    $this->handleList();
                    break;
                case 'list_admin':
                    $this->ensureAdmin();
                    $this->handleListAdmin();
                    break;
                case 'get':
                    $this->ensureAdmin();
                    $this->handleGet($request);
                    break;
                case 'create':
                    $this->ensureAdmin();
                    $this->requirePost($request);
                    $this->handleCreate($request);
                    break;
                case 'update':
                    $this->ensureAdmin();
                    $this->requirePost($request);
                    $this->handleUpdate($request);
                    break;
                case 'delete':
                    $this->ensureAdmin();
                    $this->requirePost($request);
                    $this->handleDelete($request);
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
     * Обрабатывает запрос на получение публичного списка приборов
     * 
     * GET /api/meters.php?action=list
     * 
     * Доступен всем авторизованным пользователям.
     * 
     * @return void
     */
    private function handleList(): void
    {
        $meters = $this->service->listPublic();
        JsonResponse::success($meters);
    }

    /**
     * Обрабатывает запрос на получение административного списка приборов
     * 
     * GET /api/meters.php?action=list_admin
     * 
     * Доступен только администраторам.
     * 
     * @return void
     */
    private function handleListAdmin(): void
    {
        $meters = $this->service->listAdmin();
        JsonResponse::success($meters);
    }

    /**
     * Обрабатывает запрос на получение одного прибора
     * 
     * GET /api/meters.php?action=get&id=1
     * 
     * Доступен только администраторам.
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если ID прибора не указан
     */
    private function handleGet(Request $request): void
    {
        $id = (int)$request->getQuery('id', 0);
        if ($id <= 0) {
            throw new DomainException('ID прибора не указан');
        }
        $meter = $this->service->getMeter($id);
        JsonResponse::success($meter);
    }

    /**
     * Обрабатывает запрос на создание нового прибора
     * 
     * POST /api/meters.php?action=create
     * Body: {"name": "Счетчик воды", "description": "Описание"}
     * 
     * Доступен только администраторам.
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleCreate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $id = $this->service->createMeter($payload, $this->userId);
        JsonResponse::success(['id' => $id], 'Прибор учета добавлен');
    }

    /**
     * Обрабатывает запрос на обновление прибора
     * 
     * POST /api/meters.php?action=update
     * Body: {"id": 1, "name": "Счетчик воды", "description": "Описание"}
     * 
     * Доступен только администраторам.
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleUpdate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $this->service->updateMeter($payload);
        JsonResponse::success([], 'Прибор учета обновлен');
    }

    /**
     * Обрабатывает запрос на удаление прибора
     * 
     * POST /api/meters.php?action=delete
     * Body: {"id": 1}
     * 
     * Доступен только администраторам.
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если ID прибора не указан
     */
    private function handleDelete(Request $request): void
    {
        $payload = $request->getJsonBody();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new DomainException('ID прибора не указан');
        }
        $this->service->deleteMeter($id);
        JsonResponse::success([], 'Прибор учета удален');
    }

    /**
     * Проверяет, является ли пользователь администратором
     * 
     * @return void
     * @throws DomainException Если пользователь не является администратором
     */
    private function ensureAdmin(): void
    {
        if (!$this->isAdmin) {
            throw new DomainException('Доступ запрещен');
        }
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
