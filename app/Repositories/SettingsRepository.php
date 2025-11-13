<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с настройками системы
 * 
 * Выполняет SQL запросы к таблице settings:
 * - получение списка всех настроек
 * - поиск настройки по ключу
 * - получение значения настройки
 * - обновление настройки
 */
class SettingsRepository
{
    /**
     * @var PDO Подключение к базе данных
     */
    private PDO $pdo;

    /**
     * Конструктор репозитория
     * 
     * @param PDO $pdo Подключение к базе данных
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Получает все настройки системы
     * 
     * @return array<array{id:int,key:string,value:string,description:string|null,updated_at:string,updated_by:int|null,updated_by_login:string|null,updated_by_name:string|null}> Массив всех настроек, отсортированных по ключу
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
     * Находит настройку по ключу
     * 
     * @param string $key Ключ настройки
     * @return array{id:int,key:string,value:string,description:string|null,updated_at:string,updated_by:int|null}|null Данные настройки или null, если не найдена
     */
    public function findByKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Получает значение настройки по ключу
     * 
     * @param string $key Ключ настройки
     * @return string|null Значение настройки или null, если настройка не найдена
     */
    public function getValue(string $key): ?string
    {
        $setting = $this->findByKey($key);
        return $setting ? $setting['value'] : null;
    }

    /**
     * Обновляет настройку
     * 
     * @param string $key Ключ настройки
     * @param string $value Новое значение настройки
     * @param int $updatedBy ID пользователя, обновляющего настройку
     * @return void
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

