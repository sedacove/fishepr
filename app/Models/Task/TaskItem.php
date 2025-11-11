<?php

namespace App\Models\Task;

use App\Models\Model;

class TaskItem extends Model
{
    public int $id;
    public int $task_id;
    public string $title;
    public bool $is_completed = false;
    public ?string $completed_at = null;
    public ?int $completed_by = null;
    public ?string $completed_by_login = null;
    public ?string $completed_by_name = null;
    public int $sort_order = 0;
}
