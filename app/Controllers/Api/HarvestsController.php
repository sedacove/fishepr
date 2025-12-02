<?php

namespace App\Controllers\Api;

use App\Services\HarvestService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use RuntimeException;

/**
 * API контроллер для работы с отборами
 * 
 * Обрабатывает HTTP запросы к API endpoints для отборов:
 * - get_pools: получение списка бассейнов
 * - list: получение списка отборов для бассейна
 * - get: получение одного отбора
 * - create: создание нового отбора
 * - update: обновление отбора
 * - delete: удаление отбора
 * 
 * Авторизация проверяется в api/harvests.php
 */
class HarvestsController
{
    /**
     * @var HarvestService Сервис для работы с отборами
     */
    private HarvestService $service;

    /**
     * Конструктор контроллера
     * 
     * Инициализирует сервис для работы с отборами.
     * Авторизация проверяется в api/harvests.php.
     */
    public function __construct()
    {
        // Авторизация проверяется в api/harvests.php
        $this->service = new HarvestService(\getDBConnection());
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
        $userId = \getCurrentUserId();
        $isAdmin = \isAdmin();

        try {
            switch ($action) {
                case 'get_pools':
                    JsonResponse::success($this->service->getPools());
                    return;
                case 'get_active_sessions':
                    JsonResponse::success($this->service->getActiveSessions());
                    return;
                case 'list':
                    $sessionId = (int)$request->getQuery('session_id', 0);
                    if ($sessionId <= 0) {
                        throw new ValidationException('session_id', 'ID сессии не указан');
                    }
                    JsonResponse::success($this->service->listBySession($sessionId, $userId, $isAdmin));
                    return;
                case 'list_completed':
                    JsonResponse::success($this->service->listCompletedSessionsHarvests($userId, $isAdmin));
                    return;
                case 'get':
                    $id = (int)$request->getQuery('id', 0);
                    if ($id <= 0) {
                        throw new ValidationException('id', 'ID отбора не указан', 400);
                    }
                    JsonResponse::success($this->service->get($id));
                    return;
                case 'create':
                    $this->requirePost($request);
                    $payload = $request->getJsonBody();
                    $id = $this->service->create($payload, $userId, $isAdmin);
                    JsonResponse::success(['id' => $id], 'Отбор успешно добавлен');
                    return;
                case 'update':
                    $this->requirePost($request);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    if ($id <= 0) {
                        throw new ValidationException('id', 'ID отбора не указан', 400);
                    }
                    $this->service->update($id, $payload, $userId, $isAdmin);
                    JsonResponse::success(null, 'Отбор успешно обновлён');
                    return;
                case 'delete':
                    $this->requirePost($request);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    if ($id <= 0) {
                        throw new ValidationException('id', 'ID отбора не указан', 400);
                    }
                    $this->service->delete($id, $isAdmin);
                    JsonResponse::success(null, 'Отбор успешно удалён');
                    return;
                default:
                    throw new DomainException('Неизвестное действие');
            }
        } catch (ValidationException $e) {
            JsonResponse::send([
                'success' => false,
                'message' => $e->getMessage(),
                'field' => $e->getField(),
            ], $e->getCode() ?: 422);
        } catch (DomainException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            $status = $e->getCode() ?: 404;
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
            
            error_log("Error in HarvestsController::handle(): " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
            
            JsonResponse::error($message, $status);
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


