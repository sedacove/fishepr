<?php

namespace App\Models\Session;

use App\Models\Model;

class SessionDetails extends Model
{
    public int $id;
    public ?int $pool_id = null;
    public ?string $name = null;
    public ?string $start_date = null;
    public ?float $start_mass = null;
    public ?int $start_fish_count = null;
    public ?float $previous_fcr = null;
    public ?float $end_mass = null;
    public ?float $feed_amount = null;
    public ?float $fcr = null;
    public ?bool $is_completed = null;
    public ?string $pool_name = null;
    public ?string $planting_name = null;
    public ?string $fish_breed = null;
    public ?string $hatch_date = null;
    public ?string $planting_planting_date = null;
    public ?int $planting_quantity = null;
    public ?float $planting_biomass_weight = null;
    public ?string $supplier = null;
    public ?float $planting_price = null;
    public ?float $delivery_cost = null;
}


