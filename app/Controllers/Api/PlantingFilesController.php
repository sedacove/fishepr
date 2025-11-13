<?php

namespace App\Controllers\Api;

use App\Services\PlantingService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

/**
 * API контроллер для загрузки файлов посадок
 * 
 * Обрабатывает HTTP запросы для загрузки файлов к посадкам:
 * - загрузка файлов для посадки (multipart/form-data)
 * 
 * Доступен только администраторам.
 */
class PlantingFilesController
{
    /**
     * @var PlantingService Сервис для работы с посадками
     */
    private PlantingService $service;

    /**
     * Конструктор контроллера
     * 
     * Проверяет авторизацию и права администратора.
     * Инициализирует сервис для работы с посадками.
     */
    public function __construct()
    {
        \requireAuth();
        \requireAdmin();
        $this->service = new PlantingService(\getDBConnection());
    }

    /**
     * Обрабатывает запрос на загрузку файлов для посадки
     * 
     * Принимает multipart/form-data с полями:
     * - planting_id: ID посадки
     * - files: массив файлов
     * 
     * @param Request $request Объект запроса
     * @return void
     */
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


