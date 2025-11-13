<?php

namespace App\Models\Work;

use App\Models\Model;

class WorkPool extends Model
{
    public int $id;
    public string $name;
    public ?array $active_session = null;
}


