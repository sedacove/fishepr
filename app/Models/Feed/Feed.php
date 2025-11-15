<?php

namespace App\Models\Feed;

use App\Models\Model;

/**
 * DTO модели корма
 */
class Feed extends Model
{
    public ?int $id = null;
    public string $name;
    public ?string $description = null;
    public ?string $granule = null;
    public ?string $formula_econom = null;
    public ?string $formula_normal = null;
    public ?string $formula_growth = null;
    public ?string $manufacturer = null;
    public ?int $created_by = null;
    public ?int $updated_by = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    /** @var FeedNormImage[]|array<int,FeedNormImage> */
    public array $norm_images = [];
}

