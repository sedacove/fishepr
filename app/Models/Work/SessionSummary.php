<?php

namespace App\Models\Work;

use App\Models\Model;

class SessionSummary extends Model
{
    public int $id;
    public ?string $name = null;
    public ?string $start_date = null;
    public ?float $start_mass = null;
    public ?int $start_fish_count = null;
    public ?float $avg_fish_weight = null;
    public ?string $avg_weight_source = null;
    public ?string $planting_name = null;
    public ?string $planting_fish_breed = null;
    public ?string $last_weighing_at = null;
    public ?int $last_weighing_diff_minutes = null;
    public ?string $last_weighing_diff_label = null;
    public bool $weighing_warning = false;
    public ?string $last_measurement_at = null;
    public ?int $last_measurement_diff_minutes = null;
    public ?string $last_measurement_diff_label = null;
    public bool $measurement_warning = false;
    public ?string $measurement_warning_label = null;
    public ?array $last_measurement = null;
    public ?array $previous_measurement = null;
    public ?array $current_load = null;
    public ?array $mortality_last_hours = null;
}


