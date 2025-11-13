<?php

namespace App\Models\Planting;

use App\Models\Model;

/**
 * Модель файла посадки
 * 
 * DTO (Data Transfer Object) для представления данных файла посадки.
 * Содержит метаданные загруженного файла.
 */
class PlantingFile extends Model
{
    /**
     * @var int|null ID файла
     */
    public ?int $id = null;
    
    /**
     * @var int ID посадки
     */
    public int $planting_id;
    
    /**
     * @var string Оригинальное имя файла
     */
    public string $original_name;
    
    /**
     * @var string Имя файла на сервере
     */
    public string $file_name;
    
    /**
     * @var string Путь к файлу на сервере
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
     * @var string Дата загрузки
     */
    public string $uploaded_at;
}


