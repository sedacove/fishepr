<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с файлами задач
 * 
 * Выполняет SQL запросы к таблице task_files:
 * - получение списка файлов для задачи
 * - удаление файлов задачи
 */
class TaskFileRepository extends Repository
{
    /**
     * Получает список файлов для указанной задачи
     * 
     * @param int $taskId ID задачи
     * @return array Массив файлов, отсортированных по дате создания (от новых к старым)
     */
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

    /**
     * Удаляет все файлы для задачи
     * 
     * @param int $taskId ID задачи
     * @return void
     */
    public function deleteByTask(int $taskId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM task_files WHERE task_id = ?");
        $stmt->execute([$taskId]);
    }
}
