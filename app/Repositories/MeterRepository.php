<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с приборами учета
 * 
 * Выполняет SQL запросы к таблице meters:
 * - получение списка приборов (публичный и административный)
 * - поиск прибора по ID
 * - проверка существования прибора
 * - создание, обновление, удаление приборов
 */
class MeterRepository extends Repository
{
    /**
     * Получает список всех приборов для публичного использования
     * 
     * Возвращает только основные поля (id, name, description).
     * Сортировка по названию (по возрастанию).
     * 
     * @return array Массив приборов
     */
    public function listPublic(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, description FROM meters ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получает список всех приборов для администратора
     * 
     * Возвращает полную информацию о приборах, включая информацию
     * о пользователе, создавшем прибор. Сортировка по дате создания (от новых к старым).
     * 
     * @return array Массив приборов с полной информацией
     */
    public function listAdmin(): array
    {
        $stmt = $this->pdo->query(<<<SQL
            SELECT m.*, u.full_name AS created_by_name, u.login AS created_by_login
            FROM meters m
            LEFT JOIN users u ON u.id = m.created_by
            ORDER BY m.created_at DESC
        SQL);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Находит прибор по ID
     * 
     * @param int $id ID прибора
     * @return array|null Данные прибора или null, если не найден
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT m.*, u.full_name AS created_by_name, u.login AS created_by_login
            FROM meters m
            LEFT JOIN users u ON u.id = m.created_by
            WHERE m.id = ?
        SQL);
        $stmt->execute([$id]);
        $meter = $stmt->fetch(PDO::FETCH_ASSOC);
        return $meter ?: null;
    }

    /**
     * Проверяет существование прибора по ID
     * 
     * @param int $id ID прибора
     * @return bool true если прибор существует, false в противном случае
     */
    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM meters WHERE id = ?');
        $stmt->execute([$id]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Создает новый прибор учета
     * 
     * @param string $name Название прибора
     * @param string|null $description Описание прибора (опционально)
     * @param int $createdBy ID пользователя, создающего прибор
     * @return int ID созданного прибора
     */
    public function insert(string $name, ?string $description, int $createdBy): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO meters (name, description, created_by) VALUES (?, ?, ?)');
        $stmt->execute([$name, $description, $createdBy]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновляет данные прибора учета
     * 
     * @param int $id ID прибора
     * @param string $name Новое название прибора
     * @param string|null $description Новое описание прибора (опционально)
     * @return void
     */
    public function update(int $id, string $name, ?string $description): void
    {
        $stmt = $this->pdo->prepare('UPDATE meters SET name = ?, description = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$name, $description, $id]);
    }

    /**
     * Удаляет прибор учета
     * 
     * @param int $id ID прибора для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM meters WHERE id = ?');
        $stmt->execute([$id]);
    }
}
