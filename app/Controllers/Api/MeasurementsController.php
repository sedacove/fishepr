<?php

namespace App\Controllers\Api;

use App\Services\MeasurementService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

/**
 * API контроллер для работы с измерениями
 * 
 * Обрабатывает HTTP запросы к API endpoints для измерений:
 * - list: получение списка измерений для бассейна
 * - get: получение одного измерения
 * - create: создание нового измерения
 * - update: обновление измерения
 * - delete: удаление измерения
 * - latest_temperatures: получение последних измерений температуры
 * - latest_oxygen: получение последних измерений кислорода
 * - get_pools: получение списка бассейнов
 * 
 * Авторизация проверяется в api/measurements.php
 */
class MeasurementsController
{
    /**
     * @var MeasurementService Сервис для работы с измерениями
     */
    private MeasurementService $service;

    /**
     * Конструктор контроллера
     * 
     * Инициализирует сервис для работы с измерениями.
     * Авторизация проверяется в api/measurements.php.
     */
    public function __construct()
    {
        // Авторизация проверяется в api/measurements.php
        $this->service = new MeasurementService(\getDBConnection());
    }

    /**
     * Обрабатывает входящий запрос и направляет его к соответствующему обработчику
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'list');
        $method = $request->getMethod();
        $isAdmin = \isAdmin();
        $userId = \getCurrentUserId();

        try {
            switch ($action) {
                case 'list':
                    $poolId = (int)$request->getQuery('pool_id', 0);
                    $data = $this->service->listByPool($poolId, $userId, $isAdmin);
                    JsonResponse::success($data);
                    return;

                case 'get':
                    $id = (int)$request->getQuery('id', 0);
                    if ($id <= 0) {
                        throw new ValidationException('id', 'ID замера не указан', 400);
                    }
                    $measurement = $this->service->get($id);
                    JsonResponse::success($measurement);
                    return;

                case 'create':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = $this->service->create($payload, $userId, $isAdmin);
                    JsonResponse::success(['id' => $id], 'Замер успешно добавлен');
                    return;

                case 'update':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    if ($id <= 0) {
                        throw new ValidationException('id', 'ID замера не указан', 400);
                    }
                    $this->service->update($id, $payload, $userId, $isAdmin);
                    JsonResponse::success(null, 'Замер успешно обновлён');
                    return;

                case 'delete':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    if ($id <= 0) {
                        throw new ValidationException('id', 'ID замера не указан', 400);
                    }
                    $this->service->delete($id, $isAdmin);
                    JsonResponse::success(null, 'Замер успешно удалён');
                    return;

                case 'latest_temperatures':
                    JsonResponse::success($this->service->latest('temperature'));
                    return;

                case 'latest_oxygen':
                    JsonResponse::success($this->service->latest('oxygen'));
                    return;

                case 'get_pools':
                    JsonResponse::success($this->service->getPools());
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

    /**
     * Проверяет, что запрос использует метод POST
     * 
     * @param string $method HTTP метод запроса
     * @return void
     * @throws RuntimeException Если метод не POST
     */
    private function assertPost(string $method): void
    {
        if ($method !== 'POST') {
            throw new RuntimeException('Метод не поддерживается', 405);
        }
    }
}


