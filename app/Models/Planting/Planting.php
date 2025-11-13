<?php

namespace App\Models\Planting;

use App\Models\Model;

/**
 * Модель посадки
 * 
 * DTO (Data Transfer Object) для представления данных посадки.
 * Содержит основные свойства посадки и дополнительную информацию о создателе и количестве файлов.
 */
class Planting extends Model
{
    /**
     * @var int|null ID посадки
     */
    public ?int $id = null;
    
    /**
     * @var string Название посадки
     */
    public string $name;
    
    /**
     * @var string Порода рыбы
     */
    public string $fish_breed;
    
    /**
     * @var string|null Дата вылупления
     */
    public ?string $hatch_date = null;
    
    /**
     * @var string Дата посадки
     */
    public string $planting_date;
    
    /**
     * @var int Количество рыбы
     */
    public int $fish_count;
    
    /**
     * @var float|null Биомасса (кг)
     */
    public ?float $biomass_weight = null;
    
    /**
     * @var string|null Поставщик
     */
    public ?string $supplier = null;
    
    /**
     * @var float|null Цена
     */
    public ?float $price = null;
    
    /**
     * @var float|null Стоимость доставки
     */
    public ?float $delivery_cost = null;
    
    /**
     * @var bool Архивирована ли посадка
     */
    public bool $is_archived = false;
    
    /**
     * @var int ID пользователя, создавшего посадку
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
     * @var string|null Логин пользователя, создавшего посадку (из JOIN)
     */
    public ?string $created_by_login = null;
    
    /**
     * @var string|null Полное имя пользователя, создавшего посадку (из JOIN)
     */
    public ?string $created_by_name = null;
    
    /**
     * @var int Количество файлов посадки (из подзапроса)
     */
    public int $files_count = 0;
}


