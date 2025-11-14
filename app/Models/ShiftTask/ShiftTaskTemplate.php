<?php

namespace App\Models\ShiftTask;

use App\Models\Model;

/**
 * Модель шаблона задания смены
 *
 * DTO для представления шаблонов повторяющихся заданий.
 */
class ShiftTaskTemplate extends Model
{
    /**
     * @var int|null ID шаблона
     */
    public ?int $id = null;

    /**
     * @var string Название задания
     */
    public string $title;

    /**
     * @var string|null Описание задания
     */
    public ?string $description = null;

    /**
     * @var string Частота выполнения (daily, weekly, biweekly, monthly)
     */
    public string $frequency = 'daily';

    /**
     * @var string Дата, с которой начинается повторение (Y-m-d)
     */
    public string $start_date;

    /**
     * @var int|null День недели (0 = воскресенье ... 6 = суббота) для еженедельных заданий
     */
    public ?int $week_day = null;

    /**
     * @var int|null День месяца для ежемесячных заданий
     */
    public ?int $day_of_month = null;

    /**
     * @var string Время, к которому задача должна быть выполнена (H:i:s)
     */
    public string $due_time;

    /**
     * @var bool Активен ли шаблон
     */
    public bool $is_active = true;

    /**
     * @var int ID пользователя, создавшего шаблон
     */
    public int $created_by;

    /**
     * @var int|null ID пользователя, последним изменившего шаблон
     */
    public ?int $updated_by = null;

    /**
     * @var string Дата создания
     */
    public string $created_at;

    /**
     * @var string Дата обновления
     */
    public string $updated_at;

    /**
     * @var string|null Имя пользователя, создавшего шаблон (для выводов)
     */
    public ?string $created_by_name = null;

    /**
     * @var string|null Имя пользователя, обновившего шаблон (для выводов)
     */
    public ?string $updated_by_name = null;
}


