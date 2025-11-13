<?php

namespace App\Models\Mortality;

use App\Models\Model;

class MortalityTotalsPoint extends Model
{
    public string $date;
    public string $date_label;
    public int $total_count = 0;
    public float $total_weight = 0.0;
}


