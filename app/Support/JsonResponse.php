<?php

namespace App\Support;

/**
 * Класс для формирования JSON ответов
 * 
 * Предоставляет удобные методы для возврата стандартизированных JSON ответов:
 * - успешные ответы (success)
 * - ответы с ошибками (error)
 * 
 * Все ответы имеют единый формат для удобства обработки на клиенте
 */
class JsonResponse
{
    /**
     * Отправляет JSON ответ
     * 
     * @param array $payload Данные для отправки
     * @param int $status HTTP статус код (по умолчанию 200)
     * @return void
     */
    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Отправляет успешный JSON ответ
     * 
     * Формат ответа:
     * {
     *   "success": true,
     *   "message": "Сообщение (опционально)",
     *   "data": {...} // опционально
     * }
     * 
     * @param mixed $data Данные для отправки (опционально)
     * @param string|null $message Сообщение (опционально)
     * @param int $status HTTP статус код (по умолчанию 200)
     * @return void
     */
    public static function success($data = null, string $message = null, int $status = 200): void
    {
        $payload = ['success' => true];
        if ($message !== null) {
            $payload['message'] = $message;
        }
        if ($data !== null) {
            $payload['data'] = $data;
        }
        self::maybeAttachDebug($payload);
        self::send($payload, $status);
    }

    /**
     * Отправляет JSON ответ с ошибкой
     * 
     * Формат ответа:
     * {
     *   "success": false,
     *   "message": "Сообщение об ошибке",
     *   "data": {...} // опционально
     * }
     * 
     * @param string $message Сообщение об ошибке
     * @param int $status HTTP статус код (по умолчанию 400)
     * @param array $data Дополнительные данные (опционально)
     * @return void
     */
    public static function error(string $message, int $status = 400, array $data = []): void
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];
        if (!empty($data)) {
            $payload['data'] = $data;
        }
        self::maybeAttachDebug($payload);
        self::send($payload, $status);
    }

    private static function maybeAttachDebug(array &$payload): void
    {
        if (!class_exists(\DebugProfiler::class)) {
            return;
        }
        if (!\DebugProfiler::isEnabled()) {
            return;
        }
        if (!function_exists('isAdmin') || !\isAdmin()) {
            return;
        }
        $payload['_debug'] = \DebugProfiler::getSummary();
    }
}
