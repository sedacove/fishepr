<?php

namespace App\Models\Task;

use App\Models\Model;

class TaskFile extends Model
{
    public int $id;
    public int $task_id;
    public string $original_name;
    public int $file_size;
    public string $storage_path;
    public int $uploaded_by;
    public ?string $uploaded_by_login = null;
    public ?string $uploaded_by_name = null;
    public ?string $created_at = null;
}
