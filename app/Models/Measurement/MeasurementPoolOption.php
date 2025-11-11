<?php

namespace App\Models\Measurement;

use App\Models\Model;

class MeasurementPoolOption extends Model
{
    public int $id;
    public string $pool_name;
    public ?array $active_session = null;
    public ?string $name = null;
}


