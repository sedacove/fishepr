<?php

namespace App\Models\Task;

use App\Models\Model;

/**
 * Модель задачи
 * 
 * DTO (Data Transfer Object) для представления данных задачи.
 * Содержит основные свойства задачи и дополнительную информацию о пользователях и подзадачах.
 */
class Task extends Model
{
    /**
     * @var int ID задачи
     */
    public int $id;
    
    /**
     * @var string Название задачи
     */
    public string $title;
    
    /**
     * @var string|null Описание задачи
     */
    public ?string $description = null;
    
    /**
     * @var int ID пользователя, которому назначена задача
     */
    public int $assigned_to;
    
    /**
     * @var int ID пользователя, создавшего задачу
     */
    public int $created_by;
    
    /**
     * @var int|null ID пользователя, завершившего задачу
     */
    public ?int $completed_by = null;
    
    /**
     * @var string|null Срок выполнения задачи
     */
    public ?string $due_date = null;
    
    /**
     * @var bool Завершена ли задача
     */
    public bool $is_completed = false;
    
    /**
     * @var string|null Дата создания
     */
    public ?string $created_at = null;
    
    /**
     * @var string|null Дата обновления
     */
    public ?string $updated_at = null;
    
    /**
     * @var string|null Дата завершения
     */
    public ?string $completed_at = null;
    
    /**
     * @var string|null Логин пользователя, которому назначена задача (из JOIN)
     */
    public ?string $assigned_to_login = null;
    
    /**
     * @var string|null Полное имя пользователя, которому назначена задача (из JOIN)
     */
    public ?string $assigned_to_name = null;
    
    /**
     * @var string|null Логин пользователя, создавшего задачу (из JOIN)
     */
    public ?string $created_by_login = null;
    
    /**
     * @var string|null Полное имя пользователя, создавшего задачу (из JOIN)
     */
    public ?string $created_by_name = null;
    
    /**
     * @var string|null Логин пользователя, завершившего задачу (из JOIN)
     */
    public ?string $completed_by_login = null;
    
    /**
     * @var string|null Полное имя пользователя, завершившего задачу (из JOIN)
     */
    public ?string $completed_by_name = null;
    
    /**
     * @var int Общее количество подзадач
     */
    public int $items_count = 0;
    
    /**
     * @var int Количество завершенных подзадач
     */
    public int $items_completed_count = 0;
}
