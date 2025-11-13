<?php

namespace App\Repositories;

use PDO;

class TaskFileRepository extends Repository
{
    public function getByTask(int $taskId): array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT 
                tf.*,
                u.login as uploaded_by_login,
                u.full_name as uploaded_by_name
            FROM task_files tf
            LEFT JOIN users u ON tf.uploaded_by = u.id
            WHERE tf.task_id = ?
            ORDER BY tf.created_at DESC
        SQL);
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteByTask(int $taskId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM task_files WHERE task_id = ?");
        $stmt->execute([$taskId]);
    }
}
