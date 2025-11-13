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
        // Авторизация проверяется в api/work.php
        $this->service = new WorkService(\getDBConnection());
    }

    public function handle(Request $request): void
    {
        try {
            $pools = $this->service->getPools();
            JsonResponse::success($pools);
        } catch (\Throwable $e) {
            $status = $e->getCode() ?: 500;
            
            // В режиме разработки показываем детали ошибки
            $isDev = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;
            
            if ($status >= 500) {
                $message = $isDev 
                    ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                    : 'Внутренняя ошибка сервера';
            } else {
                $message = $e->getMessage();
            }
            
            error_log("Error in WorkController::handle(): " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
            
            JsonResponse::error($message, $status);
        }
    }
}


