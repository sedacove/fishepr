<?php

namespace App\Repositories;

use PDO;

class UserRepository extends Repository
{
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

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function setActive(int $id, bool $isActive): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$isActive ? 1 : 0, $id]);
    }
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
