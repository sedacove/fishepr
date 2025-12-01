<?php

namespace App\Models\MixedPlanting;

use App\Models\Model;

/**
 * Модель компонента микстовой посадки
 * 
 * Представляет один компонент микстовой посадки с указанием чистой посадки и процентного соотношения.
 */
class MixedPlantingComponent extends Model
{
    /**
     * @var int|null ID компонента
     */
    public ?int $id = null;

    /**
     * @var int ID микстовой посадки
     */
    public int $mixed_planting_id;

    /**
     * @var int ID чистой посадки (компонента)
     */
    public int $planting_id;

    /**
     * @var string Название чистой посадки
     */
    public ?string $planting_name = null;

    /**
     * @var float Процентное соотношение (0-100)
     */
    public float $percentage;
}

