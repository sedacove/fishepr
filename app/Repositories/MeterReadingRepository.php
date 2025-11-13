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

    /**
     * Получить данные для виджета: показания за последние 30 дней с расчетом расхода
     * Возвращает массив с данными по дням, где расход = разница между соседними показаниями
     */
    public function getLast30DaysWithConsumption(int $meterId): array
    {
        // Получаем все показания за последние 30 дней, отсортированные по дате
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT 
                DATE(mr.recorded_at) AS reading_date,
                mr.reading_value,
                mr.recorded_at,
                mr.id
            FROM meter_readings mr
            WHERE mr.meter_id = ?
              AND mr.recorded_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY reading_date ASC, mr.recorded_at ASC, mr.id ASC
        SQL);
        $stmt->execute([$meterId]);
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Группируем по дням, берем последнее значение за день
        $dailyReadings = [];
        foreach ($allRows as $row) {
            $date = $row['reading_date'];
            if (!isset($dailyReadings[$date])) {
                $dailyReadings[$date] = $row;
            } else {
                // Если есть несколько показаний за день, берем последнее
                $existingDate = new \DateTime($dailyReadings[$date]['recorded_at']);
                $currentDate = new \DateTime($row['recorded_at']);
                if ($currentDate > $existingDate || 
                    ($currentDate == $existingDate && (int)$row['id'] > (int)$dailyReadings[$date]['id'])) {
                    $dailyReadings[$date] = $row;
                }
            }
        }

        // Сортируем по дате
        ksort($dailyReadings);
        $rows = array_values($dailyReadings);

        // Вычисляем расход (разница между соседними значениями)
        $result = [];
        $previousValue = null;

        foreach ($rows as $row) {
            $date = $row['reading_date'];
            $value = (float)$row['reading_value'];
            $dateObj = new \DateTime($date);
            $dateLabel = $dateObj->format('d.m');

            // Если есть предыдущее значение, вычисляем расход для текущего дня
            $consumption = null;
            if ($previousValue !== null) {
                $consumption = max(0, $value - $previousValue); // Расход не может быть отрицательным
            }

            $result[] = [
                'date' => $date,
                'date_label' => $dateLabel,
                'reading_value' => $value,
                'consumption' => $consumption, // null для первого дня, иначе разница с предыдущим
            ];

            $previousValue = $value;
        }

        return $result;
    }
}
