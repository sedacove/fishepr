<?php

namespace App\Controllers\Api;

use App\Services\SessionDetailsService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

class SessionDetailsController
{
    private SessionDetailsService $service;

    public function __construct()
    {
        // Авторизация проверяется в api/session_details.php
        $this->service = new SessionDetailsService(\getDBConnection());
    }

    public function handle(Request $request): void
    {
        try {
            $sessionId = (int)$request->getQuery('id', 0);
            $data = $this->service->getDetails($sessionId);
            JsonResponse::success($data);
        } catch (ValidationException $e) {
            JsonResponse::send([
                'success' => false,
                'message' => $e->getMessage(),
                'field' => $e->getField(),
            ], $e->getCode() ?: 422);
        } catch (RuntimeException $e) {
            JsonResponse::error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Throwable $e) {
            JsonResponse::error('Внутренняя ошибка сервера', 500);
        }
    }
}


