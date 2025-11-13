<?php

namespace App\Controllers\Api;

use App\Services\PlantingService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

class PlantingsController
{
    private PlantingService $service;

    public function __construct()
    {
        \requireAuth();
        \requireAdmin();
        $this->service = new PlantingService(\getDBConnection());
    }

    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'list');
        $method = $request->getMethod();
        $currentUserId = \getCurrentUserId();

        try {
            switch ($action) {
                case 'list':
                    $archived = (int) $request->getQuery('archived', 0) === 1;
                    JsonResponse::success($this->service->list($archived));
                    return;

                case 'get':
                    $id = (int) $request->getQuery('id', 0);
                    JsonResponse::success($this->service->get($id));
                    return;

                case 'create':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = $this->service->create($payload, $currentUserId);
                    JsonResponse::success(['id' => $id], 'Посадка успешно создана');
                    return;

                case 'update':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int) ($payload['id'] ?? 0);
                    $this->service->update($id, $payload);
                    JsonResponse::success(null, 'Посадка успешно обновлена');
                    return;

                case 'delete':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int) ($payload['id'] ?? 0);
                    $this->service->delete($id);
                    JsonResponse::success(null, 'Посадка успешно удалена');
                    return;

                case 'archive':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int) ($payload['id'] ?? 0);
                    $archived = (int) ($payload['is_archived'] ?? 1) === 1;
                    $this->service->setArchived($id, $archived);
                    JsonResponse::success(
                        ['is_archived' => $archived ? 1 : 0],
                        $archived ? 'Посадка архивирована' : 'Посадка разархивирована'
                    );
                    return;

                case 'delete_file':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $fileId = (int) ($payload['file_id'] ?? 0);
                    $this->service->deleteFile($fileId);
                    JsonResponse::success(null, 'Файл успешно удален');
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


