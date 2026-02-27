<?php

namespace App\Support;

/**
 * Класс для работы с HTTP запросами
 * 
 * Предоставляет удобный интерфейс для получения данных из запроса:
 * - метод запроса (GET, POST, etc.)
 * - параметры запроса (query string)
 * - тело запроса в формате JSON
 */
class Request
{
    /**
     * @var string HTTP метод запроса
     */
    private string $method;
    
    /**
     * @var array Параметры запроса (query string)
     */
    private array $query;
    
    /**
     * @var array Параметры POST-запроса
     */
    private array $post;
    
    /**
     * @var array|null Распарсенное тело запроса в формате JSON
     */
    private ?array $jsonBody = null;
    
    /**
     * @var string|null Сырое тело запроса
     */
    private ?string $rawBody = null;

    /**
     * Конструктор (приватный, используется только через fromGlobals)
     * 
     * @param string $method HTTP метод
     * @param array $query Параметры запроса
     * @param array $post Параметры POST-запроса
     */
    private function __construct(string $method, array $query, array $post = [])
    {
        $this->method = strtoupper($method);
        $this->query = $query;
        $this->post = $post;
    }

    /**
     * Создает экземпляр Request из глобальных переменных PHP
     * 
     * @return self Экземпляр Request
     */
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $query = $_GET ?? [];
        $post = $_POST ?? [];
        return new self($method, $query, $post);
    }

    /**
     * Возвращает HTTP метод запроса
     * 
     * @return string HTTP метод (GET, POST, PUT, DELETE, etc.)
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Проверяет, соответствует ли метод запроса указанному
     * 
     * @param string $method Метод для проверки
     * @return bool true если метод совпадает, false в противном случае
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Получает значение параметра запроса по ключу
     * 
     * @param string $key Ключ параметра
     * @param mixed $default Значение по умолчанию, если параметр не найден
     * @return mixed Значение параметра или значение по умолчанию
     */
    public function getQuery(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Возвращает все параметры запроса
     * 
     * @return array Массив всех параметров запроса
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    /**
     * Получает значение параметра POST по ключу
     * 
     * @param string $key Ключ параметра
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function getPost(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Возвращает все параметры POST-запроса
     * 
     * @return array
     */
    public function allPost(): array
    {
        return $this->post;
    }

    /**
     * Получает тело запроса в формате JSON
     * 
     * @return array Распарсенное тело запроса
     * @throws \RuntimeException Если JSON некорректен
     */
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

    /**
     * Получает значение из JSON тела запроса по ключу
     * 
     * @param string $key Ключ для получения значения
     * @param mixed $default Значение по умолчанию, если ключ не найден
     * @return mixed Значение из JSON тела или значение по умолчанию
     */
    public function getJsonValue(string $key, $default = null)
    {
        $body = $this->getJsonBody();
        return $body[$key] ?? $default;
    }
}
