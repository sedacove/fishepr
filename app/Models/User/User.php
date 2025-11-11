<?php

namespace App\Models\User;

use App\Models\Model;

class User extends Model
{
    public int $id;
    public string $login;
    public string $user_type;
    public ?string $full_name = null;
    public ?string $email = null;
    public ?float $salary = null;
    public ?string $phone = null;
    public ?string $payroll_phone = null;
    public ?string $payroll_bank = null;
    public bool $is_active = true;
    public string $created_at;
    public string $updated_at;
}


