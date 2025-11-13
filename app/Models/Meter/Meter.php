<?php

namespace App\Models\Meter;

use App\Models\Model;

class Meter extends Model
{
    public int $id;
    public string $name;
    public ?string $description = null;
    public int $created_by;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $created_by_name = null;
    public ?string $created_by_login = null;
}
