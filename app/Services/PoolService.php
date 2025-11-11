<?php

namespace App\Services;

use App\Models\Pool\Pool;
use App\Repositories\PoolRepository;
use App\Support\Exceptions\ValidationException;
use PDO;
use RuntimeException;

require_once __DIR__ . '/../../includes/activity_log.php';

class PoolService
{
    private PoolRepository $pools;

    public function __construct(PDO $pdo)
    {
        $this->pools = new PoolRepository($pdo);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list(): array
    {
        $items = $this->pools->all();
        return array_map(function (Pool $pool) {
            $data = $pool->toArray();
            $data['is_active'] = (bool) ($data['is_active'] ?? true);
            $data['created_at'] = $this->formatDateTime($data['created_at'] ?? '');
            $data['updated_at'] = $this->formatDateTime($data['updated_at'] ?? '');
            return $data;
        }, $items);
    }

    public function get(int $id): array
    {
        $pool = $this->pools->find($id);
        if (!$pool) {
            throw new RuntimeException('Бассейн не найден', 404);
        }
        return $pool->toArray();
    }

    public function create(array $payload, int $userId): int
    {
        $name = $this->validateName($payload['name'] ?? null);
        $sortOrder = $this->pools->maxSortOrder() + 1;
        $poolId = $this->pools->create($name, $sortOrder, $userId);

        \logActivity('create', 'pool', $poolId, "Создан бассейн: {$name}", [
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);

        return $poolId;
    }

    public function update(int $id, array $payload): void
    {
        $pool = $this->pools->find($id);
        if (!$pool) {
            throw new RuntimeException('Бассейн не найден', 404);
        }

        $updates = [];
        $changes = [];

        if (array_key_exists('name', $payload)) {
            $name = $this->validateName($payload['name']);
            if ($name !== $pool->name) {
                $updates['name'] = $name;
                $changes['name'] = ['old' => $pool->name, 'new' => $name];
            }
        }

        if (array_key_exists('is_active', $payload)) {
            $isActive = (bool) $payload['is_active'];
            if ((bool) $pool->is_active !== $isActive) {
                $updates['is_active'] = $isActive ? 1 : 0;
                $changes['is_active'] = ['old' => (bool) $pool->is_active, 'new' => $isActive];
            }
        }

        if (empty($updates)) {
            throw new RuntimeException('Нет изменений для сохранения', 400);
        }

        $this->pools->update($id, $updates);

        \logActivity('update', 'pool', $id, "Обновлен бассейн: {$pool->name}", $changes);
    }

    public function delete(int $id): void
    {
        $pool = $this->pools->find($id);
        if (!$pool) {
            throw new RuntimeException('Бассейн не найден', 404);
        }

        $this->pools->delete($id);

        \logActivity('delete', 'pool', $id, "Удален бассейн: {$pool->name}", [
            'name' => $pool->name,
            'sort_order' => $pool->sort_order,
            'is_active' => (bool) $pool->is_active,
        ]);
    }

    public function reorder(array $ids): void
    {
        if (empty($ids)) {
            throw new ValidationException('order', 'Неверный формат данных');
        }

        $cleanIds = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                throw new ValidationException('order', 'Список идентификаторов содержит неверные значения');
            }
            $cleanIds[] = $id;
        }

        $this->pools->updateOrder($cleanIds);

        \logActivity('update', 'pool', null, 'Изменен порядок сортировки бассейнов', [
            'new_order' => $cleanIds,
        ]);
    }

    private function validateName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            throw new ValidationException('name', 'Название бассейна обязательно для заполнения');
        }
        if (mb_strlen($name) > 255) {
            throw new ValidationException('name', 'Название бассейна слишком длинное (максимум 255 символов)');
        }
        return $name;
    }

    private function formatDateTime(string $value): string
    {
        if (!$value) {
            return '';
        }
        return date('d.m.Y H:i', strtotime($value));
    }
}


