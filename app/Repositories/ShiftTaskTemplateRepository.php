<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с шаблонами заданий смены
 */
class ShiftTaskTemplateRepository extends Repository
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * Возвращает все шаблоны (с опциональной фильтрацией по активности)
     *
     * @param bool|null $isActive
     * @return array
     */
    public function listAll(?bool $isActive = null): array
    {
        $sql = '
            SELECT t.*, 
                   uc.full_name AS created_by_name,
                   uu.full_name AS updated_by_name
            FROM shift_task_templates t
            LEFT JOIN users uc ON uc.id = t.created_by
            LEFT JOIN users uu ON uu.id = t.updated_by
        ';

        $params = [];
        if ($isActive !== null) {
            $sql .= ' WHERE t.is_active = ?';
            $params[] = $isActive ? 1 : 0;
        }

        $sql .= ' ORDER BY t.is_active DESC, t.title ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM shift_task_templates WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO shift_task_templates
            (title, description, frequency, start_date, week_day, day_of_month, due_time, is_active, created_by, updated_by)
            VALUES (:title, :description, :frequency, :start_date, :week_day, :day_of_month, :due_time, :is_active, :created_by, :updated_by)
        ');

        $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':frequency' => $data['frequency'],
            ':start_date' => $data['start_date'],
            ':week_day' => $data['week_day'],
            ':day_of_month' => $data['day_of_month'],
            ':due_time' => $data['due_time'],
            ':is_active' => $data['is_active'] ? 1 : 0,
            ':created_by' => $data['created_by'],
            ':updated_by' => $data['created_by'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE shift_task_templates
            SET title = :title,
                description = :description,
                frequency = :frequency,
                start_date = :start_date,
                week_day = :week_day,
                day_of_month = :day_of_month,
                due_time = :due_time,
                is_active = :is_active,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');

        $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':frequency' => $data['frequency'],
            ':start_date' => $data['start_date'],
            ':week_day' => $data['week_day'],
            ':day_of_month' => $data['day_of_month'],
            ':due_time' => $data['due_time'],
            ':is_active' => $data['is_active'] ? 1 : 0,
            ':updated_by' => $data['updated_by'],
            ':id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM shift_task_templates WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Возвращает активные шаблоны для генерации заданий
     *
     * @return array
     */
    public function listActive(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM shift_task_templates WHERE is_active = 1 ORDER BY id ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


