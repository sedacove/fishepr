<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с отборами
 * 
 * Выполняет SQL запросы к таблице harvests:
 * - получение списка отборов по бассейну
 * - поиск отбора по ID
 * - создание, обновление, удаление отборов
 * - получение сводной информации по отборам
 */
class HarvestRepository extends Repository
{
    /**
     * Получает список всех отборов для указанного бассейна
     * 
     * Включает информацию о пользователе, создавшем отбор, и контрагенте.
     * Отсортированы по дате (от новых к старым).
     * 
     * @param int $poolId ID бассейна
     * @return array Массив отборов с информацией о пользователе и контрагенте
     */
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

    /**
     * Находит отбор по ID
     * 
     * Включает информацию о пользователе, контрагенте и бассейне.
     * 
     * @param int $id ID отбора
     * @return array|null Данные отбора или null, если не найден
     */
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

    /**
     * Создает новый отбор
     * 
     * @param array $data Данные отбора (pool_id, weight, fish_count, counterparty_id, recorded_at, created_by)
     * @return int ID созданного отбора
     */
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

    /**
     * Обновляет данные отбора
     * 
     * Динамически формирует SQL запрос на основе переданных данных.
     * Обновляет только те поля, которые переданы в массиве $data.
     * 
     * @param int $id ID отбора
     * @param array $data Ассоциативный массив полей для обновления (column => value)
     * @return void
     */
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

    /**
     * Удаляет отбор
     * 
     * @param int $id ID отбора для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM harvests WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Получает список отборов для бассейна с определенной даты
     * 
     * Используется для построения истории отборов с начала сессии.
     * 
     * @param int $poolId ID бассейна
     * @param string $startDate Дата начала (в формате БД)
     * @return array Массив отборов с информацией о контрагенте, отсортированных по дате (от старых к новым)
     */
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

    /**
     * Получает сумму отборов для бассейна с определенной даты
     * 
     * @param int $poolId ID бассейна
     * @param string $startDate Дата начала (в формате БД)
     * @return array Массив с ключами total_weight и total_count
     */
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


