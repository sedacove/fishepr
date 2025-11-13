<?php

namespace App\Models\Harvest;

use App\Models\Model;

class HarvestSummary extends Model
{
    public string $recorded_at;
    public float $weight;
    public int $fish_count;
    public ?int $counterparty_id = null;
    public ?string $counterparty_name = null;
    public ?string $counterparty_color = null;
}


