<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с показаниями приборов учета
 * 
 * Выполняет SQL запросы к таблице meter_readings:
 * - получение показаний по прибору
 * - создание, обновление, удаление показаний
 * - получение последнего показания
 * - получение предыдущего/следующего показания для валидации
 * - расчет расхода за период для виджетов
 */
class MeterReadingRepository extends Repository
{
    /**
     * Получает все показания для указанного прибора
     * 
     * Показания отсортированы по дате (от новых к старым).
     * Включает информацию о пользователе, который записал показание.
     * 
     * @param int $meterId ID прибора учета
     * @return array Массив показаний с информацией о пользователе
     */
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

    /**
     * Находит показание по ID
     * 
     * @param int $id ID показания
     * @return array|null Данные показания или null, если не найдено
     */
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

    /**
     * Создает новое показание прибора учета
     * 
     * @param int $meterId ID прибора учета
     * @param float $value Значение показания
     * @param int $userId ID пользователя, записавшего показание
     * @param string $recordedAt Дата и время показания (формат: 'Y-m-d H:i:s')
     * @return int ID созданного показания
     */
    public function insert(int $meterId, float $value, int $userId, string $recordedAt): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO meter_readings (meter_id, reading_value, recorded_at, recorded_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$meterId, $value, $recordedAt, $userId]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновляет значение показания
     * 
     * Если передана новая дата, обновляет и дату, и значение.
     * Если дата не передана, обновляет только значение.
     * 
     * @param int $id ID показания
     * @param float $value Новое значение показания
     * @param string|null $recordedAt Новая дата показания (опционально)
     * @return void
     */
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

    /**
     * Удаляет показание
     * 
     * @param int $id ID показания для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM meter_readings WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Получает последнее показание для прибора (по дате)
     * 
     * Используется для валидации: новое показание не может быть меньше последнего.
     * 
     * @param int $meterId ID прибора учета
     * @return array|null Последнее показание или null, если показаний нет
     */
    public function getLatestByMeter(int $meterId): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT mr.*
            FROM meter_readings mr
            WHERE mr.meter_id = ?
            ORDER BY mr.recorded_at DESC, mr.id DESC
            LIMIT 1
        SQL);
        $stmt->execute([$meterId]);
        $reading = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reading ?: null;
    }

    /**
     * Получает предыдущее показание (по дате) для указанного показания
     * 
     * Используется для валидации при редактировании:
     * новое значение не может быть меньше предыдущего показания.
     * 
     * @param int $meterId ID прибора учета
     * @param string $recordedAt Дата показания для поиска предыдущего
     * @param int $excludeId ID показания, которое нужно исключить из поиска (текущее редактируемое)
     * @return array|null Предыдущее показание или null, если его нет
     */
    public function getPreviousReading(int $meterId, string $recordedAt, int $excludeId = 0): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT mr.*
            FROM meter_readings mr
            WHERE mr.meter_id = ?
              AND (mr.recorded_at < ? OR (mr.recorded_at = ? AND mr.id < ?))
              AND mr.id != ?
            ORDER BY mr.recorded_at DESC, mr.id DESC
            LIMIT 1
        SQL);
        $stmt->execute([$meterId, $recordedAt, $recordedAt, $excludeId, $excludeId]);
        $reading = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reading ?: null;
    }

    /**
     * Получает следующее показание (по дате) для указанного показания
     * 
     * Используется для валидации при редактировании:
     * новое значение не может быть больше следующего показания.
     * 
     * @param int $meterId ID прибора учета
     * @param string $recordedAt Дата показания для поиска следующего
     * @param int $excludeId ID показания, которое нужно исключить из поиска (текущее редактируемое)
     * @return array|null Следующее показание или null, если его нет
     */
    public function getNextReading(int $meterId, string $recordedAt, int $excludeId = 0): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT mr.*
            FROM meter_readings mr
            WHERE mr.meter_id = ?
              AND (mr.recorded_at > ? OR (mr.recorded_at = ? AND mr.id > ?))
              AND mr.id != ?
            ORDER BY mr.recorded_at ASC, mr.id ASC
            LIMIT 1
        SQL);
        $stmt->execute([$meterId, $recordedAt, $recordedAt, $excludeId, $excludeId]);
        $reading = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reading ?: null;
    }

    /**
     * Получает данные для виджета: показания за последние 30 дней с расчетом расхода
     * 
     * Алгоритм:
     * 1. Получает все показания за последние 30 дней
     * 2. Группирует по дням (берет последнее значение за день, если их несколько)
     * 3. Вычисляет расход как разницу между соседними показаниями
     * 
     * Расход для первого дня = null (нет предыдущего значения).
     * Расход для остальных дней = текущее значение - предыдущее значение.
     * 
     * @param int $meterId ID прибора учета
     * @return array Массив с данными по дням:
     *   - date: дата (Y-m-d)
     *   - date_label: дата для отображения (d.m)
     *   - reading_value: значение показания
     *   - consumption: расход (разница с предыдущим) или null для первого дня
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
