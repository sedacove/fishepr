<?php

namespace App\Models\Harvest;

use App\Models\Model;

/**
 * Модель улова
 * 
 * DTO (Data Transfer Object) для представления данных улова.
 * Содержит основные свойства улова и дополнительную информацию о контрагенте и создателе.
 */
class Harvest extends Model
{
    /**
     * @var int ID улова
     */
    public int $id;
    
    /**
     * @var int ID бассейна
     */
    public int $pool_id;
    
    /**
     * @var float Масса улова (кг)
     */
    public float $weight;
    
    /**
     * @var int Количество рыбы
     */
    public int $fish_count;
    
    /**
     * @var string Дата и время записи улова
     */
    public string $recorded_at;
    
    /**
     * @var int ID пользователя, создавшего запись
     */
    public int $created_by;
    
    /**
     * @var string Дата создания
     */
    public string $created_at;
    
    /**
     * @var string Дата обновления
     */
    public string $updated_at;
    
    /**
     * @var int|null ID контрагента (покупателя)
     */
    public ?int $counterparty_id = null;
    
    /**
     * @var string|null Название контрагента (из JOIN)
     */
    public ?string $counterparty_name = null;
    
    /**
     * @var string|null Цвет маркера контрагента (из JOIN)
     */
    public ?string $counterparty_color = null;
    
    /**
     * @var string|null Логин пользователя, создавшего запись (из JOIN)
     */
    public ?string $created_by_login = null;
    
    /**
     * @var string|null Полное имя пользователя, создавшего запись (из JOIN)
     */
    public ?string $created_by_name = null;
    
    /**
     * @var string|null Полное имя пользователя (альтернативное поле)
     */
    public ?string $created_by_full_name = null;
    
    /**
     * @var bool Может ли текущий пользователь редактировать запись
     */
    public bool $can_edit = false;

    /**
     * @var string|null Отформатированная дата и время записи (для отображения)
     */
    public ?string $recorded_at_display = null;
    
    /**
     * @var string|null Отформатированная дата создания (для отображения)
     */
    public ?string $created_at_display = null;
    
    /**
     * @var string|null Отформатированная дата обновления (для отображения)
     */
    public ?string $updated_at_display = null;
}


