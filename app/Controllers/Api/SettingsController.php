<?php

namespace App\Controllers\Api;

use App\Services\SettingsService;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

class SettingsController
{
    private SettingsService $service;

    public function __construct()
    {
        // Авторизация проверяется в api/settings.php
        $this->service = new SettingsService(\getDBConnection());
    }

    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'list');
        $userId = \getCurrentUserId();

        try {
            switch ($action) {
                case 'list':
                    $settings = $this->service->list();
                    JsonResponse::success($settings);
                    return;

                case 'get':
                    $key = $request->getQuery('key', '');
                    if (!$key) {
                        throw new RuntimeException('Ключ настройки не указан', 400);
                    }
                    $value = $this->service->get($key);
                    JsonResponse::success(['value' => $value]);
                    return;

                case 'update':
                    $this->requirePost($request);
                    $payload = $request->getJsonBody();
                    $key = $payload['key'] ?? '';
                    $value = $payload['value'] ?? '';
                    
                    if (!$key) {
                        throw new RuntimeException('Ключ настройки не указан', 400);
                    }
                    
                    $this->service->update($key, $value, $userId);
                    JsonResponse::success(null, 'Настройка успешно обновлена');
                    return;

                default:
                    throw new RuntimeException('Неизвестное действие', 400);
            }
        } catch (RuntimeException $e) {
            $status = $e->getCode() ?: 400;
            JsonResponse::error($e->getMessage(), $status);
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
            
            error_log("Error in SettingsController::handle(): " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
            
            JsonResponse::error($message, $status);
        }
    }

    private function requirePost(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new RuntimeException('Метод не поддерживается', 405);
        }
    }
}

