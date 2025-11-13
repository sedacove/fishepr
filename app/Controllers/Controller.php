<?php

namespace App\Controllers;

use App\Support\View;

abstract class Controller
{
    protected function view(string $template, array $data = []): string
    {
        return View::make($template, $data);
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}

