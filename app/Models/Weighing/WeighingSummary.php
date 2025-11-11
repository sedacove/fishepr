<?php

namespace App\Models\Weighing;

use App\Models\Model;

class WeighingSummary extends Model
{
    public string $recorded_at;
    public float $weight;
    public int $fish_count;
    public ?float $avg_weight = null;
}


