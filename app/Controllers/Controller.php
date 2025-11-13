<?php

namespace App\Controllers;

use App\Support\View;

/**
 * Базовый класс контроллера
 * 
 * Предоставляет общие методы для всех контроллеров приложения:
 * - рендеринг представлений (views)
 * - возврат JSON ответов
 */
abstract class Controller
{
    /**
     * Рендерит представление (view) с переданными данными
     * 
     * @param string $template Имя шаблона (например, 'users.index')
     * @param array $data Данные для передачи в представление
     * @return string HTML содержимое представления
     */
    protected function view(string $template, array $data = []): string
    {
        return View::make($template, $data);
    }

    /**
     * Возвращает JSON ответ
     * 
     * @param array $payload Данные для JSON ответа
     * @param int $status HTTP статус код (по умолчанию 200)
     * @return void
     */
    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}

