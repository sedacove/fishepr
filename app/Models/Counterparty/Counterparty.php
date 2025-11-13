<?php

namespace App\Models\Counterparty;

use App\Models\Model;

/**
 * Модель контрагента
 * 
 * DTO (Data Transfer Object) для представления данных контрагента (клиента/покупателя).
 * Содержит основные свойства контрагента и дополнительную информацию о пользователях и документах.
 */
class Counterparty extends Model
{
    /**
     * @var int ID контрагента
     */
    public int $id;

    /**
     * @var string Название контрагента
     */
    public string $name;

    /**
     * @var string|null Описание контрагента
     */
    public ?string $description = null;

    /**
     * @var string|null ИНН (10 или 12 цифр)
     */
    public ?string $inn = null;

    /**
     * @var string|null Номер телефона в формате +7XXXXXXXXXX
     */
    public ?string $phone = null;

    /**
     * @var string|null Email контрагента
     */
    public ?string $email = null;

    /**
     * @var string|null Цвет маркера из предопределенной палитры
     */
    public ?string $color = null;

    /**
     * @var int ID пользователя, создавшего запись
     */
    public int $created_by;

    /**
     * @var int ID пользователя, последним обновившего запись
     */
    public int $updated_by;

    /**
     * @var string Дата создания (форматируется позже как d.m.Y H:i)
     */
    public string $created_at;

    /**
     * @var string Дата последнего обновления
     */
    public string $updated_at;

    /**
     * @var string|null Логин пользователя, создавшего запись (из JOIN)
     */
    public ?string $created_by_login = null;

    /**
     * @var string|null Полное имя пользователя, создавшего запись (из JOIN)
     */
    public ?string $created_by_name = null;

    /**
     * @var string|null Логин пользователя, последним обновившего запись (из JOIN)
     */
    public ?string $updated_by_login = null;

    /**
     * @var string|null Полное имя пользователя, последним обновившего запись (из JOIN)
     */
    public ?string $updated_by_name = null;

    /**
     * @var int Количество прикрепленных документов
     */
    public int $documents_count = 0;

    /**
     * @var CounterpartyDocument[]|null Список прикрепленных документов (заполняется лениво при запросе детальной информации)
     */
    public ?array $documents = null;
}
