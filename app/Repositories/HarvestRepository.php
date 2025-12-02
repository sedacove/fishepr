<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с отборами
 * 
 * Выполняет SQL запросы к таблице harvests:
 * - получение списка отборов по сессии
 * - поиск отбора по ID
 * - создание, обновление, удаление отборов
 * - получение сводной информации по отборам
 */
class HarvestRepository extends Repository
{
    /**
     * Получает список всех отборов для указанной сессии
     * 
     * Включает информацию о пользователе, создавшем отбор, контрагенте, сессии и бассейне.
     * Отсортированы по дате (от новых к старым).
     * 
     * @param int $sessionId ID сессии
     * @return array Массив отборов с информацией о пользователе, контрагенте, сессии и бассейне
     */
    public function listBySession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT h.*, ' .
            'u.login AS created_by_login, u.full_name AS created_by_name, ' .
            'c.name AS counterparty_name, c.color AS counterparty_color, ' .
            's.id AS session_id, s.name AS session_name, ' .
            'p.id AS pool_id, p.name AS pool_name ' .
            'FROM harvests h ' .
            'INNER JOIN sessions s ON s.id = h.session_id ' .
            'LEFT JOIN pools p ON p.id = s.pool_id ' .
            'LEFT JOIN users u ON h.created_by = u.id ' .
            'LEFT JOIN counterparties c ON h.counterparty_id = c.id ' .
            'WHERE h.session_id = ? ' .
            'ORDER BY h.recorded_at DESC'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Получает список отборов для завершенных сессий
     * 
     * Включает информацию о пользователе, контрагенте, сессии и бассейне.
     * Отсортированы по дате (от новых к старым).
     * 
     * @return array Массив отборов с информацией о сессии и бассейне
     */
    public function listForCompletedSessions(): array
    {
        $stmt = $this->pdo->query(
            'SELECT h.*, ' .
            'u.login AS created_by_login, u.full_name AS created_by_name, ' .
            'c.name AS counterparty_name, c.color AS counterparty_color, ' .
            's.id AS session_id, s.name AS session_name, ' .
            'p.id AS pool_id, p.name AS pool_name ' .
            'FROM harvests h ' .
            'INNER JOIN sessions s ON s.id = h.session_id ' .
            'LEFT JOIN pools p ON p.id = s.pool_id ' .
            'LEFT JOIN users u ON h.created_by = u.id ' .
            'LEFT JOIN counterparties c ON h.counterparty_id = c.id ' .
            'WHERE s.is_completed = 1 ' .
            'ORDER BY h.recorded_at DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Находит отбор по ID
     * 
     * Включает информацию о пользователе, контрагенте, сессии и бассейне.
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
            's.id AS session_id, s.name AS session_name, ' .
            'p.id AS pool_id, p.name AS pool_name ' .
            'FROM harvests h ' .
            'INNER JOIN sessions s ON s.id = h.session_id ' .
            'LEFT JOIN pools p ON p.id = s.pool_id ' .
            'LEFT JOIN users u ON h.created_by = u.id ' .
            'LEFT JOIN counterparties c ON h.counterparty_id = c.id ' .
            'WHERE h.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Создает новый отбор
     * 
     * @param array $data Данные отбора (session_id, weight, fish_count, counterparty_id, recorded_at, created_by)
     * @return int ID созданного отбора
     */
    public function insert(array $data): int
    {
        // Получаем pool_id из сессии для обратной совместимости с внешним ключом
        $stmtSession = $this->pdo->prepare('SELECT pool_id FROM sessions WHERE id = ?');
        $stmtSession->execute([$data['session_id']]);
        $session = $stmtSession->fetch(PDO::FETCH_ASSOC);
        $poolId = $session ? (int)$session['pool_id'] : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO harvests (session_id, pool_id, weight, fish_count, counterparty_id, recorded_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['session_id'],
            $poolId,
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
     * Получает список отборов для сессии
     * 
     * Используется для построения истории отборов сессии.
     * 
     * @param int $sessionId ID сессии
     * @return array Массив отборов с информацией о контрагенте, отсортированных по дате (от старых к новым)
     */
    public function listForSession(int $sessionId): array
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
             WHERE h.session_id = ?
             ORDER BY h.recorded_at ASC'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Получает сумму отборов для сессии
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
             FROM harvests
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
     * Получает список отборов для отчета с фильтрами
     * 
     * Включает информацию о контрагенте, бассейне и посадке (через активную сессию на момент отбора).
     * Фильтрует по датам, контрагенту и посадке.
     * 
     * @param string|null $dateFrom Дата начала периода (формат YYYY-MM-DD)
     * @param string|null $dateTo Дата окончания периода (формат YYYY-MM-DD)
     * @param int|null $counterpartyId ID контрагента (null для всех)
     * @param int|null $plantingId ID посадки (null для всех)
     * @return array Массив отборов с информацией о контрагенте, бассейне и посадке
     */
    public function listForReport(?string $dateFrom, ?string $dateTo, ?int $counterpartyId, ?int $plantingId): array
    {
        $conditions = [];
        $params = [];

        // Фильтр по датам
        if ($dateFrom) {
            $conditions[] = 'DATE(h.recorded_at) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $conditions[] = 'DATE(h.recorded_at) <= ?';
            $params[] = $dateTo;
        }

        // Фильтр по контрагенту
        if ($counterpartyId !== null) {
            $conditions[] = 'h.counterparty_id = ?';
            $params[] = $counterpartyId;
        }

        // Фильтр по посадке (через сессию отбора)
        if ($plantingId !== null) {
            $conditions[] = 's.planting_id = ?';
            $params[] = $plantingId;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = 'SELECT 
                    h.id,
                    h.recorded_at,
                    h.weight,
                    h.fish_count,
                    h.session_id,
                    s.name AS session_name,
                    s.pool_id,
                    p.name AS pool_name,
                    s.planting_id,
                    pl.name AS planting_name,
                    h.counterparty_id,
                    c.name AS counterparty_name,
                    c.color AS counterparty_color
                FROM harvests h
                INNER JOIN sessions s ON s.id = h.session_id
                LEFT JOIN pools p ON p.id = s.pool_id
                LEFT JOIN plantings pl ON pl.id = s.planting_id
                LEFT JOIN counterparties c ON c.id = h.counterparty_id
                ' . $whereClause . '
                ORDER BY h.recorded_at DESC, h.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}


