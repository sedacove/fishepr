<?php

namespace App\Controllers\Api;

use App\Services\MortalityService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

/**
 * API контроллер для работы со смертностью
 * 
 * Обрабатывает HTTP запросы к API endpoints для смертности:
 * - list: получение списка записей смертности для бассейна
 * - get: получение одной записи смертности
 * - create: создание новой записи смертности
 * - update: обновление записи смертности
 * - delete: удаление записи смертности
 * - totals_last30: получение сводки смертности за последние 30 дней
 * - totals_last14_by_pool: получение сводки смертности за последние 14 дней по бассейнам
 * - get_pools: получение списка бассейнов
 * 
 * Авторизация проверяется в api/mortality.php
 */
class MortalityController
{
    /**
     * @var MortalityService Сервис для работы со смертностью
     */
    private MortalityService $service;

    /**
     * Конструктор контроллера
     * 
     * Инициализирует сервис для работы со смертностью.
     * Авторизация проверяется в api/mortality.php.
     */
    public function __construct()
    {
        // Авторизация проверяется в api/mortality.php
        $this->service = new MortalityService(\getDBConnection());
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
                        throw new ValidationException('id', 'ID записи не указан', 400);
                    }
                    JsonResponse::success($this->service->get($id));
                    return;

                case 'create':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = $this->service->create($payload, $userId, $isAdmin);
                    JsonResponse::success(['id' => $id], 'Запись о падеже успешно добавлена');
                    return;

                case 'update':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    if ($id <= 0) {
                        throw new ValidationException('id', 'ID записи не указан', 400);
                    }
                    $this->service->update($id, $payload, $userId, $isAdmin);
                    JsonResponse::success(null, 'Запись о падеже успешно обновлена');
                    return;

                case 'delete':
                    $this->assertPost($method);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    if ($id <= 0) {
                        throw new ValidationException('id', 'ID записи не указан', 400);
                    }
                    $this->service->delete($id, $isAdmin);
                    JsonResponse::success(null, 'Запись о падеже успешно удалена');
                    return;

                case 'totals_last30':
                    JsonResponse::success($this->service->totalsLast30());
                    return;

                case 'totals_last14_by_pool':
                    JsonResponse::success($this->service->totalsLast14ByPool());
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


