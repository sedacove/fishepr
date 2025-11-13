<?php

namespace App\Controllers\Api;

use App\Services\SessionService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

/**
 * API контроллер для работы с сессиями
 * 
 * Обрабатывает HTTP запросы к API endpoints для сессий:
 * - list: получение списка сессий (активных или завершенных)
 * - get: получение одной сессии
 * - get_pools: получение списка активных бассейнов
 * - get_plantings: получение списка активных посадок
 * - create: создание новой сессии
 * - update: обновление сессии
 * - complete: завершение сессии
 * - delete: удаление сессии
 * 
 * Доступен только администраторам.
 */
class SessionsController
{
    /**
     * @var SessionService Сервис для работы с сессиями
     */
    private SessionService $service;

    /**
     * Конструктор контроллера
     * 
     * Проверяет авторизацию и права администратора.
     * Инициализирует сервис для работы с сессиями.
     */
    public function __construct()
    {
        \requireAuth();
        \requireAdmin();
        $this->service = new SessionService(\getDBConnection());
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
        $method = $request->getMethod();
        $currentUserId = \getCurrentUserId();

        try {
            switch ($action) {
                case 'list':
                    $completed = (int) $request->getQuery('completed', 0) === 1;
                    JsonResponse::success($this->service->list($completed));
                    return;

                case 'get':
                    $id = (int) $request->getQuery('id', 0);
                    JsonResponse::success($this->service->get($id));
                    return;

                case 'get_pools':
                    JsonResponse::success($this->service->getActivePools());
                    return;

                case 'get_plantings':
                    JsonResponse::success($this->service->getActivePlantings());
                    return;

                case 'create':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = $this->service->create($payload, $currentUserId);
                    JsonResponse::success(['id' => $id], 'Сессия успешно создана');
                    return;

                case 'update':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int) ($payload['id'] ?? 0);
                    $this->service->update($id, $payload);
                    JsonResponse::success(null, 'Сессия успешно обновлена');
                    return;

                case 'complete':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int) ($payload['id'] ?? 0);
                    $result = $this->service->complete($id, $payload);
                    JsonResponse::success($result, 'Сессия успешно завершена');
                    return;

                case 'delete':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int) ($payload['id'] ?? 0);
                    $this->service->delete($id);
                    JsonResponse::success(null, 'Сессия успешно удалена');
                    return;

                default:
                    throw new RuntimeException('Неизвестное действие', 400);
            }
        } catch (ValidationException $e) {
            JsonResponse::send([
                'success' => false,
                'message' => $e->getMessage(),
                'field' => $e->getField(),
            ], $e->getCode() ?: 422);
        } catch (RuntimeException $e) {
            JsonResponse::error($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Throwable $e) {
            JsonResponse::error('Внутренняя ошибка сервера', 500);
        }
    }

    /**
     * Проверяет, что запрос использует метод POST
     * 
     * @param string $method HTTP метод запроса
     * @return void
     * @throws RuntimeException Если метод не POST
     */
    private function assertPost(string $method): void
    {
        if ($method !== 'POST') {
            throw new RuntimeException('Метод не поддерживается', 405);
        }
    }
}


