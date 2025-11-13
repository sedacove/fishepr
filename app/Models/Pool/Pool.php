<?php

namespace App\Models\Pool;

use App\Models\Model;

/**
 * Модель бассейна
 * 
 * DTO (Data Transfer Object) для представления данных бассейна.
 * Содержит основные свойства бассейна и дополнительную информацию о создателе.
 */
class Pool extends Model
{
    /**
     * @var int|null ID бассейна
     */
    public ?int $id = null;
    
    /**
     * @var string Название бассейна
     */
    public string $name;
    
    /**
     * @var int Порядок сортировки
     */
    public int $sort_order = 0;
    
    /**
     * @var bool Активен ли бассейн
     */
    public bool $is_active = true;
    
    /**
     * @var int ID пользователя, создавшего бассейн
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
     * @var string|null Логин пользователя, создавшего бассейн (из JOIN)
     */
    public ?string $created_by_login = null;
    
    /**
     * @var string|null Полное имя пользователя, создавшего бассейн (из JOIN)
     */
    public ?string $created_by_name = null;
}


