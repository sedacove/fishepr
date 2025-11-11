<?php

namespace App\Models\Measurement;

use App\Models\Model;

class MeasurementRecord extends Model
{
    public int $id;
    public int $pool_id;
    public float $temperature;
    public float $oxygen;
    public string $measured_at;
    public string $measured_at_display;
    public string $created_at;
    public int $created_by;
    public ?string $created_by_login = null;
    public ?string $created_by_name = null;
    public ?string $created_by_full_name = null;
    public bool $can_edit = false;
    public ?string $temperature_stratum = null;
    public ?string $oxygen_stratum = null;
}


