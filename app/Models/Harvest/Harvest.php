<?php

namespace App\Models\Harvest;

use App\Models\Model;

/**
 * DTO representing a single harvest (fish extraction) record.
 *
 * @property int $id
 * @property int $pool_id
 * @property float $weight
 * @property int $fish_count
 * @property string $recorded_at
 * @property int $created_by
 * @property string $created_at
 * @property string $updated_at
 * @property int|null $counterparty_id
 * @property string|null $counterparty_name
 * @property string|null $counterparty_color
 * @property string|null $created_by_login
 * @property string|null $created_by_name
 * @property bool $can_edit
 */
class Harvest extends Model
{
    public int $id;
    public int $pool_id;
    public float $weight;
    public int $fish_count;
    public string $recorded_at;
    public int $created_by;
    public string $created_at;
    public string $updated_at;
    public ?int $counterparty_id = null;
    public ?string $counterparty_name = null;
    public ?string $counterparty_color = null;
    public ?string $created_by_login = null;
    public ?string $created_by_name = null;
    public ?string $created_by_full_name = null;
    public bool $can_edit = false;

    /**
     * Computed field used by front-end. Not stored in DB.
     */
    public ?string $recorded_at_display = null;
    public ?string $created_at_display = null;
    public ?string $updated_at_display = null;
}


