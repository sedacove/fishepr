<?php

namespace App\Models\Weighing;

use App\Models\Model;

/**
 * Модель взвешивания
 * 
 * DTO (Data Transfer Object) для представления данных взвешивания (выборочного взвешивания рыбы).
 * Содержит основные свойства взвешивания и дополнительную информацию о создателе.
 */
class Weighing extends Model
{
    /**
     * @var int ID взвешивания
     */
    public int $id;
    
    /**
     * @var int ID бассейна
     */
    public int $pool_id;
    
    /**
     * @var float Масса взвешивания (кг)
     */
    public float $weight;
    
    /**
     * @var int Количество рыбы
     */
    public int $fish_count;
    
    /**
     * @var string Дата и время записи взвешивания
     */
    public string $recorded_at;
    
    /**
     * @var int ID пользователя, создавшего запись
     */
    public int $created_by;
    
    /**
     * @var string Дата создания
     */
    public string $created_at;
    
    /**
     * @var string Дата обновления
     */
    public string $updated_at;
    
    /**
     * @var string|null Логин пользователя, создавшего запись (из JOIN)
     */
    public ?string $created_by_login = null;
    
    /**
     * @var string|null Полное имя пользователя, создавшего запись (из JOIN)
     */
    public ?string $created_by_name = null;
    
    /**
     * @var string|null Полное имя пользователя (альтернативное поле)
     */
    public ?string $created_by_full_name = null;
    
    /**
     * @var bool Может ли текущий пользователь редактировать запись
     */
    public bool $can_edit = false;

    /**
     * @var string|null Отформатированная дата и время записи (для отображения, формат: d.m.Y H:i)
     */
    public ?string $recorded_at_display = null;
    
    /**
     * @var string|null Отформатированная дата создания (для отображения)
     */
    public ?string $created_at_display = null;
    
    /**
     * @var string|null Отформатированная дата обновления (для отображения)
     */
    public ?string $updated_at_display = null;
}


