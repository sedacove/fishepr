<?php

namespace App\Models\Measurement;

use App\Models\Model;

/**
 * Модель точки серии измерений для графика
 * 
 * DTO (Data Transfer Object) для представления точки данных в серии измерений на графике.
 * Используется для построения графиков с несколькими сериями (по бассейнам).
 */
class MeasurementSeriesPoint extends Model
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
     * @var string|null Название бассейна (из JOIN)
     */
    public ?string $pool_name = null;
    
    /**
     * @var float Значение измерения (температура или кислород)
     */
    public float $value;
    
    /**
     * @var string Дата и время измерения
     */
    public string $measured_at;
    
    /**
     * @var string Метка для отображения на графике
     */
    public string $label;
    
    /**
     * @var string|null Статус измерения (норма/предупреждение/критично)
     */
    public ?string $stratum = null;
}


