<?php

namespace App\Services;

use App\Repositories\MeterRepository;
use DomainException;
use PDO;
use RuntimeException;

class MeterService
{
    private MeterRepository $meters;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->meters = new MeterRepository($pdo);
    }

    public function listPublic(): array
    {
        return $this->meters->listPublic();
    }

    public function listAdmin(): array
    {
        return $this->meters->listAdmin();
    }

    public function getMeter(int $id): array
    {
        $meter = $this->meters->find($id);
        if (!$meter) {
            throw new RuntimeException('Прибор учета не найден');
        }
        return $meter;
    }

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

    public function ensureExists(int $id): void
    {
        if (!$this->meters->exists($id)) {
            throw new RuntimeException('Прибор учета не найден');
        }
    }
}
