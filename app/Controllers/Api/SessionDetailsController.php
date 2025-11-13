<?php

namespace App\Controllers\Api;

use App\Services\SessionDetailsService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

/**
 * API контроллер для работы с деталями сессии
 * 
 * Обрабатывает HTTP запросы к API endpoints для деталей сессии:
 * - получение полной информации о сессии, включая связанные данные
 *   (посадка, бассейн, измерения, навески, отборы, смертность)
 * 
 * Авторизация проверяется в api/session_details.php
 */
class SessionDetailsController
{
    /**
     * @var SessionDetailsService Сервис для работы с деталями сессии
     */
    private SessionDetailsService $service;

    /**
     * Конструктор контроллера
     * 
     * Инициализирует сервис для работы с деталями сессии.
     * Авторизация проверяется в api/session_details.php.
     */
    public function __construct()
    {
        // Авторизация проверяется в api/session_details.php
        $this->service = new SessionDetailsService(\getDBConnection());
    }

    /**
     * Обрабатывает входящий запрос
     * 
     * Возвращает полную информацию о сессии с связанными данными.
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    public function handle(Request $request): void
    {
        try {
            $sessionId = (int)$request->getQuery('id', 0);
            if ($sessionId <= 0) {
                throw new ValidationException('id', 'ID сессии не указан', 400);
            }
            $data = $this->service->getDetails($sessionId);
            JsonResponse::success($data);
        } catch (ValidationException $e) {
            JsonResponse::send([
                'success' => false,
                'message' => $e->getMessage(),
                'field' => $e->getField(),
            ], (int)($e->getCode() ?: 422));
        } catch (RuntimeException $e) {
            $status = (int)($e->getCode() ?: 404);
            JsonResponse::error($e->getMessage(), $status);
        } catch (\Throwable $e) {
            $status = (int)($e->getCode() ?: 500);
            
            // В режиме разработки показываем детали ошибки
            $isDev = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;
            
            if ($status >= 500) {
                $message = $isDev 
                    ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                    : 'Внутренняя ошибка сервера';
            } else {
                $message = $e->getMessage();
            }
            
            error_log("Error in SessionDetailsController::handle(): " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
            
            JsonResponse::error($message, $status);
        }
    }
}


