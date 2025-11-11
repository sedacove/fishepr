<?php

namespace App\Controllers\Api;

use App\Services\NewsService;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use Exception;
use RuntimeException;

class NewsController
{
    private NewsService $service;
    private int $userId;
    private bool $isAdmin;

    public function __construct()
    {
        $pdo = \getDBConnection();
        $this->service = new NewsService($pdo);
        $this->userId = \getCurrentUserId();
        $this->isAdmin = \isAdmin();
    }

    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'list');

        try {
            switch ($action) {
                case 'list':
                    $this->ensureAdmin();
                    $news = $this->service->listNews();
                    JsonResponse::success($news);
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
                case 'latest':
                    $this->handleLatest();
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

    private function handleGet(Request $request): void
    {
        $id = (int)$request->getQuery('id', 0);
        if ($id <= 0) {
            throw new DomainException('ID новости не указан');
        }
        $news = $this->service->getNews($id);
        JsonResponse::success($news);
    }

    private function handleCreate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $newsId = $this->service->createNews($payload, $this->userId);
        JsonResponse::success(['id' => $newsId], 'Новость добавлена');
    }

    private function handleUpdate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $this->service->updateNews($payload);
        JsonResponse::success([], 'Новость обновлена');
    }

    private function handleDelete(Request $request): void
    {
        $payload = $request->getJsonBody();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new DomainException('ID новости не указан');
        }
        $this->service->deleteNews($id);
        JsonResponse::success([], 'Новость удалена');
    }

    private function handleLatest(): void
    {
        $news = $this->service->getLatestNews();
        JsonResponse::success($news ?: null);
    }

    private function ensureAdmin(): void
    {
        if (!$this->isAdmin) {
            throw new DomainException('Доступ запрещен');
        }
    }

    private function requirePost(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new DomainException('Метод не поддерживается');
        }
    }
}
