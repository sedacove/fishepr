<?php

namespace App\Models\Planting;

use App\Models\Model;

class PlantingFile extends Model
{
    public ?int $id = null;
    public int $planting_id;
    public string $original_name;
    public string $file_name;
    public string $file_path;
    public int $file_size;
    public ?string $mime_type = null;
    public int $uploaded_by;
    public string $uploaded_at;
}


