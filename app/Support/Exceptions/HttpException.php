<?php

namespace App\Support\Exceptions;

use Exception;

/**
 * Исключение для HTTP ошибок
 * 
 * Используется для обработки HTTP ошибок (404, 500, etc.)
 * Содержит HTTP статус код для правильной обработки ошибки
 */
class HttpException extends Exception
{
    /**
     * Конструктор HTTP исключения
     * 
     * @param int $statusCode HTTP статус код (например, 404, 500)
     * @param string $message Сообщение об ошибке
     * @param Exception|null $previous Предыдущее исключение
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Возвращает HTTP статус код
     * 
     * @return int HTTP статус код
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

