<?php

namespace App\Repositories;

use PDO;

class MortalityRepository extends Repository
{
    public function listByPool(int $poolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                m.*,
                u.login AS created_by_login,
                u.full_name AS created_by_name
             FROM mortality m
             LEFT JOIN users u ON m.created_by = u.id
             WHERE m.pool_id = ?
             ORDER BY m.recorded_at DESC'
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
             FROM mortality m
             LEFT JOIN users u ON m.created_by = u.id
             LEFT JOIN pools p ON m.pool_id = p.id
             WHERE m.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function insert(int $poolId, float $weight, int $fishCount, string $recordedAt, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO mortality (pool_id, weight, fish_count, recorded_at, created_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$poolId, $weight, $fishCount, $recordedAt, $userId]);
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
        $sql = 'UPDATE mortality SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM mortality WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function getDailyTotalsForPoolSince(int $poolId, string $startDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                DATE(recorded_at) AS day,
                SUM(weight) AS total_weight,
                SUM(fish_count) AS total_count
             FROM mortality
             WHERE pool_id = ?
               AND recorded_at >= ?
             GROUP BY day
             ORDER BY day ASC'
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
             FROM mortality
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

    public function sumCountForHours(int $poolId, int $hours): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(fish_count), 0) AS total_count
             FROM mortality
             WHERE pool_id = ?
               AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)'
        );
        $stmt->bindValue(1, $poolId, PDO::PARAM_INT);
        $stmt->bindValue(2, $hours, PDO::PARAM_INT);
        $stmt->execute();
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public function getDailyTotalsInRange(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                DATE(recorded_at) AS record_date,
                COALESCE(SUM(fish_count), 0) AS total_count,
                COALESCE(SUM(weight), 0) AS total_weight
             FROM mortality
             WHERE recorded_at >= ?
               AND recorded_at <= ?
             GROUP BY DATE(recorded_at)
             ORDER BY record_date ASC'
        );
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDailyTotalsByPool(array $poolIds, string $startDate, string $endDate): array
    {
        if (empty($poolIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($poolIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT 
                pool_id,
                DATE(recorded_at) AS record_date,
                COALESCE(SUM(fish_count), 0) AS total_count,
                COALESCE(SUM(weight), 0) AS total_weight
             FROM mortality
             WHERE pool_id IN ($placeholders)
               AND recorded_at >= ?
               AND recorded_at <= ?
             GROUP BY pool_id, DATE(recorded_at)"
        );
        $params = array_merge($poolIds, [$startDate, $endDate]);
        $stmt->execute($params);
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $poolId = (int)$row['pool_id'];
            $date = $row['record_date'];
            if (!isset($result[$poolId])) {
                $result[$poolId] = [];
            }
            $result[$poolId][$date] = [
                'total_count' => (int)$row['total_count'],
                'total_weight' => (float)$row['total_weight'],
            ];
        }
        return $result;
    }
}


