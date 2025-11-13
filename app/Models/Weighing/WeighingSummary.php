<?php

namespace App\Models\Weighing;

use App\Models\Model;

/**
 * Модель сводки взвешивания
 * 
 * DTO (Data Transfer Object) для представления сводной информации о взвешивании.
 * Используется для отображения списка взвешиваний с расчетом среднего веса.
 */
class WeighingSummary extends Model
{
    /**
     * @var string Дата и время записи взвешивания
     */
    public string $recorded_at;
    
    /**
     * @var float Масса взвешивания (кг)
     */
    public float $weight;
    
    /**
     * @var int Количество рыбы
     */
    public int $fish_count;
    
    /**
     * @var float|null Средний вес рыбы (кг)
     */
    public ?float $avg_weight = null;
}


