<?php

namespace App\Repositories;

use PDO;

class MeterRepository extends Repository
{
    public function listPublic(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, description FROM meters ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAdmin(): array
    {
        $stmt = $this->pdo->query(<<<SQL
            SELECT m.*, u.full_name AS created_by_name, u.login AS created_by_login
            FROM meters m
            LEFT JOIN users u ON u.id = m.created_by
            ORDER BY m.created_at DESC
        SQL);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT m.*, u.full_name AS created_by_name, u.login AS created_by_login
            FROM meters m
            LEFT JOIN users u ON u.id = m.created_by
            WHERE m.id = ?
        SQL);
        $stmt->execute([$id]);
        $meter = $stmt->fetch(PDO::FETCH_ASSOC);
        return $meter ?: null;
    }

    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM meters WHERE id = ?');
        $stmt->execute([$id]);
        return (bool)$stmt->fetchColumn();
    }

    public function insert(string $name, ?string $description, int $createdBy): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO meters (name, description, created_by) VALUES (?, ?, ?)');
        $stmt->execute([$name, $description, $createdBy]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description): void
    {
        $stmt = $this->pdo->prepare('UPDATE meters SET name = ?, description = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$name, $description, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM meters WHERE id = ?');
        $stmt->execute([$id]);
    }
}
