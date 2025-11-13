<?php

namespace App\Models\Planting;

use App\Models\Model;

class Planting extends Model
{
    public ?int $id = null;
    public string $name;
    public string $fish_breed;
    public ?string $hatch_date = null;
    public string $planting_date;
    public int $fish_count;
    public ?float $biomass_weight = null;
    public ?string $supplier = null;
    public ?float $price = null;
    public ?float $delivery_cost = null;
    public bool $is_archived = false;
    public int $created_by;
    public string $created_at;
    public string $updated_at;

    public ?string $created_by_login = null;
    public ?string $created_by_name = null;
    public int $files_count = 0;
}


