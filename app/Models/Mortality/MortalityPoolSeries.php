<?php

namespace App\Models\Mortality;

use App\Models\Model;

class MortalityPoolSeries extends Model
{
    public int $pool_id;
    public string $pool_name;
    public array $series = [];
    public int $total_count = 0;
    public float $total_weight = 0.0;
}


