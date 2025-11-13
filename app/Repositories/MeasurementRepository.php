<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с измерениями
 * 
 * Выполняет SQL запросы к таблице measurements:
 * - получение списка измерений для бассейна
 * - поиск измерения по ID
 * - создание, обновление, удаление измерений
 * - получение последних измерений для бассейна
 * - получение измерений с определенной даты
 */
class MeasurementRepository extends Repository
{
    /**
     * Получает список измерений для указанного бассейна
     * 
     * @param int $poolId ID бассейна
     * @return array Массив измерений, отсортированных по дате измерения (от новых к старым)
     */
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

    /**
     * Находит измерение по ID с информацией о пользователе и бассейне
     * 
     * @param int $id ID измерения
     * @return array|null Данные измерения с информацией о создателе и бассейне или null, если не найдено
     */
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

    /**
     * Создает новое измерение
     * 
     * @param int $poolId ID бассейна
     * @param float $temperature Температура
     * @param float $oxygen Кислород
     * @param string $measuredAt Дата и время измерения
     * @param int $userId ID пользователя, создающего измерение
     * @return int ID созданного измерения
     */
    public function insert(int $poolId, float $temperature, float $oxygen, string $measuredAt, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO measurements (pool_id, temperature, oxygen, measured_at, created_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$poolId, $temperature, $oxygen, $measuredAt, $userId]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновляет данные измерения
     * 
     * Динамически формирует SQL запрос на основе переданных данных.
     * Обновляет только те поля, которые переданы в массиве $data.
     * 
     * @param int $id ID измерения
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
        $sql = 'UPDATE measurements SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Удаляет измерение
     * 
     * @param int $id ID измерения для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM measurements WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Получает список измерений для бассейна с определенной даты
     * 
     * Используется для построения графиков и истории измерений с начала сессии.
     * 
     * @param int $poolId ID бассейна
     * @param string $startDate Дата начала (в формате БД)
     * @return array Массив измерений, отсортированных по дате измерения (от старых к новым)
     */
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

    /**
     * Получает последние измерения для бассейна
     * 
     * @param int $poolId ID бассейна
     * @param int $limit Количество последних измерений (по умолчанию 2)
     * @return array Массив последних измерений, отсортированных по дате (от новых к старым)
     */
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

    /**
     * Получает последние измерения по указанному столбцу (температура или кислород)
     * 
     * Используется для виджетов дашборда, показывающих последние измерения температуры или кислорода.
     * 
     * @param string $column Название столбца ('temperature' или 'oxygen')
     * @param int $limit Количество последних измерений (по умолчанию 20)
     * @return array Массив последних измерений с информацией о бассейне, отсортированных по дате (от новых к старым)
     * @throws \InvalidArgumentException Если указан неподдерживаемый столбец
     */
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


