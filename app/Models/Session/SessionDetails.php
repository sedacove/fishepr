<?php

namespace App\Models\Session;

use App\Models\Model;

/**
 * Модель детальной информации о сессии
 * 
 * DTO (Data Transfer Object) для представления детальной информации о сессии.
 * Содержит данные сессии и связанной посадки для отображения на странице деталей сессии.
 */
class SessionDetails extends Model
{
    /**
     * @var int ID сессии
     */
    public int $id;
    
    /**
     * @var int|null ID бассейна
     */
    public ?int $pool_id = null;
    
    /**
     * @var string|null Название сессии
     */
    public ?string $name = null;
    
    /**
     * @var string|null Дата начала сессии
     */
    public ?string $start_date = null;
    
    /**
     * @var float|null Начальная масса (кг)
     */
    public ?float $start_mass = null;
    
    /**
     * @var int|null Начальное количество рыбы
     */
    public ?int $start_fish_count = null;
    
    /**
     * @var float|null Предыдущий FCR (Feed Conversion Ratio)
     */
    public ?float $previous_fcr = null;

    /**
     * @var int|null Количество кормежек в день
     */
    public ?int $daily_feedings = null;

    /**
     * @var int|null ID корма
     */
    public ?int $feed_id = null;

    /**
     * @var string|null Стратегия кормления
     */
    public ?string $feeding_strategy = null;
    
    /**
     * @var float|null Конечная масса (кг)
     */
    public ?float $end_mass = null;
    
    /**
     * @var float|null Количество корма (кг)
     */
    public ?float $feed_amount = null;
    
    /**
     * @var float|null FCR (Feed Conversion Ratio) - коэффициент конверсии корма
     */
    public ?float $fcr = null;
    
    /**
     * @var bool|null Завершена ли сессия
     */
    public ?bool $is_completed = null;
    
    /**
     * @var string|null Название бассейна (из JOIN)
     */
    public ?string $pool_name = null;
    
    /**
     * @var string|null Название посадки (из JOIN)
     */
    public ?string $planting_name = null;
    
    /**
     * @var string|null Порода рыбы (из JOIN)
     */
    public ?string $fish_breed = null;
    
    /**
     * @var string|null Дата вылупления (из JOIN)
     */
    public ?string $hatch_date = null;
    
    /**
     * @var string|null Дата посадки (из JOIN)
     */
    public ?string $planting_planting_date = null;
    
    /**
     * @var int|null Количество рыбы в посадке (из JOIN)
     */
    public ?int $planting_quantity = null;
    
    /**
     * @var float|null Биомасса посадки (кг) (из JOIN)
     */
    public ?float $planting_biomass_weight = null;
    
    /**
     * @var string|null Поставщик (из JOIN)
     */
    public ?string $supplier = null;
    
    /**
     * @var float|null Цена посадки (из JOIN)
     */
    public ?float $planting_price = null;
    
    /**
     * @var float|null Стоимость доставки (из JOIN)
     */
    public ?float $delivery_cost = null;

    /**
     * @var string|null Название корма
     */
    public ?string $feed_name = null;
}


