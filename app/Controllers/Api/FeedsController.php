<?php

namespace App\Controllers\Api;

use App\Services\FeedService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use RuntimeException;

class FeedsController
{
    private FeedService $service;

    public function __construct()
    {
        \requireAuth();
        \requireAdmin();
        $this->service = new FeedService(\getDBConnection());
    }

    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'list');
        $method = $request->getMethod();
        $userId = \getCurrentUserId();

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
                    $id = $this->service->create($payload, $userId);
                    JsonResponse::success(['id' => $id], 'Корм успешно создан');
                    return;
                case 'update':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    $this->service->update($id, $payload, $userId);
                    JsonResponse::success([], 'Корм успешно обновлён');
                    return;
                case 'delete':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    $this->service->delete($id);
                    JsonResponse::success([], 'Корм удалён');
                    return;
                case 'upload_norms':
                    $this->assertPost($method);
                    $feedId = (int)($request->getQuery('feed_id', 0) ?: ($_POST['feed_id'] ?? 0));
                    $result = $this->service->uploadNormImages($feedId, $_FILES['files'] ?? [], $userId);
                    JsonResponse::success(['images' => $result], 'Изображения загружены');
                    return;
                case 'delete_image':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $imageId = (int)($payload['id'] ?? 0);
                    $this->service->deleteImage($imageId);
                    JsonResponse::success([], 'Изображение удалено');
                    return;
                case 'options':
                    JsonResponse::success($this->service->options());
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
        } catch (DomainException | RuntimeException $e) {
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

