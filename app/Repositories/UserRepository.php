<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с пользователями
 * 
 * Выполняет SQL запросы к таблице users:
 * - получение списка активных пользователей
 * - поиск пользователя по ID или логину
 * - создание, обновление, удаление пользователей
 * - управление активностью пользователей
 * 
 * Использует мягкое удаление (soft delete) через поле deleted_at
 */
class UserRepository extends Repository
{
    /**
     * Получает список всех активных (не удаленных) пользователей
     * 
     * @return array Массив пользователей, отсортированных по дате создания (от новых к старым)
     */
    public function findAllActive(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, login, user_type, full_name, email, salary, phone, payroll_phone, payroll_bank, is_active, created_at, updated_at
             FROM users
             WHERE deleted_at IS NULL
             ORDER BY created_at DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Находит пользователя по ID
     * 
     * @param int $id ID пользователя
     * @return array|null Данные пользователя или null, если не найден или удален
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, login, user_type, full_name, email, salary, phone, payroll_phone, payroll_bank, is_active, created_at, updated_at
             FROM users
             WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Находит пользователя по логину
     * 
     * Используется для проверки уникальности логина при создании пользователя.
     * 
     * @param string $login Логин пользователя
     * @return array|null Данные пользователя (только id и login) или null, если не найден или удален
     */
    public function findByLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, login
             FROM users
             WHERE login = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$login]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Создает нового пользователя
     * 
     * @param array $data Данные пользователя (login, password, user_type, full_name, email, salary, phone, etc.)
     * @return int ID созданного пользователя
     */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (login, password, user_type, full_name, email, salary, phone, payroll_phone, payroll_bank)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['login'],
            $data['password'],
            $data['user_type'],
            $data['full_name'],
            $data['email'],
            $data['salary'],
            $data['phone'],
            $data['payroll_phone'],
            $data['payroll_bank'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновляет данные пользователя
     * 
     * Динамически формирует SQL запрос на основе переданных данных.
     * Обновляет только те поля, которые переданы в массиве $data.
     * 
     * @param int $id ID пользователя
     * @param array $data Ассоциативный массив полей для обновления (column => value)
     * @return void
     */
    public function update(int $id, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = [];
        $params = [];
        foreach ($data as $column => $value) {
            $columns[] = "{$column} = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $sql = 'UPDATE users SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Выполняет мягкое удаление пользователя
     * 
     * Устанавливает поле deleted_at в текущую дату и время.
     * Пользователь остается в базе данных, но не отображается в списках.
     * 
     * @param int $id ID пользователя для удаления
     * @return void
     */
    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Устанавливает статус активности пользователя
     * 
     * @param int $id ID пользователя
     * @param bool $isActive Статус активности (true = активен, false = заблокирован)
     * @return void
     */
    public function setActive(int $id, bool $isActive): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$isActive ? 1 : 0, $id]);
    }

    /**
     * Получает список всех активных пользователей
     * 
     * Используется для выпадающих списков и других случаев,
     * когда нужны только активные пользователи.
     * 
     * @return array Массив пользователей (id, login, full_name), отсортированных по имени и логину
     */
    public function getActiveUsers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, login, full_name
             FROM users
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY full_name ASC, login ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Находит активного пользователя по ID
     * 
     * @param int $userId ID пользователя
     * @return array|null Данные пользователя (id, login, full_name) или null, если не найден, неактивен или удален
     */
    public function findActiveById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, login, full_name
             FROM users
             WHERE id = ? AND is_active = 1 AND deleted_at IS NULL'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }
}
