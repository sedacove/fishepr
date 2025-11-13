<?php

namespace App\Models\Task;

use App\Models\Model;

class Task extends Model
{
    public int $id;
    public string $title;
    public ?string $description = null;
    public int $assigned_to;
    public int $created_by;
    public ?int $completed_by = null;
    public ?string $due_date = null;
    public bool $is_completed = false;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $completed_at = null;
    public ?string $assigned_to_login = null;
    public ?string $assigned_to_name = null;
    public ?string $created_by_login = null;
    public ?string $created_by_name = null;
    public ?string $completed_by_login = null;
    public ?string $completed_by_name = null;
    public int $items_count = 0;
    public int $items_completed_count = 0;
}
