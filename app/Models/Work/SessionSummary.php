<?php

namespace App\Models\Work;

use App\Models\Model;

/**
 * Модель сводки сессии для страницы "Работа"
 * 
 * DTO (Data Transfer Object) для представления сводной информации о сессии.
 * Содержит основные данные сессии, информацию о последних измерениях, взвешиваниях и падеже.
 */
class SessionSummary extends Model
{
    /**
     * @var int ID сессии
     */
    public int $id;
    
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
     * @var float|null Средний вес рыбы (кг)
     */
    public ?float $avg_fish_weight = null;
    
    /**
     * @var string|null Источник данных для среднего веса
     */
    public ?string $avg_weight_source = null;
    
    /**
     * @var string|null Название посадки
     */
    public ?string $planting_name = null;
    
    /**
     * @var string|null Порода рыбы
     */
    public ?string $planting_fish_breed = null;

    /**
     * @var int|null Количество кормежек в день
     */
    public ?int $daily_feedings = null;

    /**
     * @var int|null ID корма
     */
    public ?int $feed_id = null;

    /**
     * @var string|null Название корма
     */
    public ?string $feed_name = null;

    /**
     * @var string|null Стратегия кормления (econom/normal/growth)
     */
    public ?string $feeding_strategy = null;
    
    /**
     * @var string|null Дата и время последнего взвешивания
     */
    public ?string $last_weighing_at = null;
    
    /**
     * @var int|null Количество минут с последнего взвешивания
     */
    public ?int $last_weighing_diff_minutes = null;
    
    /**
     * @var string|null Отформатированная разница времени с последнего взвешивания
     */
    public ?string $last_weighing_diff_label = null;
    
    /**
     * @var bool Флаг предупреждения о давности взвешивания
     */
    public bool $weighing_warning = false;
    
    /**
     * @var string|null Дата и время последнего измерения
     */
    public ?string $last_measurement_at = null;
    
    /**
     * @var int|null Количество минут с последнего измерения
     */
    public ?int $last_measurement_diff_minutes = null;
    
    /**
     * @var string|null Отформатированная разница времени с последнего измерения
     */
    public ?string $last_measurement_diff_label = null;
    
    /**
     * @var bool Флаг предупреждения о давности измерения
     */
    public bool $measurement_warning = false;
    
    /**
     * @var string|null Текст предупреждения об измерении
     */
    public ?string $measurement_warning_label = null;
    
    /**
     * @var array|null Данные последнего измерения
     */
    public ?array $last_measurement = null;
    
    /**
     * @var array|null Данные предыдущего измерения
     */
    public ?array $previous_measurement = null;
    
    /**
     * @var array|null Текущая нагрузка на бассейн
     */
    public ?array $current_load = null;
    
    /**
     * @var array|null Данные о падеже за последние часы
     */
    public ?array $mortality_last_hours = null;

    /**
     * @var array|null Расчет нормы кормления
     */
    public ?array $feeding_plan = null;

    /**
     * @var float|null Коэффициент фактического внесения корма (кг на 100 кг биомассы)
     */
    public ?float $feed_ratio = null;
}


