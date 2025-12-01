<?php

namespace App\Models\MixedPlanting;

use App\Models\Model;

/**
 * Модель микстовой посадки
 * 
 * Представляет микстовую посадку, состоящую из нескольких чистых посадок в определенных пропорциях.
 */
class MixedPlanting extends Model
{
    /**
     * @var int|null ID микстовой посадки
     */
    public ?int $id = null;

    /**
     * @var string Название микстовой посадки
     */
    public string $name;

    /**
     * @var string|null Основная порода рыбы (если все компоненты одной породы)
     */
    public ?string $fish_breed = null;

    /**
     * @var int|null ID пользователя, создавшего микстовую посадку
     */
    public ?int $created_by = null;

    /**
     * @var string|null Имя пользователя, создавшего микстовую посадку
     */
    public ?string $created_by_name = null;

    /**
     * @var string Дата создания
     */
    public string $created_at;

    /**
     * @var string Дата обновления
     */
    public string $updated_at;

    /**
     * @var array Компоненты микстовой посадки (массив MixedPlantingComponent)
     */
    public array $components = [];
}

