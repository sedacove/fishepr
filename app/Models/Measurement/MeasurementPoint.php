<?php

namespace App\Models\Measurement;

use App\Models\Model;

/**
 * Модель точки измерения для графика
 * 
 * DTO (Data Transfer Object) для представления точки данных измерения на графике.
 * Используется для построения графиков температуры и кислорода.
 */
class MeasurementPoint extends Model
{
    /**
     * @var string Дата и время измерения
     */
    public string $measured_at;
    
    /**
     * @var float|null Температура (°C)
     */
    public ?float $temperature = null;
    
    /**
     * @var float|null Кислород (мг/л)
     */
    public ?float $oxygen = null;
    
    /**
     * @var int|null ID пользователя, создавшего запись
     */
    public ?int $created_by = null;
}


