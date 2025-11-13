<?php

namespace App\Models\Meter;

use App\Models\Model;

class MeterReading extends Model
{
    public int $id;
    public int $meter_id;
    public float $reading_value;
    public string $recorded_at;
    public int $recorded_by;
    public ?string $updated_at = null;
    public ?string $recorded_by_login = null;
    public ?string $recorded_by_name = null;
}
