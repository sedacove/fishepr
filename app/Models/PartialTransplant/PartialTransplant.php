<?php

namespace App\Models\PartialTransplant;

use App\Models\Model;

/**
 * Модель данных для частичной пересадки биомассы между сессиями
 * 
 * Представляет запись о частичной пересадке рыбы из одной сессии в другую.
 * Используется для отслеживания перемещения биомассы и возможности отката операций.
 */
class PartialTransplant extends Model
{
    /**
     * @var int ID записи пересадки
     */
    public int $id;

    /**
     * @var string Дата пересадки (формат: Y-m-d)
     */
    public string $transplant_date;

    /**
     * @var int ID сессии отбора (источник)
     */
    public int $source_session_id;

    /**
     * @var string Название сессии отбора
     */
    public ?string $source_session_name;

    /**
     * @var int ID сессии реципиента (получатель)
     */
    public int $recipient_session_id;

    /**
     * @var string Название сессии реципиента
     */
    public ?string $recipient_session_name;

    /**
     * @var float Вес пересаженной биомассы (кг)
     */
    public float $weight;

    /**
     * @var int Количество пересаженных особей
     */
    public int $fish_count;

    /**
     * @var bool Флаг отката пересадки (true = откат выполнен)
     */
    public bool $is_reverted;

    /**
     * @var int|null ID пользователя, выполнившего откат
     */
    public ?int $reverted_by;

    /**
     * @var string|null Дата и время отката
     */
    public ?string $reverted_at;

    /**
     * @var string|null Имя пользователя, выполнившего откат
     */
    public ?string $reverted_by_name;

    /**
     * @var int|null ID пользователя, создавшего запись
     */
    public ?int $created_by;

    /**
     * @var string|null Имя пользователя, создавшего запись
     */
    public ?string $created_by_name;

    /**
     * @var string Дата и время создания записи
     */
    public string $created_at;

    /**
     * @var string Дата и время последнего обновления записи
     */
    public string $updated_at;
}

