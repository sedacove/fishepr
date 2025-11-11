<?php

namespace App\Support;

class Request
{
    private string $method;
    private array $query;
    private ?array $jsonBody = null;
    private ?string $rawBody = null;

    private function __construct(string $method, array $query)
    {
        $this->method = strtoupper($method);
        $this->query = $query;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $query = $_GET ?? [];
        return new self($method, $query);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function getQuery(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function getJsonBody(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input');
        }

        if ($this->rawBody === '' || $this->rawBody === false) {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $decoded = json_decode($this->rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \RuntimeException('Некорректный JSON в теле запроса', 400);
        }

        $this->jsonBody = $decoded;
        return $this->jsonBody;
    }

    public function getJsonValue(string $key, $default = null)
    {
        $body = $this->getJsonBody();
        return $body[$key] ?? $default;
    }
}
