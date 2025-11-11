<?php

namespace App\Repositories;

use PDO;

class MortalityRepository extends Repository
{
    public function getDailyTotalsForPoolSince(int $poolId, string $startDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                DATE(recorded_at) AS day,
                SUM(weight) AS total_weight,
                SUM(fish_count) AS total_count
             FROM mortality
             WHERE pool_id = ?
               AND recorded_at >= ?
             GROUP BY day
             ORDER BY day ASC'
        );
        $stmt->execute([$poolId, $startDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}


