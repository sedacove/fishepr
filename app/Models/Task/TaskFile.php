<?php

namespace App\Models\Task;

use App\Models\Model;

/**
 * Модель файла задачи
 * 
 * DTO (Data Transfer Object) для представления данных файла, прикрепленного к задаче.
 * Содержит метаданные загруженного файла.
 */
class TaskFile extends Model
{
    /**
     * @var int ID файла
     */
    public int $id;
    
    /**
     * @var int ID задачи, к которой прикреплен файл
     */
    public int $task_id;
    
    /**
     * @var string Оригинальное имя файла
     */
    public string $original_name;
    
    /**
     * @var int Размер файла (в байтах)
     */
    public int $file_size;
    
    /**
     * @var string Путь к файлу в хранилище
     */
    public string $storage_path;
    
    /**
     * @var int ID пользователя, загрузившего файл
     */
    public int $uploaded_by;
    
    /**
     * @var string|null Логин пользователя, загрузившего файл (из JOIN)
     */
    public ?string $uploaded_by_login = null;
    
    /**
     * @var string|null Полное имя пользователя, загрузившего файл (из JOIN)
     */
    public ?string $uploaded_by_name = null;
    
    /**
     * @var string|null Дата создания
     */
    public ?string $created_at = null;
}
