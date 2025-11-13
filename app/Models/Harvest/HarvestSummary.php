<?php

namespace App\Models\Harvest;

use App\Models\Model;

/**
 * Модель сводки улова
 * 
 * DTO (Data Transfer Object) для представления сводной информации об улове.
 * Используется для отображения списка уловов с информацией о контрагенте.
 */
class HarvestSummary extends Model
{
    /**
     * @var string Дата и время записи улова
     */
    public string $recorded_at;
    
    /**
     * @var float Масса улова (кг)
     */
    public float $weight;
    
    /**
     * @var int Количество рыбы
     */
    public int $fish_count;
    
    /**
     * @var int|null ID контрагента (покупателя)
     */
    public ?int $counterparty_id = null;
    
    /**
     * @var string|null Название контрагента (из JOIN)
     */
    public ?string $counterparty_name = null;
    
    /**
     * @var string|null Цвет маркера контрагента (из JOIN)
     */
    public ?string $counterparty_color = null;
}


