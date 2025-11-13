<?php

namespace App\Services;

use App\Repositories\MeterReadingRepository;
use App\Repositories\MeterRepository;
use DomainException;
use PDO;
use RuntimeException;

class MeterReadingService
{
    private MeterReadingRepository $readings;
    private MeterRepository $meters;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->readings = new MeterReadingRepository($pdo);
        $this->meters = new MeterRepository($pdo);
    }

    public function listReadings(int $meterId, int $userId, bool $isAdmin): array
    {
        $this->ensureMeterExists($meterId);
        $rows = $this->readings->getByMeter($meterId);
        return array_map(fn ($reading) => $this->formatReading($reading, $userId, $isAdmin), $rows);
    }

    public function getReading(int $id, int $userId, bool $isAdmin): array
    {
        $reading = $this->readings->find($id);
        if (!$reading) {
            throw new RuntimeException('Показание не найдено');
        }
        return $this->formatReading($reading, $userId, $isAdmin);
    }

    public function createReading(array $payload, int $userId, bool $isAdmin): int
    {
        $meterId = (int)($payload['meter_id'] ?? 0);
        $value = isset($payload['reading_value']) ? (float)$payload['reading_value'] : null;
        if ($meterId <= 0) {
            throw new DomainException('Прибор учета не выбран');
        }
        if ($value === null) {
            throw new DomainException('Введите показание');
        }
        $this->ensureMeterExists($meterId);

        $recordedAt = $this->resolveRecordedAt($payload['recorded_at'] ?? null, $isAdmin);

        $readingId = $this->readings->insert($meterId, $value, $userId, $recordedAt);

        if (\function_exists('logActivity')) {
            \logActivity('create', 'meter_reading', $readingId, 'Добавлено показание прибора учета', [
                'meter_id' => $meterId,
                'reading_value' => $value,
                'recorded_at' => $recordedAt,
            ]);
        }

        return $readingId;
    }

    public function updateReading(array $payload, int $userId, bool $isAdmin): void
    {
        $id = (int)($payload['id'] ?? 0);
        $value = isset($payload['reading_value']) ? (float)$payload['reading_value'] : null;
        if ($id <= 0) {
            throw new DomainException('ID показания не указан');
        }
        if ($value === null) {
            throw new DomainException('Введите показание');
        }

        $reading = $this->readings->find($id);
        if (!$reading) {
            throw new RuntimeException('Показание не найдено');
        }

        if (!$this->canModify($reading, $userId, $isAdmin)) {
            throw new DomainException('Вы не можете редактировать это показание');
        }

        // Для админа: если передана новая дата, обновляем её, иначе оставляем старую
        $recordedAt = null;
        if ($isAdmin) {
            if (isset($payload['recorded_at']) && $payload['recorded_at'] !== null && $payload['recorded_at'] !== '') {
                // Передана новая дата - обновляем
                $recordedAt = $this->resolveRecordedAt($payload['recorded_at'], true);
            } else {
                // Дата не передана - оставляем старую
                $recordedAt = $reading['recorded_at'];
            }
        }

        $this->readings->updateValue($id, $value, $recordedAt);

        if (\function_exists('logActivity')) {
            \logActivity('update', 'meter_reading', $id, 'Обновлено показание прибора учета', [
                'reading_value' => ['old' => $reading['reading_value'], 'new' => $value],
                'recorded_at' => $recordedAt !== null ? ['old' => $reading['recorded_at'], 'new' => $recordedAt] : null,
            ]);
        }
    }

    public function deleteReading(int $id, int $userId, bool $isAdmin): void
    {
        $reading = $this->readings->find($id);
        if (!$reading) {
            throw new RuntimeException('Показание не найдено');
        }

        if (!$isAdmin && !$this->canModify($reading, $userId, false)) {
            throw new DomainException('Вы не можете удалить это показание');
        }

        $this->readings->delete($id);

        if (\function_exists('logActivity')) {
            \logActivity('delete', 'meter_reading', $id, 'Удалено показание прибора учета', [
                'meter_id' => $reading['meter_id'],
                'reading_value' => $reading['reading_value'],
            ]);
        }
    }

    private function formatReading(array $reading, int $userId, bool $isAdmin): array
    {
        $recordedAt = new \DateTime($reading['recorded_at']);
        $reading['recorded_at_display'] = $recordedAt->format('d.m.Y H:i');
        $reading['recorded_at_iso'] = $recordedAt->format('Y-m-d\\TH:i');
        $reading['recorded_by_label'] = $reading['recorded_by_name']
            ? $reading['recorded_by_name'] . ' (' . $reading['recorded_by_login'] . ')'
            : $reading['recorded_by_login'];
        $reading['can_edit'] = $this->canModify($reading, $userId, $isAdmin);
        $reading['can_delete'] = $isAdmin || $reading['can_edit'];
        return $reading;
    }

    private function canModify(array $reading, int $userId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }
        if ((int)$reading['recorded_by'] !== $userId) {
            return false;
        }
        $recordedAt = new \DateTime($reading['recorded_at']);
        $diffMinutes = (time() - $recordedAt->getTimestamp()) / 60;
        return $diffMinutes <= $this->getEditTimeoutMinutes();
    }

    private function resolveRecordedAt(?string $value, bool $allowOverride, ?string $fallback = null): string
    {
        if (!$allowOverride || empty($value)) {
            if ($fallback !== null) {
                return $fallback;
            }
            return \date('Y-m-d H:i:s');
        }

        $normalized = str_replace('T', ' ', trim($value));
        if (strlen($normalized) === 16) {
            $normalized .= ':00';
        }

        $timestamp = \strtotime($normalized);
        if ($timestamp === false) {
            throw new DomainException('Некорректная дата показания');
        }

        return \date('Y-m-d H:i:s', $timestamp);
    }

    private function getEditTimeoutMinutes(): int
    {
        if (\function_exists('getSettingInt')) {
            return max(0, (int)\getSettingInt('meter_reading_edit_timeout_minutes', 30));
        }
        return 30;
    }

    public function getWidgetData(int $meterId): array
    {
        $this->ensureMeterExists($meterId);
        $meter = $this->meters->find($meterId);
        $data = $this->readings->getLast30DaysWithConsumption($meterId);
        
        return [
            'meter' => [
                'id' => $meter['id'],
                'name' => $meter['name'],
            ],
            'data' => $data,
        ];
    }

    public function getAllMeters(): array
    {
        // Получаем все приборы
        $allMeters = $this->meters->listPublic();
        
        // Фильтруем: оставляем только те, у которых есть показания за последние 14 дней
        $metersWithData = [];
        foreach ($allMeters as $meter) {
            $meterId = (int)$meter['id'];
            $readings = $this->readings->getLast30DaysWithConsumption($meterId);
            
            // Проверяем, есть ли данные за последние 14 дней
            $hasRecentData = false;
            $cutoffDate = new \DateTime();
            $cutoffDate->modify('-14 days');
            
            foreach ($readings as $reading) {
                $readingDate = new \DateTime($reading['date']);
                if ($readingDate >= $cutoffDate) {
                    $hasRecentData = true;
                    break;
                }
            }
            
            if ($hasRecentData) {
                $metersWithData[] = $meter;
            }
        }
        
        return $metersWithData;
    }

    private function ensureMeterExists(int $meterId): void
    {
        if (!$this->meters->exists($meterId)) {
            throw new RuntimeException('Прибор учета не найден');
        }
    }
}
