<?php

namespace App\Models\Mortality;

use App\Models\Model;

/**
 * Модель точки общих итогов падежа для графика
 * 
 * DTO (Data Transfer Object) для представления точки данных общих итогов падежа на графике.
 * Используется для построения графиков общих итогов падежа по всем бассейнам.
 */
class MortalityTotalsPoint extends Model
{
    /**
     * @var string Дата (формат: Y-m-d)
     */
    public string $date;
    
    /**
     * @var string Отформатированная дата для отображения на графике
     */
    public string $date_label;
    
    /**
     * @var int Общее количество погибшей рыбы за день по всем бассейнам
     */
    public int $total_count = 0;
    
    /**
     * @var float Общая масса падежа за день по всем бассейнам (кг)
     */
    public float $total_weight = 0.0;
}


