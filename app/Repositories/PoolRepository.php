<?php

namespace App\Repositories;

use App\Models\Pool\Pool;
use PDO;

class PoolRepository extends Repository
{
    /**
     * @return Pool[]
     */
    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT p.*, u.login AS created_by_login, u.full_name AS created_by_name
             FROM pools p
             LEFT JOIN users u ON u.id = p.created_by
             ORDER BY p.sort_order ASC, p.created_at ASC'
        );

        return array_map(fn ($row) => new Pool($row), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function find(int $id): ?Pool
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.login AS created_by_login, u.full_name AS created_by_name
             FROM pools p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? new Pool($row) : null;
    }

    public function create(string $name, int $sortOrder, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pools (name, sort_order, created_by)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $sortOrder, $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = [];
        $params = [];
        foreach ($data as $column => $value) {
            $columns[] = "{$column} = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $sql = 'UPDATE pools SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM pools WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function maxSortOrder(): int
    {
        $stmt = $this->pdo->query('SELECT MAX(sort_order) AS max_order FROM pools');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return isset($row['max_order']) ? (int) $row['max_order'] : -1;
    }

    public function updateOrder(array $ids): void
    {
        $stmt = $this->pdo->prepare('UPDATE pools SET sort_order = ? WHERE id = ?');
        foreach ($ids as $index => $poolId) {
            $stmt->execute([$index, $poolId]);
        }
    }
}


