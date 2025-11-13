<?php

namespace App\Models\News;

use App\Models\Model;

/**
 * Модель новости
 * 
 * DTO (Data Transfer Object) для представления данных новости.
 * Содержит основные свойства новости и дополнительную информацию об авторе.
 */
class News extends Model
{
    /**
     * @var int ID новости
     */
    public int $id;
    
    /**
     * @var string Заголовок новости
     */
    public string $title;
    
    /**
     * @var string Содержание новости (HTML)
     */
    public string $content;
    
    /**
     * @var string Дата публикации
     */
    public string $published_at;
    
    /**
     * @var int ID автора новости
     */
    public int $author_id;
    
    /**
     * @var string|null Дата создания
     */
    public ?string $created_at = null;
    
    /**
     * @var string|null Дата обновления
     */
    public ?string $updated_at = null;
    
    /**
     * @var string|null Полное имя автора (из JOIN)
     */
    public ?string $author_full_name = null;
    
    /**
     * @var string|null Логин автора (из JOIN)
     */
    public ?string $author_login = null;
}
