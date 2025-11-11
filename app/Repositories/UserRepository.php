<?php

namespace App\Repositories;

use PDO;

class UserRepository extends Repository
{
    public function getActiveUsers(): array
    {
        $stmt = $this->pdo->query("SELECT id, login, full_name FROM users WHERE is_active = 1 AND deleted_at IS NULL ORDER BY full_name ASC, login ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActiveById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, login, full_name FROM users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }
}
