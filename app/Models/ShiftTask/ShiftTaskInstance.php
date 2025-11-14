<?php

namespace App\Models\ShiftTask;

use App\Models\Model;

/**
 * Модель экземпляра задания смены
 *
 * DTO для конкретного задания на смену.
 */
class ShiftTaskInstance extends Model
{
    /**
     * @var int|null ID экземпляра
     */
    public ?int $id = null;

    /**
     * @var int ID шаблона, по которому создано задание
     */
    public int $template_id;

    /**
     * @var string Дата смены (Y-m-d)
     */
    public string $shift_date;

    /**
     * @var string Дата/время, к которому следует выполнить задание
     */
    public string $due_at;

    /**
     * @var string Статус задания (pending, completed, missed)
     */
    public string $status = 'pending';

    /**
     * @var string|null Дата/время выполнения
     */
    public ?string $completed_at = null;

    /**
     * @var int|null ID пользователя, отметившего выполнение
     */
    public ?int $completed_by = null;

    /**
     * @var string|null Комментарий к выполнению/пропуску
     */
    public ?string $note = null;

    /**
     * @var string Дата создания
     */
    public string $created_at;

    /**
     * @var string Дата обновления
     */
    public string $updated_at;

    /**
     * @var string|null Название задания
     */
    public ?string $title = null;

    /**
     * @var string|null Описание задания
     */
    public ?string $description = null;

    /**
     * @var string|null Флаг частоты для отображения
     */
    public ?string $frequency = null;

    /**
     * @var string|null Время из шаблона (H:i:s)
     */
    public ?string $template_due_time = null;

    /**
     * @var string|null Имя пользователя, отметившего выполнение
     */
    public ?string $completed_by_name = null;
}


