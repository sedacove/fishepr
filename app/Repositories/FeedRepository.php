<?php

namespace App\Repositories;

use PDO;

class FeedRepository extends Repository
{
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT f.*,
                    (SELECT COUNT(*) FROM feed_norm_images i WHERE i.feed_id = f.id) AS images_count
             FROM feeds f
             ORDER BY f.name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM feeds WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO feeds (
                name, description, granule,
                formula_econom, formula_normal, formula_growth,
                manufacturer, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['granule'],
            $data['formula_econom'],
            $data['formula_normal'],
            $data['formula_growth'],
            $data['manufacturer'],
            $data['created_by'],
            $data['updated_by'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE feeds SET
                name = ?,
                description = ?,
                granule = ?,
                formula_econom = ?,
                formula_normal = ?,
                formula_growth = ?,
                manufacturer = ?,
                updated_by = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['granule'],
            $data['formula_econom'],
            $data['formula_normal'],
            $data['formula_growth'],
            $data['manufacturer'],
            $data['updated_by'],
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM feeds WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM feeds WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function options(): array
    {
        $stmt = $this->pdo->query('SELECT id, name FROM feeds ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

