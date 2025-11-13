<?php

namespace App\Controllers\Api;

use App\Services\PoolService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

class PoolsController
{
    private PoolService $service;

    public function __construct()
    {
        \requireAuth();
        \requireAdmin();
        $this->service = new PoolService(\getDBConnection());
    }

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

    private function assertPost(string $method): void
    {
        if ($method !== 'POST') {
            throw new RuntimeException('Метод не поддерживается', 405);
        }
    }
}


