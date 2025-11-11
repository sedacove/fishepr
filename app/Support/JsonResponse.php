<?php

namespace App\Support;

class JsonResponse
{
    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function success($data = null, string $message = null, int $status = 200): void
    {
        $payload = ['success' => true];
        if ($message !== null) {
            $payload['message'] = $message;
        }
        if ($data !== null) {
            $payload['data'] = $data;
        }
        self::send($payload, $status);
    }

    public static function error(string $message, int $status = 400, array $data = []): void
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];
        if (!empty($data)) {
            $payload['data'] = $data;
        }
        self::send($payload, $status);
    }
}
