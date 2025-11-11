<?php

namespace App\Controllers\Api;

use App\Services\UserService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

class UsersController
{
    private UserService $service;

    public function __construct()
    {
        \requireAuth();
        \requireAdmin();
        $this->service = new UserService(\getDBConnection());
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
                    $id = (int)$request->getQuery('id', 0);
                    JsonResponse::success($this->service->get($id));
                    return;

                case 'create':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = $this->service->create($payload, $currentUserId);
                    JsonResponse::success(['id' => $id], 'Пользователь успешно создан');
                    return;

                case 'update':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    $this->service->update($id, $payload, $currentUserId);
                    JsonResponse::success(null, 'Пользователь успешно обновлен');
                    return;

                case 'delete':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    $this->service->delete($id, $currentUserId);
                    JsonResponse::success(null, 'Пользователь успешно удален');
                    return;

                case 'toggle_active':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    $status = $this->service->toggleActive($id, $currentUserId);
                    JsonResponse::success(
                        ['is_active' => $status ? 1 : 0],
                        $status ? 'Пользователь разблокирован' : 'Пользователь заблокирован'
                    );
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


