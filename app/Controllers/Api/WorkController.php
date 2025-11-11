<?php

namespace App\Controllers\Api;

use App\Services\WorkService;
use App\Support\JsonResponse;
use App\Support\Request;

class WorkController
{
    private WorkService $service;

    public function __construct()
    {
        \requireAuth();
        $this->service = new WorkService(\getDBConnection());
    }

    public function handle(Request $request): void
    {
        try {
            $pools = $this->service->getPools();
            JsonResponse::success($pools);
        } catch (\Throwable $e) {
            $status = $e->getCode() ?: 500;
            $message = $status >= 500 ? 'Внутренняя ошибка сервера' : $e->getMessage();
            JsonResponse::error($message, $status);
        }
    }
}


