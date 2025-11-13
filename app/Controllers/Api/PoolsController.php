<?php

namespace App\Controllers\Api;

use App\Services\PoolService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

/**
 * API контроллер для работы с бассейнами
 * 
 * Обрабатывает HTTP запросы к API endpoints для бассейнов:
 * - list: получение списка всех бассейнов
 * - get: получение одного бассейна
 * - create: создание нового бассейна
 * - update: обновление бассейна
 * - delete: удаление бассейна
 * - update_order: изменение порядка сортировки бассейнов
 * 
 * Доступен только администраторам.
 */
class PoolsController
{
    /**
     * @var PoolService Сервис для работы с бассейнами
     */
    private PoolService $service;

    /**
     * Конструктор контроллера
     * 
     * Проверяет авторизацию и права администратора.
     * Инициализирует сервис для работы с бассейнами.
     */
    public function __construct()
    {
        \requireAuth();
        \requireAdmin();
        $this->service = new PoolService(\getDBConnection());
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
                    JsonResponse::success($this->service->list());
                    return;

                case 'get':
                    $id = (int) $request->getQuery('id', 0);
                    JsonResponse::success($this->service->get($id));
                    return;

                case 'create':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = $this->service->create($payload, $currentUserId);
                    JsonResponse::success(['id' => $id], 'Бассейн успешно создан');
                    return;

                case 'update':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int) ($payload['id'] ?? 0);
                    $this->service->update($id, $payload);
                    JsonResponse::success(null, 'Бассейн успешно обновлен');
                    return;

                case 'delete':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int) ($payload['id'] ?? 0);
                    $this->service->delete($id);
                    JsonResponse::success(null, 'Бассейн успешно удален');
                    return;

                case 'update_order':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $order = $payload['order'] ?? [];
                    $this->service->reorder(is_array($order) ? $order : []);
                    JsonResponse::success(null, 'Порядок сортировки обновлен');
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


