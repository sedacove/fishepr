<?php

namespace App\Models\Meter;

use App\Models\Model;

/**
 * Модель показания прибора учета
 * 
 * Data Transfer Object (DTO) для представления показания прибора учета.
 * Содержит только данные, без бизнес-логики.
 */
class MeterReading extends Model
{
    /**
     * @var int ID показания
     */
    public int $id;
    
    /**
     * @var int ID прибора учета
     */
    public int $meter_id;
    
    /**
     * @var float Значение показания
     */
    public float $reading_value;
    
    /**
     * @var string Дата и время записи показания (формат: 'Y-m-d H:i:s')
     */
    public string $recorded_at;
    
    /**
     * @var int ID пользователя, записавшего показание
     */
    public int $recorded_by;
    
    /**
     * @var string|null Дата и время последнего обновления
     */
    public ?string $updated_at = null;
    
    /**
     * @var string|null Логин пользователя, записавшего показание (из JOIN)
     */
    public ?string $recorded_by_login = null;
    
    /**
     * @var string|null Полное имя пользователя, записавшего показание (из JOIN)
     */
    public ?string $recorded_by_name = null;
}
