<?php

namespace App\Models\Feed;

use App\Models\Model;

/**
 * DTO изображения норм кормления
 */
class FeedNormImage extends Model
{
    public ?int $id = null;
    public int $feed_id;
    public ?string $original_name = null;
    public string $file_name;
    public string $file_path;
    public ?int $file_size = null;
    public ?string $mime_type = null;
    public ?int $uploaded_by = null;
    public ?string $uploaded_at = null;
}

