<?php

namespace App\Models\Mortality;

use App\Models\Model;

/**
 * Модель точки падежа для графика
 * 
 * DTO (Data Transfer Object) для представления точки данных падежа на графике.
 * Используется для построения графиков падежа по дням.
 */
class MortalityPoint extends Model
{
    /**
     * @var string Дата (формат: Y-m-d)
     */
    public string $day;
    
    /**
     * @var string Отформатированная дата для отображения на графике
     */
    public string $day_label;
    
    /**
     * @var float Общая масса падежа за день (кг)
     */
    public float $total_weight = 0.0;
    
    /**
     * @var int Общее количество погибшей рыбы за день
     */
    public int $total_count = 0;
}


