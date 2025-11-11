<?php

namespace App\Repositories;

use PDO;

/**
 * Storage abstraction for counterparties.
 */
class CounterpartyRepository extends Repository
{
    /**
     * Returns all counterparties with author/updater information.
     *
     * @return array<int,array<string,mixed>>
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
     * Returns a map counterparty_id => documents_count.
     *
     * @param array<int,int> $ids
     * @return array<int,int>
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
     * Returns detailed counterparty record or null when not found.
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
     * Creates a new counterparty entry.
     *
     * @param array<string,mixed> $data
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
     * Applies partial updates for a counterparty.
     *
     * @param array<string,mixed> $data column => value pairs
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

    /** Deletes counterparty row. */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM counterparties WHERE id = ?');
        $stmt->execute([$id]);
    }
}
