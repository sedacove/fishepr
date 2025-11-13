<?php

namespace App\Repositories;

use App\Models\Session\Session;
use PDO;

class SessionRepository extends Repository
{
    /**
     * @return Session[]
     */
    public function listByCompletion(bool $completed): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, 
                    p.name AS pool_name,
                    pl.name AS planting_name,
                    pl.fish_breed AS planting_fish_breed,
                    u.login AS created_by_login,
                    u.full_name AS created_by_name
             FROM sessions s
             LEFT JOIN pools p ON p.id = s.pool_id
             LEFT JOIN plantings pl ON pl.id = s.planting_id
             LEFT JOIN users u ON u.id = s.created_by
             WHERE s.is_completed = ?
             ORDER BY s.start_date DESC, s.created_at DESC'
        );
        $stmt->execute([$completed ? 1 : 0]);

        return array_map(
            fn ($row) => new Session($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    public function find(int $id): ?Session
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*,
                    p.name AS pool_name,
                    pl.name AS planting_name
             FROM sessions s
             LEFT JOIN pools p ON p.id = s.pool_id
             LEFT JOIN plantings pl ON pl.id = s.planting_id
             WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? new Session($row) : null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (
                name, pool_id, planting_id, start_date,
                start_mass, start_fish_count, previous_fcr, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['pool_id'],
            $data['planting_id'],
            $data['start_date'],
            $data['start_mass'],
            $data['start_fish_count'],
            $data['previous_fcr'],
            $data['created_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sessions SET
                name = ?,
                pool_id = ?,
                planting_id = ?,
                start_date = ?,
                start_mass = ?,
                start_fish_count = ?,
                previous_fcr = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['pool_id'],
            $data['planting_id'],
            $data['start_date'],
            $data['start_mass'],
            $data['start_fish_count'],
            $data['previous_fcr'],
            $id,
        ]);
    }

    public function updateCompletion(int $id, array $data): void
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
        $sql = 'UPDATE sessions SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function markCompleted(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sessions SET
                is_completed = 1,
                end_date = ?,
                end_mass = ?,
                feed_amount = ?,
                fcr = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['end_date'],
            $data['end_mass'],
            $data['feed_amount'],
            $data['fcr'],
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function hasActiveSessionInPool(int $poolId, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM sessions WHERE pool_id = ? AND is_completed = 0';
        $params = [$poolId];
        if ($excludeId) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int,array{id:int,name:string}>
     */
    public function getActivePools(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name
             FROM pools
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int,array{id:int,name:string,fish_breed:string}>
     */
    public function getActivePlantings(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, fish_breed
             FROM plantings
             WHERE is_archived = 0
             ORDER BY planting_date DESC, name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Найти активную сессию для указанного бассейна
     * @return array|null
     */
    public function findActiveByPool(int $poolId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*,
                    pl.name AS planting_name,
                    pl.fish_breed AS planting_fish_breed
             FROM sessions s
             LEFT JOIN plantings pl ON pl.id = s.planting_id
             WHERE s.pool_id = ? AND s.is_completed = 0
             ORDER BY s.start_date DESC, s.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$poolId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Найти сессию со всеми связанными данными для детальной страницы
     * @return array|null
     */
    public function findWithRelations(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*,
                    p.name AS pool_name,
                    p.id AS pool_id,
                    pl.name AS planting_name,
                    pl.fish_breed AS planting_fish_breed,
                    pl.hatch_date AS hatch_date,
                    pl.planting_date AS planting_planting_date,
                    pl.fish_count AS planting_quantity,
                    pl.biomass_weight AS planting_biomass_weight,
                    pl.supplier AS supplier,
                    pl.price AS planting_price,
                    pl.delivery_cost AS delivery_cost,
                    u.login AS created_by_login,
                    u.full_name AS created_by_name
             FROM sessions s
             LEFT JOIN pools p ON p.id = s.pool_id
             LEFT JOIN plantings pl ON pl.id = s.planting_id
             LEFT JOIN users u ON u.id = s.created_by
             WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}


