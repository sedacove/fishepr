<?php

namespace App\Models;

/**
 * Базовый класс модели
 * 
 * Предоставляет общую функциональность для всех моделей:
 * - заполнение свойств из массива
 * - преобразование в массив
 * 
 * Модели представляют собой Data Transfer Objects (DTO):
 * - содержат только данные, без бизнес-логики
 * - используются для передачи данных между слоями приложения
 */
abstract class Model
{
    /**
     * Конструктор модели
     * 
     * @param array $attributes Атрибуты для заполнения модели
     */
    public function __construct(array $attributes = [])
    {
        if ($attributes) {
            $this->fill($attributes);
        }
    }

    /**
     * Заполняет модель данными из массива
     * 
     * @param array $attributes Атрибуты для заполнения
     * @return void
     */
    public function fill(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Преобразует модель в массив
     * 
     * @return array Массив со всеми свойствами модели
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
