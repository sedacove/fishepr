<?php

namespace App\Controllers\Api;

use App\Services\WeighingService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use RuntimeException;

class WeighingsController
{
    private WeighingService $service;

    public function __construct()
    {
        // Авторизация проверяется в api/weighings.php
        $this->service = new WeighingService(\getDBConnection());
    }

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
                case 'list':
                    $poolId = (int)$request->getQuery('pool_id', 0);
                    JsonResponse::success($this->service->listByPool($poolId, $userId, $isAdmin));
                    return;
                case 'get':
                    $id = (int)$request->getQuery('id', 0);
                    if ($id <= 0) {
                        throw new ValidationException('id', 'ID записи не указан', 400);
                    }
                    JsonResponse::success($this->service->get($id));
                    return;
                case 'create':
                    $this->requirePost($request);
                    $payload = $request->getJsonBody();
                    $id = $this->service->create($payload, $userId, $isAdmin);
                    JsonResponse::success(['id' => $id], 'Навеска успешно добавлена');
                    return;
                case 'update':
                    $this->requirePost($request);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    if ($id <= 0) {
                        throw new ValidationException('id', 'ID записи не указан', 400);
                    }
                    $this->service->update($id, $payload, $userId, $isAdmin);
                    JsonResponse::success(null, 'Навеска успешно обновлена');
                    return;
                case 'delete':
                    $this->requirePost($request);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    if ($id <= 0) {
                        throw new ValidationException('id', 'ID записи не указан', 400);
                    }
                    $this->service->delete($id, $isAdmin);
                    JsonResponse::success(null, 'Навеска успешно удалена');
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
            JsonResponse::error('Внутренняя ошибка сервера', 500);
        }
    }

    private function requirePost(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new DomainException('Метод не поддерживается');
        }
    }
}


