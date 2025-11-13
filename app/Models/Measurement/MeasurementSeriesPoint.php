<?php

namespace App\Models\Measurement;

use App\Models\Model;

class MeasurementSeriesPoint extends Model
{
    public int $id;
    public int $pool_id;
    public ?string $pool_name = null;
    public float $value;
    public string $measured_at;
    public string $label;
    public ?string $stratum = null;
}


