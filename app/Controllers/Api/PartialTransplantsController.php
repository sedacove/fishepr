<?php

namespace App\Controllers\Api;

use App\Services\PartialTransplantService;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use Exception;
use RuntimeException;

/**
 * API контроллер для работы с частичными пересадками
 * 
 * Обрабатывает HTTP запросы к API endpoints для пересадок:
 * - list: получение списка всех пересадок
 * - get: получение одной пересадки
 * - create: создание новой пересадки
 * - revert: откат пересадки
 * 
 * Авторизация проверяется в api/partial_transplants.php
 */
class PartialTransplantsController
{
    /**
     * @var PartialTransplantService Сервис для работы с пересадками
     */
    private PartialTransplantService $service;
    
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
     * Авторизация проверяется в api/partial_transplants.php.
     */
    public function __construct()
    {
        // Авторизация проверяется в api/partial_transplants.php
        $pdo = \getDBConnection();
        $this->service = new PartialTransplantService($pdo);
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
            // Проверка прав доступа - только администраторы
            if (!$this->isAdmin) {
                throw new DomainException('Доступ запрещен. Требуются права администратора', 403);
            }

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
                case 'revert':
                    $this->requirePost($request);
                    $this->handleRevert($request);
                    break;
                case 'preview':
                    $this->handlePreview($request);
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
     * Обрабатывает запрос на получение списка пересадок
     * 
     * GET /api/partial_transplants.php?action=list
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleList(Request $request): void
    {
        $transplants = $this->service->list();
        $data = array_map(function ($transplant) {
            return $transplant->toArray();
        }, $transplants);
        JsonResponse::success($data);
    }

    /**
     * Обрабатывает запрос на получение одной пересадки
     * 
     * GET /api/partial_transplants.php?action=get&id=1
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если ID пересадки не указан
     */
    private function handleGet(Request $request): void
    {
        $id = (int)$request->getQuery('id', 0);
        if ($id <= 0) {
            throw new DomainException('ID пересадки не указан');
        }
        $transplant = $this->service->get($id);
        JsonResponse::success($transplant->toArray());
    }

    /**
     * Обрабатывает запрос на создание новой пересадки
     * 
     * POST /api/partial_transplants.php?action=create
     * Body: {"transplant_date": "2025-01-15", "source_session_id": 1, "recipient_session_id": 2, "weight": 100.5, "fish_count": 500}
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleCreate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $transplantId = $this->service->createTransplant($payload, $this->userId);
        JsonResponse::success(['id' => $transplantId], 'Пересадка успешно создана');
    }

    /**
     * Обрабатывает запрос на откат пересадки
     * 
     * POST /api/partial_transplants.php?action=revert
     * Body: {"id": 1}
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если ID пересадки не указан
     */
    private function handleRevert(Request $request): void
    {
        $payload = $request->getJsonBody();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new DomainException('ID пересадки не указан');
        }
        $this->service->revertTransplant($id, $this->userId);
        JsonResponse::success([], 'Пересадка успешно откатана');
    }

    /**
     * Обрабатывает запрос на получение предпросмотра пересадки
     * 
     * GET /api/partial_transplants.php?action=preview&source_session_id=1&recipient_session_id=2&weight=100&fish_count=500
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handlePreview(Request $request): void
    {
        $payload = [
            'source_session_id' => (int)$request->getQuery('source_session_id', 0),
            'recipient_session_id' => (int)$request->getQuery('recipient_session_id', 0),
            'weight' => (float)$request->getQuery('weight', 0),
            'fish_count' => (int)$request->getQuery('fish_count', 0),
        ];
        
        $preview = $this->service->getTransplantPreview($payload);
        JsonResponse::success($preview);
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

