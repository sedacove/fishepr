<?php

namespace App\Services;

use App\Repositories\SettingsRepository;
use PDO;
use RuntimeException;

require_once __DIR__ . '/../../includes/activity_log.php';

class SettingsService
{
    private SettingsRepository $repository;

    public function __construct(PDO $pdo)
    {
        $this->repository = new SettingsRepository($pdo);
    }

    /**
     * Получить все настройки
     * @return array<array{id:int,key:string,value:string,description:string|null,updated_at:string,updated_by:int|null,updated_by_login:string|null,updated_by_name:string|null}>
     */
    public function list(): array
    {
        $settings = $this->repository->findAll();
        
        // Преобразование дат
        foreach ($settings as &$setting) {
            $setting['updated_at'] = $setting['updated_at'] 
                ? date('d.m.Y H:i', strtotime($setting['updated_at'])) 
                : null;
        }
        
        return $settings;
    }

    /**
     * Получить значение настройки по ключу
     */
    public function get(string $key): string
    {
        $value = $this->repository->getValue($key);
        if ($value === null) {
            throw new RuntimeException('Настройка не найдена', 404);
        }
        return $value;
    }

    /**
     * Обновить настройку
     */
    public function update(string $key, string $value, int $userId): void
    {
        $oldSetting = $this->repository->findByKey($key);
        if (!$oldSetting) {
            throw new RuntimeException('Настройка не найдена', 404);
        }

        $this->repository->update($key, $value, $userId);

        // Логирование
        $changes = [
            'key' => $key,
            'old_value' => $oldSetting['value'],
            'new_value' => $value
        ];
        logActivity('update', 'setting', $oldSetting['id'], "Обновлена настройка: {$key}", $changes);
    }
}

