<?php

namespace App\Models\Measurement;

use App\Models\Model;

/**
 * Модель опции бассейна для выбора
 * 
 * DTO (Data Transfer Object) для представления бассейна в списке выбора на странице измерений.
 * Содержит информацию о бассейне и активной сессии в нем.
 */
class MeasurementPoolOption extends Model
{
    /**
     * @var int ID бассейна
     */
    public int $id;
    
    /**
     * @var string Название бассейна
     */
    public string $pool_name;
    
    /**
     * @var array|null Информация об активной сессии в бассейне
     */
    public ?array $active_session = null;
    
    /**
     * @var string|null Название (альтернативное поле, используется для совместимости)
     */
    public ?string $name = null;
}


