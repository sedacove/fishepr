<?php

namespace App\Models\Measurement;

use App\Models\Model;

/**
 * Модель записи измерения
 * 
 * DTO (Data Transfer Object) для представления данных измерения температуры и кислорода.
 * Содержит основные свойства измерения и дополнительную информацию о создателе и статусах.
 */
class MeasurementRecord extends Model
{
    /**
     * @var int ID записи измерения
     */
    public int $id;
    
    /**
     * @var int ID бассейна
     */
    public int $pool_id;
    
    /**
     * @var float Температура (°C)
     */
    public float $temperature;
    
    /**
     * @var float Кислород (мг/л)
     */
    public float $oxygen;
    
    /**
     * @var string Дата и время измерения
     */
    public string $measured_at;
    
    /**
     * @var string Отформатированная дата и время измерения (для отображения)
     */
    public string $measured_at_display;
    
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
    
    /**
     * @var string|null Статус температуры (норма/предупреждение/критично)
     */
    public ?string $temperature_stratum = null;
    
    /**
     * @var string|null Статус кислорода (норма/предупреждение/критично)
     */
    public ?string $oxygen_stratum = null;
}


