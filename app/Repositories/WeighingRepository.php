<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с навесками
 * 
 * Выполняет SQL запросы к таблице weighings:
 * - получение списка навесок по бассейну или сессии
 * - поиск навески по ID
 * - создание, обновление, удаление навесок
 * - получение сводной информации по навескам
 */
class WeighingRepository extends Repository
{
    /**
     * Получает список всех навесок для указанного бассейна
     * 
     * Включает информацию о пользователе, создавшем навеску.
     * Отсортированы по дате (от новых к старым).
     * 
     * @param int $poolId ID бассейна
     * @return array Массив навесок с информацией о пользователе
     */
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

    /**
     * Получает список всех навесок для указанной сессии
     * 
     * Включает информацию о пользователе, создавшем навеску.
     * Отсортированы по дате (от новых к старым).
     * 
     * @param int $sessionId ID сессии
     * @return array Массив навесок с информацией о пользователе
     */
    public function listBySession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT w.*, u.login AS created_by_login, u.full_name AS created_by_name ' .
            'FROM weighings w ' .
            'LEFT JOIN users u ON w.created_by = u.id ' .
            'WHERE w.session_id = ? ' .
            'ORDER BY w.recorded_at DESC'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Находит навеску по ID
     * 
     * Включает информацию о пользователе и бассейне.
     * 
     * @param int $id ID навески
     * @return array|null Данные навески или null, если не найдена
     */
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

    /**
     * Создает новую навеску
     * 
     * @param array $data Данные навески (pool_id, session_id, weight, fish_count, recorded_at, created_by)
     * @return int ID созданной навески
     */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO weighings (pool_id, session_id, weight, fish_count, recorded_at, created_by) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['pool_id'],
            $data['session_id'] ?? null,
            $data['weight'],
            $data['fish_count'],
            $data['recorded_at'],
            $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновляет данные навески
     * 
     * Динамически формирует SQL запрос на основе переданных данных.
     * Обновляет только те поля, которые переданы в массиве $data.
     * 
     * @param int $id ID навески
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
        $sql = 'UPDATE weighings SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Удаляет навеску
     * 
     * @param int $id ID навески для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM weighings WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Получает список навесок для бассейна с определенной даты
     * 
     * Используется для построения истории навесок с начала сессии.
     * 
     * @param int $poolId ID бассейна
     * @param string $startDate Дата начала (в формате БД)
     * @return array Массив навесок, отсортированных по дате (от старых к новым)
     */
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

    /**
     * Получает список навесок для сессии
     * 
     * Используется для построения истории навесок сессии.
     * 
     * @param int $sessionId ID сессии
     * @return array Массив навесок, отсортированных по дате (от старых к новым)
     */
    public function listForSession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT recorded_at, weight, fish_count
             FROM weighings
             WHERE session_id = ?
             ORDER BY recorded_at ASC'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Находит последнюю навеску для бассейна с определенной даты
     * 
     * Используется для расчета статусов на странице "Работа".
     * 
     * @param int $poolId ID бассейна
     * @param string $startDate Дата начала (в формате БД)
     * @return array|null Данные последней навески или null, если навесок нет
     */
    public function findLatestSince(int $poolId, string $startDate): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT weight, fish_count, recorded_at
             FROM weighings
             WHERE pool_id = ?
               AND recorded_at >= ?
             ORDER BY recorded_at DESC
             LIMIT 1'
        );
        $stmt->execute([$poolId, $startDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Находит последнюю навеску для сессии
     * 
     * Используется для расчета статусов на странице "Работа".
     * 
     * @param int $sessionId ID сессии
     * @return array|null Данные последней навески или null, если навесок нет
     */
    public function findLatestForSession(int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT weight, fish_count, recorded_at
             FROM weighings
             WHERE session_id = ?
             ORDER BY recorded_at DESC
             LIMIT 1'
        );
        $stmt->execute([$sessionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}


