<?php

namespace App\Models\Weighing;

use App\Models\Model;

/**
 * DTO representing a weighing (fish sampling weight) record.
 *
 * @property int $id
 * @property int $pool_id
 * @property float $weight
 * @property int $fish_count
 * @property string $recorded_at
 * @property int $created_by
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $created_by_login
 * @property string|null $created_by_name
 * @property bool $can_edit
 */
class Weighing extends Model
{
    public int $id;
    public int $pool_id;
    public float $weight;
    public int $fish_count;
    public string $recorded_at;
    public int $created_by;
    public string $created_at;
    public string $updated_at;
    public ?string $created_by_login = null;
    public ?string $created_by_name = null;
    public ?string $created_by_full_name = null;
    public bool $can_edit = false;

    /**
     * Human readable representation of recorded_at (d.m.Y H:i)
     */
    public ?string $recorded_at_display = null;
    public ?string $created_at_display = null;
    public ?string $updated_at_display = null;
}


