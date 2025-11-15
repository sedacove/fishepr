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
                    f.name AS feed_name,
                    u.login AS created_by_login,
                    u.full_name AS created_by_name
             FROM sessions s
             LEFT JOIN pools p ON p.id = s.pool_id
             LEFT JOIN plantings pl ON pl.id = s.planting_id
             LEFT JOIN feeds f ON f.id = s.feed_id
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

    /**
     * Находит сессию по ID
     * 
     * @param int $id ID сессии
     * @return Session|null Модель сессии или null, если не найдена
     */
    public function find(int $id): ?Session
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*,
                    p.name AS pool_name,
                    pl.name AS planting_name,
                    f.name AS feed_name
             FROM sessions s
             LEFT JOIN pools p ON p.id = s.pool_id
             LEFT JOIN plantings pl ON pl.id = s.planting_id
             LEFT JOIN feeds f ON f.id = s.feed_id
             WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? new Session($row) : null;
    }

    /**
     * Создает новую сессию
     * 
     * @param array $data Данные сессии (name, pool_id, planting_id, start_date, start_mass, start_fish_count, previous_fcr, created_by)
     * @return int ID созданной сессии
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (
                name, pool_id, planting_id, start_date,
                start_mass, start_fish_count, previous_fcr,
                daily_feedings, feed_id, feeding_strategy, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['pool_id'],
            $data['planting_id'],
            $data['start_date'],
            $data['start_mass'],
            $data['start_fish_count'],
            $data['previous_fcr'],
            $data['daily_feedings'],
            $data['feed_id'],
            $data['feeding_strategy'],
            $data['created_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Обновляет данные сессии
     * 
     * @param int $id ID сессии
     * @param array $data Данные для обновления (name, pool_id, planting_id, start_date, start_mass, start_fish_count, previous_fcr)
     * @return void
     */
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
                previous_fcr = ?,
                daily_feedings = ?,
                feed_id = ?,
                feeding_strategy = ?
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
            $data['daily_feedings'],
            $data['feed_id'],
            $data['feeding_strategy'],
            $id,
        ]);
    }

    /**
     * Обновляет данные завершения сессии
     * 
     * Динамически формирует SQL запрос на основе переданных данных.
     * Используется для частичного обновления данных завершения сессии.
     * 
     * @param int $id ID сессии
     * @param array $data Данные для обновления (end_date, end_mass, feed_amount, fcr, is_completed)
     * @return void
     */
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

    /**
     * Отмечает сессию как завершенную
     * 
     * Устанавливает is_completed = 1 и сохраняет данные завершения сессии.
     * 
     * @param int $id ID сессии
     * @param array $data Данные завершения (end_date, end_mass, feed_amount, fcr)
     * @return void
     */
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

    /**
     * Удаляет сессию
     * 
     * @param int $id ID сессии для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Проверяет, есть ли активная сессия в указанном бассейне
     * 
     * @param int $poolId ID бассейна
     * @param int|null $excludeId ID сессии для исключения из проверки (например, при обновлении текущей сессии)
     * @return bool true, если есть активная сессия, false в противном случае
     */
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
     * Получает список активных бассейнов
     * 
     * Используется для выпадающих списков при создании/редактировании сессий.
     * 
     * @return array<int,array{id:int,name:string}> Массив активных бассейнов (id, name)
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
     * Получает список активных посадок
     * 
     * Используется для выпадающих списков при создании/редактировании сессий.
     * 
     * @return array<int,array{id:int,name:string,fish_breed:string}> Массив активных посадок (id, name, fish_breed)
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
     * Находит активную сессию для указанного бассейна
     * 
     * @param int $poolId ID бассейна
     * @return array|null Данные активной сессии или null, если активной сессии нет
     */
    public function findActiveByPool(int $poolId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*,
                    pl.name AS planting_name,
                    pl.fish_breed AS planting_fish_breed,
                    f.name AS feed_name
             FROM sessions s
             LEFT JOIN plantings pl ON pl.id = s.planting_id
             LEFT JOIN feeds f ON f.id = s.feed_id
             WHERE s.pool_id = ? AND s.is_completed = 0
             ORDER BY s.start_date DESC, s.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$poolId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Находит сессию со всеми связанными данными для детальной страницы
     * 
     * Возвращает полную информацию о сессии, включая данные о бассейне, посадке и создателе.
     * 
     * @param int $id ID сессии
     * @return array|null Данные сессии со связанными данными или null, если сессия не найдена
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
                    f.name AS feed_name,
                    u.login AS created_by_login,
                    u.full_name AS created_by_name
             FROM sessions s
             LEFT JOIN pools p ON p.id = s.pool_id
             LEFT JOIN plantings pl ON pl.id = s.planting_id
             LEFT JOIN feeds f ON f.id = s.feed_id
             LEFT JOIN users u ON u.id = s.created_by
             WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}


