<?php

namespace App\Services;

use App\Repositories\MeterRepository;
use DomainException;
use PDO;
use RuntimeException;

/**
 * Сервис для работы с приборами учета
 * 
 * Содержит бизнес-логику для работы с приборами:
 * - валидация данных
 * - логирование действий
 * - проверка существования приборов
 */
class MeterService
{
    /**
     * @var MeterRepository Репозиторий для работы с приборами
     */
    private MeterRepository $meters;
    
    /**
     * @var PDO Подключение к базе данных
     */
    private PDO $pdo;

    /**
     * Конструктор сервиса
     * 
     * @param PDO $pdo Подключение к базе данных
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->meters = new MeterRepository($pdo);
    }

    /**
     * Получает список всех приборов для публичного использования
     * 
     * @return array Массив приборов (только основные поля)
     */
    public function listPublic(): array
    {
        return $this->meters->listPublic();
    }

    /**
     * Получает список всех приборов для администратора
     * 
     * @return array Массив приборов с полной информацией
     */
    public function listAdmin(): array
    {
        return $this->meters->listAdmin();
    }

    /**
     * Получает прибор по ID
     * 
     * @param int $id ID прибора
     * @return array Данные прибора
     * @throws RuntimeException Если прибор не найден
     */
    public function getMeter(int $id): array
    {
        $meter = $this->meters->find($id);
        if (!$meter) {
            throw new RuntimeException('Прибор учета не найден');
        }
        return $meter;
    }

    /**
     * Создает новый прибор учета
     * 
     * Валидация:
     * - название прибора обязательно
     * 
     * @param array $payload Данные прибора (name, description)
     * @param int $userId ID пользователя, создающего прибор
     * @return int ID созданного прибора
     * @throws DomainException Если название не указано
     */
    public function createMeter(array $payload, int $userId): int
    {
        $name = trim($payload['name'] ?? '');
        $description = isset($payload['description']) ? trim((string)$payload['description']) : null;
        if ($name === '') {
            throw new DomainException('Название прибора обязательно');
        }

        $meterId = $this->meters->insert($name, $description ?: null, $userId);

        if (\function_exists('logActivity')) {
            \logActivity('create', 'meter', $meterId, "Добавлен прибор учета: {$name}", [
                'name' => $name,
                'description' => $description,
            ]);
        }

        return $meterId;
    }

    /**
     * Обновляет данные прибора учета
     * 
     * Валидация:
     * - ID прибора должен быть указан
     * - название прибора обязательно
     * 
     * @param array $payload Данные прибора (id, name, description)
     * @return void
     * @throws DomainException Если данные некорректны
     * @throws RuntimeException Если прибор не найден
     */
    public function updateMeter(array $payload): void
    {
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new DomainException('ID прибора не указан');
        }

        $existing = $this->meters->find($id);
        if (!$existing) {
            throw new RuntimeException('Прибор учета не найден');
        }

        $name = trim($payload['name'] ?? $existing['name']);
        $description = isset($payload['description']) ? trim((string)$payload['description']) : ($existing['description'] ?? null);

        if ($name === '') {
            throw new DomainException('Название прибора обязательно');
        }

        $this->meters->update($id, $name, $description ?: null);

        if (\function_exists('logActivity')) {
            \logActivity('update', 'meter', $id, "Обновлен прибор учета: {$name}", [
                'name' => ['old' => $existing['name'], 'new' => $name],
                'description' => ['old' => $existing['description'], 'new' => $description],
            ]);
        }
    }

    /**
     * Удаляет прибор учета
     * 
     * @param int $id ID прибора для удаления
     * @return void
     * @throws RuntimeException Если прибор не найден
     */
    public function deleteMeter(int $id): void
    {
        $existing = $this->meters->find($id);
        if (!$existing) {
            throw new RuntimeException('Прибор учета не найден');
        }
        $this->meters->delete($id);

        if (\function_exists('logActivity')) {
            \logActivity('delete', 'meter', $id, "Удален прибор учета: {$existing['name']}");
        }
    }

    /**
     * Проверяет существование прибора учета
     * 
     * @param int $id ID прибора
     * @return void
     * @throws RuntimeException Если прибор не найден
     */
    public function ensureExists(int $id): void
    {
        if (!$this->meters->exists($id)) {
            throw new RuntimeException('Прибор учета не найден');
        }
    }
}
