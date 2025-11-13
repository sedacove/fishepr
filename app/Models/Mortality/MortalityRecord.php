<?php

namespace App\Models\Mortality;

use App\Models\Model;

class MortalityRecord extends Model
{
    public int $id;
    public int $pool_id;
    public float $weight;
    public int $fish_count;
    public string $recorded_at;
    public string $recorded_at_display;
    public string $created_at;
    public int $created_by;
    public ?string $created_by_login = null;
    public ?string $created_by_name = null;
    public ?string $created_by_full_name = null;
    public bool $can_edit = false;
}


