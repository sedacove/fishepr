<?php

namespace App\Models\Session;

use App\Models\Model;

/**
 * Модель сессии
 * 
 * DTO (Data Transfer Object) для представления данных сессии.
 * Содержит основные свойства сессии и дополнительную информацию о связанных сущностях.
 */
class Session extends Model
{
    /**
     * @var int|null ID сессии
     */
    public ?int $id = null;
    
    /**
     * @var string Название сессии
     */
    public string $name;
    
    /**
     * @var int ID бассейна
     */
    public int $pool_id;
    
    /**
     * @var int ID посадки
     */
    public int $planting_id;
    
    /**
     * @var string Дата начала сессии
     */
    public string $start_date;
    
    /**
     * @var float Начальная масса (кг)
     */
    public float $start_mass;
    
    /**
     * @var int Начальное количество рыбы
     */
    public int $start_fish_count;
    
    /**
     * @var float|null Предыдущий FCR (Feed Conversion Ratio)
     */
    public ?float $previous_fcr = null;

    /**
     * @var int Количество кормежек в день
     */
    public int $daily_feedings = 3;

    /**
     * @var int|null ID выбранного корма
     */
    public ?int $feed_id = null;

    /**
     * @var string Стратегия кормления
     */
    public string $feeding_strategy = 'normal';
    
    /**
     * @var bool Завершена ли сессия
     */
    public bool $is_completed = false;
    
    /**
     * @var string|null Дата окончания сессии
     */
    public ?string $end_date = null;
    
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
     * @var int ID пользователя, создавшего сессию
     */
    public int $created_by;
    
    /**
     * @var string Дата создания
     */
    public string $created_at;
    
    /**
     * @var string Дата обновления
     */
    public string $updated_at;

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
    public ?string $planting_fish_breed = null;

    /**
     * @var string|null Название корма
     */
    public ?string $feed_name = null;
    
    /**
     * @var string|null Логин пользователя, создавшего сессию (из JOIN)
     */
    public ?string $created_by_login = null;
    
    /**
     * @var string|null Полное имя пользователя, создавшего сессию (из JOIN)
     */
    public ?string $created_by_name = null;
}


