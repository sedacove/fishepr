<?php

namespace App\Repositories;

use PDO;

class PoolRepository extends Repository
{
    public function findActive(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM pools WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function listActive(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, sort_order FROM pools WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getActiveWithSessions(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, sort_order FROM pools WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
        $pools = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$pools) {
            return [];
        }

        $sessionStmt = $this->pdo->prepare(
            'SELECT s.id, s.name AS session_name FROM sessions s WHERE s.pool_id = ? AND s.is_completed = 0 ORDER BY s.start_date DESC LIMIT 1'
        );

        foreach ($pools as &$pool) {
            $sessionStmt->execute([$pool['id']]);
            $pool['active_session'] = $sessionStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $pool['pool_name'] = $pool['name'];
        }

        return $pools;
    }
}


