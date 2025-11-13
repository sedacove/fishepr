<?php

namespace App\Models\Mortality;

use App\Models\Model;

/**
 * Модель серии падежа по бассейну для графика
 * 
 * DTO (Data Transfer Object) для представления серии данных падежа по бассейну на графике.
 * Используется для построения графиков с несколькими сериями (по бассейнам).
 */
class MortalityPoolSeries extends Model
{
    /**
     * @var int ID бассейна
     */
    public int $pool_id;
    
    /**
     * @var string Название бассейна
     */
    public string $pool_name;
    
    /**
     * @var array Массив точек данных падежа (MortalityPoint[])
     */
    public array $series = [];
    
    /**
     * @var int Общее количество погибшей рыбы за период
     */
    public int $total_count = 0;
    
    /**
     * @var float Общая масса падежа за период (кг)
     */
    public float $total_weight = 0.0;
}


