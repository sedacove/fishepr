<?php

namespace App\Repositories;

use PDO;

class TaskItemRepository extends Repository
{
    public function getByTask(int $taskId): array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT 
                ti.*,
                u.login as completed_by_login,
                u.full_name as completed_by_name
            FROM task_items ti
            LEFT JOIN users u ON ti.completed_by = u.id
            WHERE ti.task_id = ?
            ORDER BY ti.sort_order ASC, ti.created_at ASC
        SQL);
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Создает новую подзадачу
     * 
     * @param int $taskId ID задачи
     * @param string $title Название подзадачи
     * @param bool $isCompleted Статус выполнения
     * @param int|null $completedBy ID пользователя, завершившего подзадачу (если выполнена)
     * @param int $sortOrder Порядок сортировки
     * @return int ID созданной подзадачи
     */
    public function insert(int $taskId, string $title, bool $isCompleted, ?int $completedBy, int $sortOrder): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO task_items (task_id, title, is_completed, completed_at, completed_by, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $taskId,
            $title,
            $isCompleted ? 1 : 0,
            $isCompleted ? date('Y-m-d H:i:s') : null,
            $isCompleted ? $completedBy : null,
            $sortOrder,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновляет данные подзадачи
     * 
     * @param int $itemId ID подзадачи
     * @param string $title Название подзадачи
     * @param int $sortOrder Порядок сортировки
     * @param bool $isCompleted Статус выполнения
     * @param int|null $completedBy ID пользователя, завершившего подзадачу
     * @param string|null $completedAt Дата завершения подзадачи
     * @return void
     */
    public function update(int $itemId, string $title, int $sortOrder, bool $isCompleted, ?int $completedBy, ?string $completedAt): void
    {
        $stmt = $this->pdo->prepare("UPDATE task_items SET title = ?, sort_order = ?, is_completed = ?, completed_at = ?, completed_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([
            $title,
            $sortOrder,
            $isCompleted ? 1 : 0,
            $completedAt,
            $completedBy,
            $itemId,
        ]);
    }

    /**
     * Удаляет подзадачи, которых нет в списке сохраняемых
     * 
     * Используется при обновлении списка подзадач задачи.
     * 
     * @param int $taskId ID задачи
     * @param array $keepIds Массив ID подзадач, которые нужно сохранить
     * @return void
     */
    public function deleteMissing(int $taskId, array $keepIds): void
    {
        if (empty($keepIds)) {
            $stmt = $this->pdo->prepare("DELETE FROM task_items WHERE task_id = ?");
            $stmt->execute([$taskId]);
            return;
        }
        $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM task_items WHERE task_id = ? AND id NOT IN ($placeholders)");
        $stmt->execute(array_merge([$taskId], $keepIds));
    }

    /**
     * Обновляет порядок сортировки подзадачи
     * 
     * @param int $itemId ID подзадачи
     * @param int $taskId ID задачи (для проверки принадлежности)
     * @param int $sortOrder Новый порядок сортировки
     * @return void
     */
    public function updateSortOrder(int $itemId, int $taskId, int $sortOrder): void
    {
        $stmt = $this->pdo->prepare("UPDATE task_items SET sort_order = ? WHERE id = ? AND task_id = ?");
        $stmt->execute([$sortOrder, $itemId, $taskId]);
    }

    /**
     * Находит подзадачу по ID с информацией о задаче
     * 
     * @param int $itemId ID подзадачи
     * @return array|null Данные подзадачи с информацией о задаче или null, если не найдена
     */
    public function findWithTask(int $itemId): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT ti.*, t.assigned_to
            FROM task_items ti
            INNER JOIN tasks t ON ti.task_id = t.id
            WHERE ti.id = ?
        SQL);
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    /**
     * Устанавливает статус выполнения подзадачи
     * 
     * @param int $itemId ID подзадачи
     * @param bool $completed true для отметки как выполненной, false для снятия отметки
     * @param int $userId ID пользователя, изменяющего статус
     * @return void
     */
    public function setCompletion(int $itemId, bool $completed, int $userId): void
    {
        if ($completed) {
            $stmt = $this->pdo->prepare("UPDATE task_items SET is_completed = 1, completed_at = NOW(), completed_by = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$userId, $itemId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE task_items SET is_completed = 0, completed_at = NULL, completed_by = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$itemId]);
        }
    }

    /**
     * Получает статистику выполнения подзадач для задачи
     * 
     * @param int $taskId ID задачи
     * @return array Массив с ключами 'total' (общее количество) и 'completed' (количество выполненных)
     */
    public function completionStats(int $taskId): array
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed FROM task_items WHERE task_id = ?");
        $stmt->execute([$taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'completed' => 0];
    }
}
