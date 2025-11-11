<?php

namespace App\Models\Mortality;

use App\Models\Model;

class MortalityPoint extends Model
{
    public string $day;
    public string $day_label;
    public float $total_weight = 0.0;
    public int $total_count = 0;
}


