<?php

namespace App\Models\News;

use App\Models\Model;

class News extends Model
{
    public int $id;
    public string $title;
    public string $content;
    public string $published_at;
    public int $author_id;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $author_full_name = null;
    public ?string $author_login = null;
}
