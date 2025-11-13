<?php

namespace App\Support\Exceptions;

use DomainException;

/**
 * Исключение валидации данных
 * 
 * Выбрасывается, когда входящие данные не проходят валидацию.
 * Содержит имя поля, которое вызвало ошибку, чтобы клиент мог
 * выделить его в интерфейсе.
 */
class ValidationException extends DomainException
{
    /**
     * @var string Имя поля, которое вызвало ошибку валидации
     */
    private string $field;

    /**
     * Конструктор исключения валидации
     * 
     * @param string $field Имя поля, которое вызвало ошибку
     * @param string $message Сообщение об ошибке
     * @param int $code HTTP код статуса (по умолчанию 422)
     * @param \Throwable|null $previous Предыдущее исключение
     */
    public function __construct(string $field, string $message, int $code = 422, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->field = $field;
    }

    /**
     * Возвращает имя поля, которое вызвало ошибку
     * 
     * @return string Имя поля
     */
    public function getField(): string
    {
        return $this->field;
    }
}


