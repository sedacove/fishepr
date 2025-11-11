<?php

namespace App\Services;

use App\Models\Measurement\MeasurementPoolOption;
use App\Models\Measurement\MeasurementRecord;
use App\Models\Measurement\MeasurementSeriesPoint;
use App\Repositories\MeasurementRepository;
use App\Repositories\PoolRepository;
use App\Support\Exceptions\ValidationException;
use DateTime;
use PDO;
use RuntimeException;

require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/activity_log.php';

class MeasurementService
{
    private MeasurementRepository $measurements;
    private PoolRepository $pools;
    private array $temperatureSettings;
    private array $oxygenSettings;
    private int $editTimeoutMinutes;
    private int $latestSeriesLimit = 20;

    public function __construct(PDO $pdo)
    {
        $this->measurements = new MeasurementRepository($pdo);
        $this->pools = new PoolRepository($pdo);

        $this->temperatureSettings = [
            'bad_below' => (float)\getSetting('temp_bad_below', 10),
            'acceptable_min' => (float)\getSetting('temp_acceptable_min', 10),
            'good_min' => (float)\getSetting('temp_good_min', 14),
            'good_max' => (float)\getSetting('temp_good_max', 17),
            'acceptable_max' => (float)\getSetting('temp_acceptable_max', 20),
            'bad_above' => (float)\getSetting('temp_bad_above', 20),
        ];

        $this->oxygenSettings = [
            'bad_below' => (float)\getSetting('oxygen_bad_below', 8),
            'acceptable_min' => (float)\getSetting('oxygen_acceptable_min', 8),
            'good_min' => (float)\getSetting('oxygen_good_min', 11),
            'good_max' => (float)\getSetting('oxygen_good_max', 16),
            'acceptable_max' => (float)\getSetting('oxygen_acceptable_max', 20),
            'bad_above' => (float)\getSetting('oxygen_bad_above', 20),
        ];

        $this->editTimeoutMinutes = \getSettingInt('measurement_edit_timeout_minutes', 30);
    }

    public function listByPool(int $poolId, int $currentUserId, bool $isAdmin): array
    {
        if ($poolId <= 0) {
            throw new ValidationException('pool_id', 'ID бассейна не указан', 400);
        }

        $measurements = $this->measurements->listByPool($poolId);
        $result = [];

        foreach ($measurements as $row) {
            $measuredAt = new DateTime($row['measured_at']);
            $createdAt = new DateTime($row['created_at']);

            $record = new MeasurementRecord([
                'id' => (int)$row['id'],
                'pool_id' => (int)$row['pool_id'],
                'temperature' => (float)$row['temperature'],
                'oxygen' => (float)$row['oxygen'],
                'measured_at' => $measuredAt->format('Y-m-d\TH:i'),
                'measured_at_display' => $measuredAt->format('d.m.Y H:i'),
                'created_at' => $createdAt->format('d.m.Y H:i'),
                'created_by' => (int)$row['created_by'],
                'created_by_login' => $row['created_by_login'] ?? null,
                'created_by_name' => $row['created_by_name'] ?? null,
                'created_by_full_name' => $row['created_by_name'] ?? null,
                'can_edit' => $this->canEditMeasurement($row, $currentUserId, $isAdmin),
            ]);

            $record->temperature_stratum = $this->calculateStratum($record->temperature, $this->temperatureSettings);
            $record->oxygen_stratum = $this->calculateStratum($record->oxygen, $this->oxygenSettings);

            $result[] = $record->toArray();
        }

        return $result;
    }

    public function get(int $id): array
    {
        $measurement = $this->measurements->findWithUser($id);
        if (!$measurement) {
            throw new RuntimeException('Замер не найден', 404);
        }

        $measuredAt = new DateTime($measurement['measured_at']);
        $measurement['measured_at'] = $measuredAt->format('Y-m-d\TH:i');
        $measurement['temperature_stratum'] = $this->calculateStratum((float)$measurement['temperature'], $this->temperatureSettings);
        $measurement['oxygen_stratum'] = $this->calculateStratum((float)$measurement['oxygen'], $this->oxygenSettings);

        return $measurement;
    }

    public function create(array $payload, int $userId, bool $isAdmin): int
    {
        $poolId = isset($payload['pool_id']) ? (int)$payload['pool_id'] : 0;
        if ($poolId <= 0) {
            throw new ValidationException('pool_id', 'Бассейн обязателен для выбора');
        }

        $temperature = $this->extractFloat($payload, 'temperature');
        $oxygen = $this->extractFloat($payload, 'oxygen');

        $pool = $this->pools->findActive($poolId);
        if (!$pool) {
            throw new RuntimeException('Бассейн не найден или неактивен', 404);
        }

        $measuredAt = null;
        if ($isAdmin && !empty($payload['measured_at'])) {
            $measuredAt = $this->normalizeDateTime($payload['measured_at']);
        }
        if ($measuredAt === null) {
            $measuredAt = date('Y-m-d H:i:s');
        }

        $id = $this->measurements->insert($poolId, $temperature, $oxygen, $measuredAt, $userId);

        if ($isAdmin) {
            \logActivity('create', 'measurement', $id, 'Добавлен замер для бассейна: ' . ($pool['name'] ?? $poolId), [
                'pool_id' => $poolId,
                'temperature' => $temperature,
                'oxygen' => $oxygen,
                'measured_at' => $measuredAt,
            ]);
        }

        return $id;
    }

    public function update(int $id, array $payload, int $userId, bool $isAdmin): void
    {
        $existing = $this->measurements->findWithUser($id);
        if (!$existing) {
            throw new RuntimeException('Замер не найден', 404);
        }

        if (!$isAdmin && (int)$existing['created_by'] !== $userId) {
            throw new RuntimeException('Вы можете редактировать только свои замеры', 403);
        }

        if (!$isAdmin && !$this->canEditMeasurement($existing, $userId, false)) {
            throw new RuntimeException(
                'Редактирование возможно только в течение ' . $this->editTimeoutMinutes . ' минут после создания замера',
                403
            );
        }

        $poolId = $isAdmin && isset($payload['pool_id'])
            ? (int)$payload['pool_id']
            : (int)$existing['pool_id'];
        if ($poolId <= 0) {
            throw new ValidationException('pool_id', 'Бассейн обязателен для выбора');
        }

        if ($poolId !== (int)$existing['pool_id']) {
            $pool = $this->pools->findActive($poolId);
            if (!$pool) {
                throw new RuntimeException('Бассейн не найден или неактивен', 404);
            }
        }

        $temperature = $this->extractFloat($payload, 'temperature', (float)$existing['temperature']);
        $oxygen = $this->extractFloat($payload, 'oxygen', (float)$existing['oxygen']);

        $measuredAt = $isAdmin && !empty($payload['measured_at'])
            ? $this->normalizeDateTime($payload['measured_at'])
            : $existing['measured_at'];

        $this->measurements->update($id, [
            'pool_id' => $poolId,
            'temperature' => $temperature,
            'oxygen' => $oxygen,
            'measured_at' => $measuredAt,
        ]);

        \logActivity('update', 'measurement', $id, 'Обновлён замер для бассейна: ' . ($existing['pool_name'] ?? $poolId), [
            'pool_id' => ['old' => $existing['pool_id'], 'new' => $poolId],
            'temperature' => ['old' => (float)$existing['temperature'], 'new' => $temperature],
            'oxygen' => ['old' => (float)$existing['oxygen'], 'new' => $oxygen],
            'measured_at' => ['old' => $existing['measured_at'], 'new' => $measuredAt],
        ]);
    }

    public function delete(int $id, bool $isAdmin): void
    {
        if (!$isAdmin) {
            throw new RuntimeException('Доступ запрещен', 403);
        }

        $existing = $this->measurements->findWithUser($id);
        if (!$existing) {
            throw new RuntimeException('Замер не найден', 404);
        }

        $this->measurements->delete($id);

        \logActivity('delete', 'measurement', $id, 'Удалён замер для бассейна: ' . ($existing['pool_name'] ?? ''), [
            'pool_id' => $existing['pool_id'],
            'temperature' => $existing['temperature'],
            'oxygen' => $existing['oxygen'],
            'measured_at' => $existing['measured_at'],
        ]);
    }

    public function latest(string $type): array
    {
        $column = $type === 'oxygen' ? 'oxygen' : 'temperature';
        $rows = $this->measurements->latestByColumn($column, $this->latestSeriesLimit);

        $points = [];
        foreach ($rows as $row) {
            $stratumSource = $column === 'temperature'
                ? $this->calculateStratum((float)$row['temperature'], $this->temperatureSettings)
                : $this->calculateStratum((float)$row['oxygen'], $this->oxygenSettings);

            $label = (new DateTime($row['measured_at']))->format('d.m H:i');

            $points[] = (new MeasurementSeriesPoint([
                'id' => (int)$row['id'],
                'pool_id' => (int)$row['pool_id'],
                'pool_name' => $row['pool_name'] ?? null,
                'value' => (float)$row['target_value'],
                'measured_at' => $row['measured_at'],
                'label' => $label,
                'stratum' => $stratumSource,
            ]))->toArray();
        }

        return array_reverse($points);
    }

    public function getPools(): array
    {
        $pools = $this->pools->getActiveWithSessions();
        $result = [];
        foreach ($pools as $pool) {
            $option = new MeasurementPoolOption([
                'id' => (int)$pool['id'],
                'pool_name' => $pool['pool_name'] ?? $pool['name'],
                'active_session' => $pool['active_session'] ?? null,
                'name' => $pool['pool_name'] ?? $pool['name'],
            ]);
            $result[] = $option->toArray();
        }
        return $result;
    }

    private function calculateStratum(?float $value, array $settings): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value < $settings['bad_below'] || $value > $settings['bad_above']) {
            return 'bad';
        }
        $isAcceptableLow = ($value >= $settings['acceptable_min'] && $value < $settings['good_min']);
        $isAcceptableHigh = ($value > $settings['good_max'] && $value <= $settings['acceptable_max']);
        if ($isAcceptableLow || $isAcceptableHigh) {
            return 'acceptable';
        }
        if ($value >= $settings['good_min'] && $value <= $settings['good_max']) {
            return 'good';
        }
        return 'bad';
    }

    private function normalizeDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m-d H:i:s');
        }
        if (strpos($value, 'T') !== false) {
            $value = str_replace('T', ' ', $value);
        }
        if (!str_contains($value, ':')) {
            $value .= ':00';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new ValidationException('measured_at', 'Некорректная дата и время');
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function extractFloat(array $payload, string $key, ?float $default = null): float
    {
        $labels = [
            'temperature' => 'Температура обязательна для заполнения',
            'oxygen' => 'Количество кислорода обязательно для заполнения',
        ];

        if (!array_key_exists($key, $payload) && $default !== null) {
            return $default;
        }
        if (!isset($payload[$key]) && $default === null) {
            $message = $labels[$key] ?? (ucfirst($key) . ' обязательна для заполнения');
            throw new ValidationException($key, $message);
        }
        if (!isset($payload[$key])) {
            return $default ?? 0.0;
        }
        return (float)$payload[$key];
    }

    private function canEditMeasurement(array $measurement, int $currentUserId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }
        if ((int)$measurement['created_by'] !== $currentUserId) {
            return false;
        }
        $createdAt = strtotime($measurement['created_at']);
        if ($createdAt === false) {
            return false;
        }
        $now = time();
        $minutesPassed = ($now - $createdAt) / 60;
        return $minutesPassed <= $this->editTimeoutMinutes;
    }
}


