<?php

namespace App\Models\Counterparty;

use App\Models\Model;

/**
 * Data transfer object that represents a counterparty (customer / buyer).
 *
 * This model is mainly used to pass data from the service layer to controllers
 * and eventually to JSON responses. All properties are strictly typed for
 * clarity; optional fields are nullable.
 */
class Counterparty extends Model
{
    /** Primary identifier */
    public int $id;

    /** Display name of the counterparty */
    public string $name;

    /** Optional free-form description */
    public ?string $description = null;

    /** Normalised tax identifier (10 or 12 digits) */
    public ?string $inn = null;

    /** Phone number in +7XXXXXXXXXX format */
    public ?string $phone = null;

    /** Contact e-mail */
    public ?string $email = null;

    /** Colour marker chosen from the predefined palette */
    public ?string $color = null;

    /** Author of the record */
    public int $created_by;

    /** Last user who updated the record */
    public int $updated_by;

    /** Creation timestamp (d.m.Y H:i formatted later) */
    public string $created_at;

    /** Last update timestamp */
    public string $updated_at;

    /** Login of the author */
    public ?string $created_by_login = null;

    /** Full name of the author */
    public ?string $created_by_name = null;

    /** Login of the last editor */
    public ?string $updated_by_login = null;

    /** Full name of the last editor */
    public ?string $updated_by_name = null;

    /** Number of attached documents */
    public int $documents_count = 0;

    /**
     * List of attached documents. Filled lazily when detailed information is requested.
     *
     * @var CounterpartyDocument[]|null
     */
    public ?array $documents = null;
}
