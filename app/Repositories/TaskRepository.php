<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с задачами
 * 
 * Выполняет SQL запросы к таблице tasks:
 * - получение задач, назначенных на пользователя
 * - получение задач, созданных пользователем
 * - поиск задачи по ID
 * - создание, обновление, удаление задач
 * - управление статусом выполнения задач
 */
class TaskRepository extends Repository
{
    /**
     * Получает список задач, назначенных на пользователя
     * 
     * @param int $userId ID пользователя
     * @return array Массив задач, отсортированных по статусу выполнения, дате выполнения и дате создания
     */
    public function getTasksAssignedTo(int $userId): array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT 
                t.*,
                u1.login as assigned_to_login,
                u1.full_name as assigned_to_name,
                u2.login as created_by_login,
                u2.full_name as created_by_name,
                u3.login as completed_by_login,
                u3.full_name as completed_by_name,
                (SELECT COUNT(*) FROM task_items ti WHERE ti.task_id = t.id) as items_count,
                (SELECT COUNT(*) FROM task_items ti WHERE ti.task_id = t.id AND ti.is_completed = 1) as items_completed_count
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.created_by = u2.id
            LEFT JOIN users u3 ON t.completed_by = u3.id
            WHERE t.assigned_to = ? AND u1.deleted_at IS NULL
            ORDER BY t.is_completed ASC, t.due_date ASC, t.created_at DESC
        SQL);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получает список задач, созданных пользователем
     * 
     * @param int $userId ID пользователя
     * @return array Массив задач, отсортированных по статусу выполнения, дате выполнения и дате создания
     */
    public function getTasksCreatedBy(int $userId): array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT 
                t.*,
                u1.login as assigned_to_login,
                u1.full_name as assigned_to_name,
                u2.login as created_by_login,
                u2.full_name as created_by_name,
                u3.login as completed_by_login,
                u3.full_name as completed_by_name,
                (SELECT COUNT(*) FROM task_items ti WHERE ti.task_id = t.id) as items_count,
                (SELECT COUNT(*) FROM task_items ti WHERE ti.task_id = t.id AND ti.is_completed = 1) as items_completed_count
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.created_by = u2.id
            LEFT JOIN users u3 ON t.completed_by = u3.id
            WHERE t.created_by = ? AND u1.deleted_at IS NULL
            ORDER BY t.is_completed ASC, t.due_date ASC, t.created_at DESC
        SQL);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Находит задачу по ID
     * 
     * @param int $taskId ID задачи
     * @return array|null Данные задачи с информацией о пользователях или null, если не найдена
     */
    public function findById(int $taskId): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT 
                t.*,
                u1.login as assigned_to_login,
                u1.full_name as assigned_to_name,
                u2.login as created_by_login,
                u2.full_name as created_by_name,
                u3.login as completed_by_login,
                u3.full_name as completed_by_name
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.created_by = u2.id
            LEFT JOIN users u3 ON t.completed_by = u3.id
            WHERE t.id = ?
        SQL);
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        return $task ?: null;
    }

    /**
     * Создает новую задачу
     * 
     * @param string $title Название задачи
     * @param string|null $description Описание задачи
     * @param int $assignedTo ID пользователя, на которого назначена задача
     * @param int $createdBy ID пользователя, создающего задачу
     * @param string|null $dueDate Дата выполнения задачи
     * @return int ID созданной задачи
     */
    public function create(string $title, ?string $description, int $assignedTo, int $createdBy, ?string $dueDate): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO tasks (title, description, assigned_to, created_by, due_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description ?: null, $assignedTo, $createdBy, $dueDate ?: null]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновляет данные задачи
     * 
     * @param int $taskId ID задачи
     * @param string $title Название задачи
     * @param string|null $description Описание задачи
     * @param int $assignedTo ID пользователя, на которого назначена задача
     * @param string|null $dueDate Дата выполнения задачи
     * @return void
     */
    public function update(int $taskId, string $title, ?string $description, int $assignedTo, ?string $dueDate): void
    {
        $stmt = $this->pdo->prepare("UPDATE tasks SET title = ?, description = ?, assigned_to = ?, due_date = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $description ?: null, $assignedTo, $dueDate ?: null, $taskId]);
    }

    /**
     * Удаляет задачу
     * 
     * @param int $taskId ID задачи для удаления
     * @return void
     */
    public function delete(int $taskId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
    }

    /**
     * Отмечает задачу как выполненную
     * 
     * @param int $taskId ID задачи
     * @param int $userId ID пользователя, завершившего задачу
     * @return void
     */
    public function markCompleted(int $taskId, int $userId): void
    {
        $stmt = $this->pdo->prepare("UPDATE tasks SET is_completed = 1, completed_at = NOW(), completed_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$userId, $taskId]);
    }

    /**
     * Возвращает задачу в работу (снимает отметку о выполнении)
     * 
     * @param int $taskId ID задачи
     * @return void
     */
    public function markInProgress(int $taskId): void
    {
        $stmt = $this->pdo->prepare("UPDATE tasks SET is_completed = 0, completed_at = NULL, completed_by = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$taskId]);
    }
}
