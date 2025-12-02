<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы со смертностью
 * 
 * Выполняет SQL запросы к таблице mortality:
 * - получение списка записей смертности для сессии
 * - поиск записи смертности по ID
 * - создание, обновление, удаление записей смертности
 * - получение дневных итогов смертности
 * - получение суммы смертности за период
 */
class MortalityRepository extends Repository
{
    /**
     * Получает список записей смертности для указанной сессии
     * 
     * @param int $sessionId ID сессии
     * @return array Массив записей смертности, отсортированных по дате записи (от новых к старым)
     */
    public function listBySession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                m.*,
                u.login AS created_by_login,
                u.full_name AS created_by_name,
                s.id AS session_id,
                s.name AS session_name,
                p.id AS pool_id,
                p.name AS pool_name
             FROM mortality m
             INNER JOIN sessions s ON s.id = m.session_id
             LEFT JOIN pools p ON p.id = s.pool_id
             LEFT JOIN users u ON m.created_by = u.id
             WHERE m.session_id = ?
             ORDER BY m.recorded_at DESC'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Получает список записей смертности из завершенных сессий
     * 
     * Используется для отображения падежей из завершенных сессий в отдельном табе.
     * 
     * @return array Массив записей смертности с информацией о сессии и бассейне
     */
    public function listForCompletedSessions(): array
    {
        $stmt = $this->pdo->query(
            'SELECT m.*, ' .
            'u.login AS created_by_login, u.full_name AS created_by_name, ' .
            's.id AS session_id, s.name AS session_name, ' .
            'p.id AS pool_id, p.name AS pool_name ' .
            'FROM mortality m ' .
            'INNER JOIN sessions s ON s.id = m.session_id ' .
            'LEFT JOIN pools p ON p.id = s.pool_id ' .
            'LEFT JOIN users u ON m.created_by = u.id ' .
            'WHERE s.is_completed = 1 ' .
            'ORDER BY m.recorded_at DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Находит запись смертности по ID с информацией о пользователе, сессии и бассейне
     * 
     * @param int $id ID записи смертности
     * @return array|null Данные записи с информацией о создателе, сессии и бассейне или null, если не найдено
     */
    public function findWithUser(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                m.*,
                u.login AS created_by_login,
                u.full_name AS created_by_name,
                s.id AS session_id,
                s.name AS session_name,
                p.id AS pool_id,
                p.name AS pool_name
             FROM mortality m
             INNER JOIN sessions s ON s.id = m.session_id
             LEFT JOIN pools p ON p.id = s.pool_id
             LEFT JOIN users u ON m.created_by = u.id
             WHERE m.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Создает новую запись смертности
     * 
     * @param int $sessionId ID сессии
     * @param float $weight Вес
     * @param int $fishCount Количество рыбы
     * @param string $recordedAt Дата и время записи
     * @param int $userId ID пользователя, создающего запись
     * @return int ID созданной записи
     */
    public function insert(int $sessionId, float $weight, int $fishCount, string $recordedAt, int $userId): int
    {
        // Получаем pool_id из сессии для обратной совместимости с внешним ключом
        $stmtSession = $this->pdo->prepare('SELECT pool_id FROM sessions WHERE id = ?');
        $stmtSession->execute([$sessionId]);
        $session = $stmtSession->fetch(PDO::FETCH_ASSOC);
        $poolId = $session ? (int)$session['pool_id'] : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO mortality (session_id, pool_id, weight, fish_count, recorded_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$sessionId, $poolId, $weight, $fishCount, $recordedAt, $userId]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновляет данные записи смертности
     * 
     * Динамически формирует SQL запрос на основе переданных данных.
     * Обновляет только те поля, которые переданы в массиве $data.
     * 
     * @param int $id ID записи смертности
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
        $sql = 'UPDATE mortality SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Удаляет запись смертности
     * 
     * @param int $id ID записи для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM mortality WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Получает дневные итоги смертности для сессии
     * 
     * Группирует записи смертности по дням и суммирует вес и количество.
     * Используется для построения графиков смертности.
     * 
     * @param int $sessionId ID сессии
     * @return array Массив дневных итогов, отсортированных по дате (от старых к новым)
     */
    public function getDailyTotalsForSession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                DATE(recorded_at) AS day,
                SUM(weight) AS total_weight,
                SUM(fish_count) AS total_count
             FROM mortality
             WHERE session_id = ?
             GROUP BY day
             ORDER BY day ASC'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Получает сумму смертности для сессии
     * 
     * @param int $sessionId ID сессии
     * @return array Массив с ключами total_weight и total_count
     */
    public function sumForSession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                COALESCE(SUM(weight), 0) AS total_weight,
                COALESCE(SUM(fish_count), 0) AS total_count
             FROM mortality
             WHERE session_id = ?'
        );
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_weight' => 0, 'total_count' => 0];
        return [
            'total_weight' => (float)$row['total_weight'],
            'total_count' => (int)$row['total_count'],
        ];
    }

    /**
     * Получает сумму количества смертности для сессии за указанное количество часов
     * 
     * Используется для расчета статусов смертности на странице "Работа".
     * 
     * @param int $sessionId ID сессии
     * @param int $hours Количество часов для расчета
     * @return int Сумма количества смертности за период
     */
    public function sumCountForHours(int $sessionId, int $hours): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(fish_count), 0) AS total_count
             FROM mortality
             WHERE session_id = ?
               AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)'
        );
        $stmt->bindValue(1, $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(2, $hours, PDO::PARAM_INT);
        $stmt->execute();
        return (int)($stmt->fetchColumn() ?: 0);
    }

    /**
     * Получает дневные итоги смертности за указанный период (для всех бассейнов)
     * 
     * Используется для виджетов дашборда.
     * 
     * @param string $startDate Дата начала (в формате БД)
     * @param string $endDate Дата окончания (в формате БД)
     * @return array Массив дневных итогов, отсортированных по дате (от старых к новым)
     */
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

    /**
     * Получает дневные итоги смертности по бассейнам за указанный период
     * 
     * Используется для виджетов дашборда, показывающих смертность по бассейнам.
     * 
     * @param array $poolIds Массив ID бассейнов
     * @param string $startDate Дата начала (в формате БД)
     * @param string $endDate Дата окончания (в формате БД)
     * @return array Ассоциативный массив: [pool_id => [date => [total_count, total_weight]]]
     */
    public function getDailyTotalsByPool(array $poolIds, string $startDate, string $endDate): array
    {
        if (empty($poolIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($poolIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT 
                s.pool_id,
                DATE(m.recorded_at) AS record_date,
                COALESCE(SUM(m.fish_count), 0) AS total_count,
                COALESCE(SUM(m.weight), 0) AS total_weight
             FROM mortality m
             INNER JOIN sessions s ON s.id = m.session_id
             WHERE s.pool_id IN ($placeholders)
               AND m.recorded_at >= ?
               AND m.recorded_at <= ?
             GROUP BY s.pool_id, DATE(m.recorded_at)"
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


