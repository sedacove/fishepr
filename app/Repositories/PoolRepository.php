<?php

namespace App\Repositories;

use App\Models\Pool\Pool;
use PDO;

/**
 * Репозиторий для работы с бассейнами
 * 
 * Выполняет SQL запросы к таблице pools:
 * - получение списка всех бассейнов
 * - поиск бассейна по ID
 * - создание, обновление, удаление бассейнов
 * - управление порядком сортировки
 * - получение активных бассейнов
 */
class PoolRepository extends Repository
{
    /**
     * Получает список всех бассейнов
     * 
     * @return Pool[] Массив бассейнов, отсортированных по порядку сортировки и дате создания
     */
    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT p.*, u.login AS created_by_login, u.full_name AS created_by_name
             FROM pools p
             LEFT JOIN users u ON u.id = p.created_by
             ORDER BY p.sort_order ASC, p.created_at ASC'
        );

        return array_map(fn ($row) => new Pool($row), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Находит бассейн по ID
     * 
     * @param int $id ID бассейна
     * @return Pool|null Модель бассейна или null, если не найден
     */
    public function find(int $id): ?Pool
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.login AS created_by_login, u.full_name AS created_by_name
             FROM pools p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? new Pool($row) : null;
    }

    /**
     * Находит активный бассейн по ID
     * 
     * @param int $id ID бассейна
     * @return array|null Данные бассейна или null, если не найден или неактивен
     */
    public function findActive(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.login AS created_by_login, u.full_name AS created_by_name
             FROM pools p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.id = ? AND p.is_active = 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Создает новый бассейн
     * 
     * @param string $name Название бассейна
     * @param int $sortOrder Порядок сортировки
     * @param int $userId ID пользователя, создающего бассейн
     * @return int ID созданного бассейна
     */
    public function create(string $name, int $sortOrder, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pools (name, sort_order, created_by)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $sortOrder, $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Обновляет данные бассейна
     * 
     * Динамически формирует SQL запрос на основе переданных данных.
     * Обновляет только те поля, которые переданы в массиве $data.
     * 
     * @param int $id ID бассейна
     * @param array $data Ассоциативный массив полей для обновления (column => value)
     * @return void
     */
    public function update(int $id, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = [];
        $params = [];
        foreach ($data as $column => $value) {
            $columns[] = "{$column} = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $sql = 'UPDATE pools SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Удаляет бассейн
     * 
     * @param int $id ID бассейна для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM pools WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Получает максимальный порядок сортировки среди всех бассейнов
     * 
     * Используется для назначения порядка новым бассейнам.
     * 
     * @return int Максимальный порядок сортировки или -1, если бассейнов нет
     */
    public function maxSortOrder(): int
    {
        $stmt = $this->pdo->query('SELECT MAX(sort_order) AS max_order FROM pools');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return isset($row['max_order']) ? (int) $row['max_order'] : -1;
    }

    /**
     * Обновляет порядок сортировки бассейнов
     * 
     * Устанавливает порядок сортировки в соответствии с порядком ID в массиве.
     * 
     * @param array $ids Массив ID бассейнов в новом порядке
     * @return void
     */
    public function updateOrder(array $ids): void
    {
        $stmt = $this->pdo->prepare('UPDATE pools SET sort_order = ? WHERE id = ?');
        foreach ($ids as $index => $poolId) {
            $stmt->execute([$index, $poolId]);
        }
    }

    /**
     * Получает список активных бассейнов
     * 
     * @return array<array{id:int,name:string}> Массив активных бассейнов (id, name)
     */
    public function listActive(): array
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
     * Получает активные бассейны с информацией об активных сессиях
     * 
     * Для каждого активного бассейна возвращает информацию об активной (не завершенной) сессии, если она есть.
     * 
     * @return array<array{id:int,name:string,pool_name:string,active_session:array|null}> Массив бассейнов с информацией об активных сессиях
     */
    public function getActiveWithSessions(): array
    {
        $stmt = $this->pdo->query(
            'SELECT 
                p.id,
                p.name,
                p.name AS pool_name,
                s.id AS active_session_id,
                s.name AS active_session_name,
                s.start_date AS active_session_start_date
             FROM pools p
             LEFT JOIN sessions s ON s.pool_id = p.id AND s.is_completed = 0
             WHERE p.is_active = 1
             ORDER BY p.sort_order ASC, p.name ASC'
        );
        
        $result = [];
        $processed = [];
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $poolId = (int)$row['id'];
            
            if (!isset($processed[$poolId])) {
                $processed[$poolId] = [
                    'id' => $poolId,
                    'name' => $row['name'],
                    'pool_name' => $row['pool_name'],
                    'active_session' => null,
                ];
                
                if ($row['active_session_id']) {
                    $processed[$poolId]['active_session'] = [
                        'id' => (int)$row['active_session_id'],
                        'name' => $row['active_session_name'],
                        'session_name' => $row['active_session_name'], // Для совместимости с фронтендом
                        'start_date' => $row['active_session_start_date'],
                    ];
                }
                
                $result[] = &$processed[$poolId];
            }
        }
        
        return $result;
    }
}


