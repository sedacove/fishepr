<?php

namespace App\Models\Work;

use App\Models\Model;

/**
 * Модель бассейна для страницы "Работа"
 * 
 * DTO (Data Transfer Object) для представления бассейна на странице "Работа".
 * Содержит информацию о бассейне и активной сессии в нем.
 */
class WorkPool extends Model
{
    /**
     * @var int ID бассейна
     */
    public int $id;
    
    /**
     * @var string Название бассейна
     */
    public string $name;
    
    /**
     * @var array|null Информация об активной сессии в бассейне
     */
    public ?array $active_session = null;
}


