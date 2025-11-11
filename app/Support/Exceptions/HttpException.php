<?php

namespace App\Support\Exceptions;

use Exception;

class HttpException extends Exception
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

