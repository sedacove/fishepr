<?php

namespace App\Repositories;

use PDO;

class SettingsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Получить все настройки
     * @return array<array{id:int,key:string,value:string,description:string|null,updated_at:string,updated_by:int|null,updated_by_login:string|null,updated_by_name:string|null}>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                s.*,
                u.login as updated_by_login,
                u.full_name as updated_by_name
            FROM settings s
            LEFT JOIN users u ON s.updated_by = u.id
            ORDER BY s.key ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Найти настройку по ключу
     * @return array{id:int,key:string,value:string,description:string|null,updated_at:string,updated_by:int|null}|null
     */
    public function findByKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Получить значение настройки по ключу
     */
    public function getValue(string $key): ?string
    {
        $setting = $this->findByKey($key);
        return $setting ? $setting['value'] : null;
    }

    /**
     * Обновить настройку
     */
    public function update(string $key, string $value, int $updatedBy): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE settings SET
                value = ?,
                updated_by = ?
            WHERE `key` = ?
        ");
        $stmt->execute([$value, $updatedBy, $key]);
    }
}

