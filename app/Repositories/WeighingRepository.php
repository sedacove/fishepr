<?php

namespace App\Repositories;

use PDO;

class WeighingRepository extends Repository
{
    public function listByPool(int $poolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT w.*, u.login AS created_by_login, u.full_name AS created_by_name ' .
            'FROM weighings w ' .
            'LEFT JOIN users u ON w.created_by = u.id ' .
            'WHERE w.pool_id = ? ' .
            'ORDER BY w.recorded_at DESC'
        );
        $stmt->execute([$poolId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT w.*, u.login AS created_by_login, u.full_name AS created_by_name, p.name AS pool_name ' .
            'FROM weighings w ' .
            'LEFT JOIN users u ON w.created_by = u.id ' .
            'LEFT JOIN pools p ON w.pool_id = p.id ' .
            'WHERE w.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO weighings (pool_id, weight, fish_count, recorded_at, created_by) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['pool_id'],
            $data['weight'],
            $data['fish_count'],
            $data['recorded_at'],
            $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $columns = [];
        $params = [];
        foreach ($data as $column => $value) {
            $columns[] = "{$column} = ?";
            $params[] = $value;
        }
        if (empty($columns)) {
            return;
        }
        $params[] = $id;
        $sql = 'UPDATE weighings SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM weighings WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function listForPoolSince(int $poolId, string $startDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT recorded_at, weight, fish_count
             FROM weighings
             WHERE pool_id = ?
               AND recorded_at >= ?
             ORDER BY recorded_at ASC'
        );
        $stmt->execute([$poolId, $startDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}


