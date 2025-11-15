<?php

namespace App\Repositories;

use PDO;

class FeedNormImageRepository extends Repository
{
    public function getByFeed(int $feedId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM feed_norm_images WHERE feed_id = ? ORDER BY uploaded_at DESC, id DESC'
        );
        $stmt->execute([$feedId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO feed_norm_images (
                feed_id, original_name, file_name, file_path,
                file_size, mime_type, uploaded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['feed_id'],
            $data['original_name'],
            $data['file_name'],
            $data['file_path'],
            $data['file_size'],
            $data['mime_type'],
            $data['uploaded_by'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT file_path FROM feed_norm_images WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $stmt = $this->pdo->prepare('DELETE FROM feed_norm_images WHERE id = ?');
        $stmt->execute([$id]);

        return $row;
    }

    public function deleteByFeed(int $feedId): array
    {
        $stmt = $this->pdo->prepare('SELECT file_path FROM feed_norm_images WHERE feed_id = ?');
        $stmt->execute([$feedId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->pdo->prepare('DELETE FROM feed_norm_images WHERE feed_id = ?');
        $stmt->execute([$feedId]);

        return array_column($rows, 'file_path');
    }
}

