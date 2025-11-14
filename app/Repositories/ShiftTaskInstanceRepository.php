<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с экземплярами заданий смены
 */
class ShiftTaskInstanceRepository extends Repository
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * Возвращает экземпляр по шаблону и дате смены
     */
    public function findByTemplateAndDate(int $templateId, string $shiftDate): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM shift_task_instances WHERE template_id = ? AND shift_date = ?');
        $stmt->execute([$templateId, $shiftDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Возвращает экземпляр по ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT i.*, t.title, t.description, t.due_time AS template_due_time
            FROM shift_task_instances i
            INNER JOIN shift_task_templates t ON t.id = i.template_id
            WHERE i.id = ?
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Создает экземпляр задания
     */
    public function create(int $templateId, string $shiftDate, string $dueAt): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO shift_task_instances (template_id, shift_date, due_at)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$templateId, $shiftDate, $dueAt]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновляет due_at у существующего экземпляра
     */
    public function touchDueDate(int $id, string $dueAt): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE shift_task_instances
            SET due_at = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');
        $stmt->execute([$dueAt, $id]);
    }

    /**
     * Получает список заданий на смену с информацией о шаблоне и пользователе
     */
    public function listForShiftDate(string $shiftDate): array
    {
        $stmt = $this->pdo->prepare('
            SELECT i.*,
                   t.title,
                   t.description,
                   t.frequency,
                   t.due_time AS template_due_time,
                   u.full_name AS completed_by_name
            FROM shift_task_instances i
            INNER JOIN shift_task_templates t ON t.id = i.template_id
            LEFT JOIN users u ON u.id = i.completed_by
            WHERE i.shift_date = ?
            ORDER BY t.due_time ASC, t.title ASC
        ');
        $stmt->execute([$shiftDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Помечает задания предыдущей смены как просроченные
     */
    public function markMissedBefore(string $shiftDate): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE shift_task_instances
            SET status = \'missed\',
                updated_at = CURRENT_TIMESTAMP
            WHERE shift_date < ?
              AND status = \'pending\'
        ');
        $stmt->execute([$shiftDate]);
    }

    /**
     * Переключает статус выполнения задания
     */
    public function setCompletion(int $id, bool $completed, int $userId, ?string $note = null): void
    {
        if ($completed) {
            $stmt = $this->pdo->prepare('
                UPDATE shift_task_instances
                SET status = \'completed\',
                    completed_at = NOW(),
                    completed_by = :user_id,
                    note = :note,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ');
            $stmt->execute([
                ':note' => $note,
                ':id' => $id,
                ':user_id' => $userId,
            ]);
            return;
        }

        $stmt = $this->pdo->prepare('
            UPDATE shift_task_instances
            SET status = \'pending\',
                completed_at = NULL,
                completed_by = NULL,
                note = :note,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            ':note' => $note,
            ':id' => $id,
        ]);
    }
}


