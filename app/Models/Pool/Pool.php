<?php

namespace App\Models\Pool;

use App\Models\Model;

class Pool extends Model
{
    public ?int $id = null;
    public string $name;
    public int $sort_order = 0;
    public bool $is_active = true;
    public int $created_by;
    public string $created_at;
    public string $updated_at;

    public ?string $created_by_login = null;
    public ?string $created_by_name = null;
}


