<?php

namespace App\Controllers\Api;

use App\Services\MeterService;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use Exception;
use RuntimeException;

class MetersController
{
    private MeterService $service;
    private int $userId;
    private bool $isAdmin;

    public function __construct()
    {
        $pdo = \getDBConnection();
        $this->service = new MeterService($pdo);
        $this->userId = \getCurrentUserId();
        $this->isAdmin = \isAdmin();
    }

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

    private function handleList(): void
    {
        $meters = $this->service->listPublic();
        JsonResponse::success($meters);
    }

    private function handleListAdmin(): void
    {
        $meters = $this->service->listAdmin();
        JsonResponse::success($meters);
    }

    private function handleGet(Request $request): void
    {
        $id = (int)$request->getQuery('id', 0);
        if ($id <= 0) {
            throw new DomainException('ID прибора не указан');
        }
        $meter = $this->service->getMeter($id);
        JsonResponse::success($meter);
    }

    private function handleCreate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $id = $this->service->createMeter($payload, $this->userId);
        JsonResponse::success(['id' => $id], 'Прибор учета добавлен');
    }

    private function handleUpdate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $this->service->updateMeter($payload);
        JsonResponse::success([], 'Прибор учета обновлен');
    }

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
