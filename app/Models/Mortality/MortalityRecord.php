<?php

namespace App\Models\Mortality;

use App\Models\Model;

/**
 * Модель записи падежа
 * 
 * DTO (Data Transfer Object) для представления данных записи падежа рыбы.
 * Содержит основные свойства записи падежа и дополнительную информацию о создателе.
 */
class MortalityRecord extends Model
{
    /**
     * @var int ID записи падежа
     */
    public int $id;
    
    /**
     * @var int ID бассейна
     */
    public int $pool_id;
    
    /**
     * @var float Масса падежа (кг)
     */
    public float $weight;
    
    /**
     * @var int Количество погибшей рыбы
     */
    public int $fish_count;
    
    /**
     * @var string Дата и время записи падежа
     */
    public string $recorded_at;
    
    /**
     * @var string Отформатированная дата и время записи (для отображения)
     */
    public string $recorded_at_display;
    
    /**
     * @var string Дата создания
     */
    public string $created_at;
    
    /**
     * @var int ID пользователя, создавшего запись
     */
    public int $created_by;
    
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
}


