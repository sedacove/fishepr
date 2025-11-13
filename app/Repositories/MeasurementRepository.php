<?php

namespace App\Repositories;

use PDO;

class MeasurementRepository extends Repository
{
    public function listByPool(int $poolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                m.*,
                u.login AS created_by_login,
                u.full_name AS created_by_name
             FROM measurements m
             LEFT JOIN users u ON m.created_by = u.id
             WHERE m.pool_id = ?
             ORDER BY m.measured_at DESC'
        );
        $stmt->execute([$poolId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findWithUser(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                m.*,
                u.login AS created_by_login,
                u.full_name AS created_by_name,
                p.name AS pool_name
             FROM measurements m
             LEFT JOIN users u ON m.created_by = u.id
             LEFT JOIN pools p ON m.pool_id = p.id
             WHERE m.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function insert(int $poolId, float $temperature, float $oxygen, string $measuredAt, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO measurements (pool_id, temperature, oxygen, measured_at, created_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$poolId, $temperature, $oxygen, $measuredAt, $userId]);
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
        $sql = 'UPDATE measurements SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM measurements WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function listForPoolSince(int $poolId, string $startDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT measured_at, temperature, oxygen, created_by
             FROM measurements
             WHERE pool_id = ?
               AND measured_at >= ?
             ORDER BY measured_at ASC'
        );
        $stmt->execute([$poolId, $startDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getLatestForPool(int $poolId, int $limit = 2): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT temperature, oxygen, measured_at
             FROM measurements
             WHERE pool_id = ?
             ORDER BY measured_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $poolId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function latestByColumn(string $column, int $limit = 20): array
    {
        $allowed = ['temperature', 'oxygen'];
        if (!in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported column requested');
        }

        $stmt = $this->pdo->query(
            "SELECT 
                m.id,
                m.pool_id,
                m.{$column} AS target_value,
                m.temperature,
                m.oxygen,
                m.measured_at,
                p.name AS pool_name
             FROM measurements m
             LEFT JOIN pools p ON m.pool_id = p.id
             WHERE m.{$column} IS NOT NULL
             ORDER BY m.measured_at DESC
             LIMIT {$limit}"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}


