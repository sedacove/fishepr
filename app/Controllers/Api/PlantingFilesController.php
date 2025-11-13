<?php

namespace App\Controllers\Api;

use App\Services\PlantingService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

class PlantingFilesController
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
        if ($request->getMethod() !== 'POST') {
            JsonResponse::error('Метод не поддерживается', 405);
            return;
        }

        try {
            $plantingId = (int) $request->getPost('planting_id', 0);
            if ($plantingId <= 0) {
                throw new ValidationException('planting_id', 'ID посадки не указан');
            }

            $files = $request->getFile('files');
            $result = $this->service->uploadFiles($plantingId, $files ?? [], \getCurrentUserId());

            JsonResponse::success($result, 'Файлы успешно загружены');
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
}


