<?php

namespace App\Models\Measurement;

use App\Models\Model;

class MeasurementPoint extends Model
{
    public string $measured_at;
    public ?float $temperature = null;
    public ?float $oxygen = null;
    public ?int $created_by = null;
}


