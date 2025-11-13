<?php

namespace App\Models\Counterparty;

use App\Models\Model;

/**
 * Модель документа контрагента
 * 
 * DTO (Data Transfer Object) для представления данных документа, загруженного для контрагента.
 * Содержит метаданные загруженного файла.
 */
class CounterpartyDocument extends Model
{
    /**
     * @var int ID документа
     */
    public int $id;

    /**
     * @var int ID контрагента, которому принадлежит документ
     */
    public int $counterparty_id;

    /**
     * @var string Оригинальное имя файла, предоставленное пользователем
     */
    public string $original_name;

    /**
     * @var string Имя файла на сервере
     */
    public string $file_name;

    /**
     * @var string Относительный путь к файлу в хранилище
     */
    public string $file_path;

    /**
     * @var int Размер файла (в байтах)
     */
    public int $file_size;

    /**
     * @var string|null MIME-тип файла
     */
    public ?string $mime_type = null;

    /**
     * @var int ID пользователя, загрузившего файл
     */
    public int $uploaded_by;

    /**
     * @var string Дата и время загрузки (форматируется позже перед выводом)
     */
    public string $uploaded_at;

    /**
     * @var string|null Логин пользователя, загрузившего файл (из JOIN)
     */
    public ?string $uploaded_by_login = null;

    /**
     * @var string|null Полное имя пользователя, загрузившего файл (из JOIN)
     */
    public ?string $uploaded_by_name = null;
}
