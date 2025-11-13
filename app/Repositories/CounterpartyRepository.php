<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с контрагентами
 * 
 * Выполняет SQL запросы к таблице counterparties:
 * - получение списка всех контрагентов
 * - поиск контрагента по ID
 * - создание, обновление, удаление контрагентов
 * - подсчет документов для контрагентов
 */
class CounterpartyRepository extends Repository
{
    /**
     * Получает список всех контрагентов с информацией о создателе и обновителе
     * 
     * @return array<int,array<string,mixed>> Массив контрагентов, отсортированных по названию
     */
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT c.*, ' .
            'creator.login AS created_by_login, creator.full_name AS created_by_name, ' .
            'updater.login AS updated_by_login, updater.full_name AS updated_by_name ' .
            'FROM counterparties c ' .
            'LEFT JOIN users creator ON c.created_by = creator.id ' .
            'LEFT JOIN users updater ON c.updated_by = updater.id ' .
            'ORDER BY c.name ASC, c.created_at DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Возвращает карту количества документов для указанных контрагентов
     * 
     * @param array<int,int> $ids Массив ID контрагентов
     * @return array<int,int> Ассоциативный массив [counterparty_id => documents_count]
     */
    public function countDocumentsFor(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT counterparty_id, COUNT(*) AS documents_count FROM counterparty_documents WHERE counterparty_id IN ($placeholders) GROUP BY counterparty_id"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }

    /**
     * Находит контрагента по ID с полной информацией
     * 
     * Включает информацию о создателе и обновителе.
     * 
     * @param int $id ID контрагента
     * @return array|null Данные контрагента или null, если не найден
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, ' .
            'creator.login AS created_by_login, creator.full_name AS created_by_name, ' .
            'updater.login AS updated_by_login, updater.full_name AS updated_by_name ' .
            'FROM counterparties c ' .
            'LEFT JOIN users creator ON c.created_by = creator.id ' .
            'LEFT JOIN users updater ON c.updated_by = updater.id ' .
            'WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Создает нового контрагента
     * 
     * @param array<string,mixed> $data Данные контрагента (name, description, inn, phone, email, color, created_by, updated_by)
     * @return int ID созданного контрагента
     */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO counterparties (name, description, inn, phone, email, color, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['inn'],
            $data['phone'],
            $data['email'],
            $data['color'],
            $data['created_by'],
            $data['updated_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновляет данные контрагента
     * 
     * Динамически формирует SQL запрос на основе переданных данных.
     * Обновляет только те поля, которые переданы в массиве $data.
     * 
     * @param int $id ID контрагента
     * @param array<string,mixed> $data Ассоциативный массив полей для обновления (column => value)
     * @return void
     */
    public function update(int $id, array $data): void
    {
        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $sets[] = "$column = ?";
            $params[] = $value;
        }
        if (empty($sets)) {
            return;
        }
        $params[] = $id;
        $sql = 'UPDATE counterparties SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Удаляет контрагента
     * 
     * @param int $id ID контрагента для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM counterparties WHERE id = ?');
        $stmt->execute([$id]);
    }
}
