<?php

namespace App\Models\Meter;

use App\Models\Model;

/**
 * Модель прибора учета
 * 
 * Data Transfer Object (DTO) для представления прибора учета.
 * Содержит только данные, без бизнес-логики.
 */
class Meter extends Model
{
    /**
     * @var int ID прибора
     */
    public int $id;
    
    /**
     * @var string Название прибора
     */
    public string $name;
    
    /**
     * @var string|null Описание прибора
     */
    public ?string $description = null;
    
    /**
     * @var int ID пользователя, создавшего прибор
     */
    public int $created_by;
    
    /**
     * @var string|null Дата и время создания
     */
    public ?string $created_at = null;
    
    /**
     * @var string|null Дата и время последнего обновления
     */
    public ?string $updated_at = null;
    
    /**
     * @var string|null Полное имя пользователя, создавшего прибор (из JOIN)
     */
    public ?string $created_by_name = null;
    
    /**
     * @var string|null Логин пользователя, создавшего прибор (из JOIN)
     */
    public ?string $created_by_login = null;
}
