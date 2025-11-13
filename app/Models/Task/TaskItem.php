<?php

namespace App\Models\Task;

use App\Models\Model;

/**
 * Модель подзадачи
 * 
 * DTO (Data Transfer Object) для представления данных подзадачи.
 * Содержит основные свойства подзадачи и дополнительную информацию о пользователе, завершившем подзадачу.
 */
class TaskItem extends Model
{
    /**
     * @var int ID подзадачи
     */
    public int $id;
    
    /**
     * @var int ID задачи, к которой относится подзадача
     */
    public int $task_id;
    
    /**
     * @var string Название подзадачи
     */
    public string $title;
    
    /**
     * @var bool Завершена ли подзадача
     */
    public bool $is_completed = false;
    
    /**
     * @var string|null Дата завершения
     */
    public ?string $completed_at = null;
    
    /**
     * @var int|null ID пользователя, завершившего подзадачу
     */
    public ?int $completed_by = null;
    
    /**
     * @var string|null Логин пользователя, завершившего подзадачу (из JOIN)
     */
    public ?string $completed_by_login = null;
    
    /**
     * @var string|null Полное имя пользователя, завершившего подзадачу (из JOIN)
     */
    public ?string $completed_by_name = null;
    
    /**
     * @var int Порядок сортировки подзадачи
     */
    public int $sort_order = 0;
}
