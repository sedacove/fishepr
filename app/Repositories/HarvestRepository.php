<?php

namespace App\Repositories;

use PDO;

class HarvestRepository extends Repository
{
    public function listByPool(int $poolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT h.*, ' .
            'u.login AS created_by_login, u.full_name AS created_by_name, ' .
            'c.name AS counterparty_name, c.color AS counterparty_color ' .
            'FROM harvests h ' .
            'LEFT JOIN users u ON h.created_by = u.id ' .
            'LEFT JOIN counterparties c ON h.counterparty_id = c.id ' .
            'WHERE h.pool_id = ? ' .
            'ORDER BY h.recorded_at DESC'
        );
        $stmt->execute([$poolId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT h.*, ' .
            'u.login AS created_by_login, u.full_name AS created_by_name, ' .
            'c.name AS counterparty_name, c.color AS counterparty_color, ' .
            'p.name AS pool_name ' .
            'FROM harvests h ' .
            'LEFT JOIN users u ON h.created_by = u.id ' .
            'LEFT JOIN counterparties c ON h.counterparty_id = c.id ' .
            'LEFT JOIN pools p ON h.pool_id = p.id ' .
            'WHERE h.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO harvests (pool_id, weight, fish_count, counterparty_id, recorded_at, created_by) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['pool_id'],
            $data['weight'],
            $data['fish_count'],
            $data['counterparty_id'],
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
        $sql = 'UPDATE harvests SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM harvests WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function listForPoolSince(int $poolId, string $startDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                h.recorded_at,
                h.weight,
                h.fish_count,
                h.counterparty_id,
                c.name AS counterparty_name,
                c.color AS counterparty_color
             FROM harvests h
             LEFT JOIN counterparties c ON h.counterparty_id = c.id
             WHERE h.pool_id = ?
               AND h.recorded_at >= ?
             ORDER BY h.recorded_at ASC'
        );
        $stmt->execute([$poolId, $startDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sumForPoolSince(int $poolId, string $startDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                COALESCE(SUM(weight), 0) AS total_weight,
                COALESCE(SUM(fish_count), 0) AS total_count
             FROM harvests
             WHERE pool_id = ?
               AND recorded_at >= ?'
        );
        $stmt->execute([$poolId, $startDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_weight' => 0, 'total_count' => 0];
        return [
            'total_weight' => (float)$row['total_weight'],
            'total_count' => (int)$row['total_count'],
        ];
    }
}


