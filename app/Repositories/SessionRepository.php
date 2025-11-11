<?php

namespace App\Repositories;

use PDO;

class SessionRepository extends Repository
{
    public function findWithRelations(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                s.*,
                p.name AS pool_name,
                pl.name AS planting_name,
                pl.fish_breed,
                pl.hatch_date,
                pl.planting_date AS planting_planting_date,
                pl.fish_count AS planting_quantity,
                pl.biomass_weight AS planting_biomass_weight,
                pl.supplier,
                pl.price AS planting_price,
                pl.delivery_cost
            FROM sessions s
            LEFT JOIN pools p ON s.pool_id = p.id
            LEFT JOIN plantings pl ON s.planting_id = pl.id
            WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}


