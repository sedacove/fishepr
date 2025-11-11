<?php

namespace App\Repositories;

use PDO;

class MeasurementRepository extends Repository
{
    public function listForPoolSince(int $poolId, string $startDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT measured_at, temperature, oxygen, created_by
             FROM measurements
             WHERE pool_id = ?
               AND measured_at >= ?
             ORDER BY measured_at ASC'
        );
        $stmt->execute([$poolId, $startDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}


