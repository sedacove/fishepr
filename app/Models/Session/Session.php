<?php

namespace App\Models\Session;

use App\Models\Model;

class Session extends Model
{
    public ?int $id = null;
    public string $name;
    public int $pool_id;
    public int $planting_id;
    public string $start_date;
    public float $start_mass;
    public int $start_fish_count;
    public ?float $previous_fcr = null;
    public bool $is_completed = false;
    public ?string $end_date = null;
    public ?float $end_mass = null;
    public ?float $feed_amount = null;
    public ?float $fcr = null;
    public int $created_by;
    public string $created_at;
    public string $updated_at;

    public ?string $pool_name = null;
    public ?string $planting_name = null;
    public ?string $planting_fish_breed = null;
    public ?string $created_by_login = null;
    public ?string $created_by_name = null;
}


