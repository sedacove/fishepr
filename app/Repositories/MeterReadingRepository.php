<?php

namespace App\Repositories;

use PDO;

class MeterReadingRepository extends Repository
{
    public function getByMeter(int $meterId): array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT mr.*, u.login AS recorded_by_login, u.full_name AS recorded_by_name
            FROM meter_readings mr
            LEFT JOIN users u ON u.id = mr.recorded_by
            WHERE mr.meter_id = ?
            ORDER BY mr.recorded_at DESC, mr.id DESC
        SQL);
        $stmt->execute([$meterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT mr.*, u.login AS recorded_by_login, u.full_name AS recorded_by_name
            FROM meter_readings mr
            LEFT JOIN users u ON u.id = mr.recorded_by
            WHERE mr.id = ?
        SQL);
        $stmt->execute([$id]);
        $reading = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reading ?: null;
    }

    public function insert(int $meterId, float $value, int $userId, string $recordedAt): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO meter_readings (meter_id, reading_value, recorded_at, recorded_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$meterId, $value, $recordedAt, $userId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateValue(int $id, float $value, ?string $recordedAt = null): void
    {
        if ($recordedAt !== null) {
            $stmt = $this->pdo->prepare('UPDATE meter_readings SET reading_value = ?, recorded_at = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$value, $recordedAt, $id]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE meter_readings SET reading_value = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$value, $id]);
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM meter_readings WHERE id = ?');
        $stmt->execute([$id]);
    }
}
