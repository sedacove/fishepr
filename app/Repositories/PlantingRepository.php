<?php

namespace App\Repositories;

use App\Models\Planting\Planting;
use App\Models\Planting\PlantingFile;
use PDO;

class PlantingRepository extends Repository
{
    /**
     * @return Planting[]
     */
    public function listByArchived(bool $archived): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.login AS created_by_login, u.full_name AS created_by_name,
                    (SELECT COUNT(*) FROM planting_files pf WHERE pf.planting_id = p.id) AS files_count
             FROM plantings p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.is_archived = ?
             ORDER BY p.planting_date DESC, p.created_at DESC'
        );
        $stmt->execute([$archived ? 1 : 0]);
        return array_map(
            fn ($row) => new Planting($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    public function find(int $id): ?Planting
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.login AS created_by_login, u.full_name AS created_by_name
             FROM plantings p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new Planting($row) : null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO plantings (
                name, fish_breed, hatch_date, planting_date, fish_count,
                biomass_weight, supplier, price, delivery_cost, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['fish_breed'],
            $data['hatch_date'],
            $data['planting_date'],
            $data['fish_count'],
            $data['biomass_weight'],
            $data['supplier'],
            $data['price'],
            $data['delivery_cost'],
            $data['created_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plantings SET
                name = ?,
                fish_breed = ?,
                hatch_date = ?,
                planting_date = ?,
                fish_count = ?,
                biomass_weight = ?,
                supplier = ?,
                price = ?,
                delivery_cost = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['fish_breed'],
            $data['hatch_date'],
            $data['planting_date'],
            $data['fish_count'],
            $data['biomass_weight'],
            $data['supplier'],
            $data['price'],
            $data['delivery_cost'],
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM plantings WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function setArchived(int $id, bool $archived): void
    {
        $stmt = $this->pdo->prepare('UPDATE plantings SET is_archived = ? WHERE id = ?');
        $stmt->execute([$archived ? 1 : 0, $id]);
    }

    /**
     * @return PlantingFile[]
     */
    public function getFiles(int $plantingId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM planting_files WHERE planting_id = ? ORDER BY created_at ASC'
        );
        $stmt->execute([$plantingId]);

        return array_map(
            fn ($row) => new PlantingFile($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    public function findFile(int $id): ?PlantingFile
    {
        $stmt = $this->pdo->prepare('SELECT * FROM planting_files WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? new PlantingFile($row) : null;
    }

    public function createFile(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO planting_files (planting_id, original_name, file_name, file_path, file_size, mime_type, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['planting_id'],
            $data['original_name'],
            $data['file_name'],
            $data['file_path'],
            $data['file_size'],
            $data['mime_type'],
            $data['uploaded_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteFile(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM planting_files WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function deleteFilesByPlanting(int $plantingId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM planting_files WHERE planting_id = ?');
        $stmt->execute([$plantingId]);
    }
}


