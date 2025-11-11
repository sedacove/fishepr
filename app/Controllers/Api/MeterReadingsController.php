<?php

namespace App\Controllers\Api;

use App\Services\MeterReadingService;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use Exception;
use RuntimeException;

class MeterReadingsController
{
    private MeterReadingService $service;
    private int $userId;
    private bool $isAdmin;

    public function __construct()
    {
        $pdo = \getDBConnection();
        $this->service = new MeterReadingService($pdo);
        $this->userId = \getCurrentUserId();
        $this->isAdmin = \isAdmin();
    }

    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'list');

        try {
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
                case 'update':
                    $this->requirePost($request);
                    $this->handleUpdate($request);
                    break;
                case 'delete':
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

    private function handleList(Request $request): void
    {
        $meterId = (int)$request->getQuery('meter_id', 0);
        if ($meterId <= 0) {
            throw new DomainException('ID прибора не указан');
        }
        $readings = $this->service->listReadings($meterId, $this->userId, $this->isAdmin);
        JsonResponse::success($readings);
    }

    private function handleGet(Request $request): void
    {
        $id = (int)$request->getQuery('id', 0);
        if ($id <= 0) {
            throw new DomainException('ID показания не указан');
        }
        $reading = $this->service->getReading($id, $this->userId, $this->isAdmin);
        JsonResponse::success($reading);
    }

    private function handleCreate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $readingId = $this->service->createReading($payload, $this->userId, $this->isAdmin);
        JsonResponse::success(['id' => $readingId], 'Показание добавлено');
    }

    private function handleUpdate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $this->service->updateReading($payload, $this->userId, $this->isAdmin);
        JsonResponse::success([], 'Показание обновлено');
    }

    private function handleDelete(Request $request): void
    {
        $payload = $request->getJsonBody();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new DomainException('ID показания не указан');
        }
        $this->service->deleteReading($id, $this->userId, $this->isAdmin);
        JsonResponse::success([], 'Показание удалено');
    }

    private function requirePost(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new DomainException('Метод не поддерживается');
        }
    }
}
