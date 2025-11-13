<?php

namespace App\Support\Exceptions;

use DomainException;

/**
 * ValidationException is thrown when incoming data fails domain validation.
 * Carries the name of the field that triggered the error so the client can
 * highlight it inline.
 */
class ValidationException extends DomainException
{
    private string $field;

    public function __construct(string $field, string $message, int $code = 422, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->field = $field;
    }

    public function getField(): string
    {
        return $this->field;
    }
}


