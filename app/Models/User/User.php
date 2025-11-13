<?php

namespace App\Models\User;

use App\Models\Model;

/**
 * Модель пользователя
 * 
 * Data Transfer Object (DTO) для представления пользователя.
 * Содержит только данные, без бизнес-логики.
 */
class User extends Model
{
    /**
     * @var int ID пользователя
     */
    public int $id;
    
    /**
     * @var string Логин пользователя
     */
    public string $login;
    
    /**
     * @var string Тип пользователя ('admin' или 'user')
     */
    public string $user_type;
    
    /**
     * @var string|null Полное имя пользователя
     */
    public ?string $full_name = null;
    
    /**
     * @var string|null Email пользователя
     */
    public ?string $email = null;
    
    /**
     * @var float|null Зарплата пользователя
     */
    public ?float $salary = null;
    
    /**
     * @var string|null Телефон пользователя
     */
    public ?string $phone = null;
    
    /**
     * @var string|null Телефон для расчета зарплаты
     */
    public ?string $payroll_phone = null;
    
    /**
     * @var string|null Банк для расчета зарплаты
     */
    public ?string $payroll_bank = null;
    
    /**
     * @var bool Статус активности пользователя
     */
    public bool $is_active = true;
    
    /**
     * @var string Дата и время создания
     */
    public string $created_at;
    
    /**
     * @var string Дата и время последнего обновления
     */
    public string $updated_at;
}


