<?php

namespace App\Models\Counterparty;

use App\Models\Model;

/**
 * DTO describing a document uploaded for a counterparty.
 * Values are used when serialising responses or when auditing updates.
 */
class CounterpartyDocument extends Model
{
    /** Primary identifier */
    public int $id;

    /** Owning counterparty identifier */
    public int $counterparty_id;

    /** Original file name provided by the user */
    public string $original_name;

    /** Stored file name */
    public string $file_name;

    /** Relative path to the file in storage */
    public string $file_path;

    /** File size in bytes */
    public int $file_size;

    /** Reported MIME type */
    public ?string $mime_type = null;

    /** User identifier who uploaded the file */
    public int $uploaded_by;

    /** Upload timestamp (formatted later before output) */
    public string $uploaded_at;

    /** Login of the uploader */
    public ?string $uploaded_by_login = null;

    /** Full name of the uploader */
    public ?string $uploaded_by_name = null;
}
