<?php

namespace App\Services;

use App\Models\Mortality\MortalityPoolSeries;
use App\Models\Mortality\MortalityRecord;
use App\Models\Mortality\MortalityTotalsPoint;
use App\Repositories\MortalityRepository;
use App\Repositories\PoolRepository;
use App\Repositories\SessionRepository;
use App\Support\Exceptions\ValidationException;
use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeImmutable;
use PDO;
use RuntimeException;

require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/activity_log.php';
require_once __DIR__ . '/../../includes/telegram.php';

class MortalityService
{
    private MortalityRepository $mortality;
    private PoolRepository $pools;
    private SessionRepository $sessions;
    private int $editTimeoutMinutes;

    public function __construct(PDO $pdo)
    {
        $this->mortality = new MortalityRepository($pdo);
        $this->pools = new PoolRepository($pdo);
        $this->sessions = new SessionRepository($pdo);
        $this->editTimeoutMinutes = \getSettingInt('measurement_edit_timeout_minutes', 30);
    }

    public function listByPool(int $poolId, int $currentUserId, bool $isAdmin): array
    {
        if ($poolId <= 0) {
            throw new ValidationException('pool_id', 'ID бассейна не указан', 400);
        }

        $records = $this->mortality->listByPool($poolId);
        $result = [];

        foreach ($records as $row) {
            $recordedAt = new DateTime($row['recorded_at']);
            $createdAt = new DateTime($row['created_at']);

            $record = new MortalityRecord([
                'id' => (int)$row['id'],
                'pool_id' => (int)$row['pool_id'],
                'weight' => (float)$row['weight'],
                'fish_count' => (int)$row['fish_count'],
                'recorded_at' => $recordedAt->format('Y-m-d\TH:i'),
                'recorded_at_display' => $recordedAt->format('d.m.Y H:i'),
                'created_at' => $createdAt->format('d.m.Y H:i'),
                'created_by' => (int)$row['created_by'],
                'created_by_login' => $row['created_by_login'] ?? null,
                'created_by_name' => $row['created_by_name'] ?? null,
                'created_by_full_name' => $row['created_by_name'] ?? null,
                'can_edit' => $this->canEdit($row, $currentUserId, $isAdmin),
            ]);

            $result[] = $record->toArray();
        }

        return $result;
    }

    public function get(int $id): array
    {
        $record = $this->mortality->findWithUser($id);
        if (!$record) {
            throw new RuntimeException('Запись не найдена', 404);
        }
        $record['recorded_at'] = (new DateTime($record['recorded_at']))->format('Y-m-d\TH:i');
        return $record;
    }

    public function create(array $payload, int $userId, bool $isAdmin): int
    {
        $poolId = isset($payload['pool_id']) ? (int)$payload['pool_id'] : 0;
        if ($poolId <= 0) {
            throw new ValidationException('pool_id', 'Бассейн обязателен для выбора');
        }

        $pool = $this->pools->findActive($poolId);
        if (!$pool) {
            throw new RuntimeException('Бассейн не найден или неактивен', 404);
        }

        $weight = $this->extractFloat($payload, 'weight');
        if ($weight <= 0) {
            throw new ValidationException('weight', 'Вес должен быть положительным числом');
        }

        $fishCount = $this->extractInt($payload, 'fish_count', 0);
        if ($fishCount < 0) {
            throw new ValidationException('fish_count', 'Количество рыб должно быть неотрицательным числом');
        }

        $recordedAt = $isAdmin && !empty($payload['recorded_at'])
            ? $this->normalizeDateTime($payload['recorded_at'])
            : date('Y-m-d H:i:s');

        $id = $this->mortality->insert($poolId, $weight, $fishCount, $recordedAt, $userId);

        if ($isAdmin) {
            \logActivity('create', 'mortality', $id, 'Добавлен падеж для бассейна: ' . ($pool['name'] ?? $poolId), [
                'pool_id' => $poolId,
                'weight' => $weight,
                'fish_count' => $fishCount,
                'recorded_at' => $recordedAt,
            ]);
        }

        $session = $this->sessions->findActiveByPool($poolId);
        $createdByName = $_SESSION['user_full_name'] ?? $_SESSION['user_login'] ?? null;

        \maybeSendMortalityAlert([
            'pool_name' => $pool['name'] ?? ('Бассейн #' . $poolId),
            'session_name' => $session['name'] ?? null,
            'fish_count' => $fishCount,
            'weight' => $weight,
            'recorded_at' => $recordedAt,
            'created_by' => $createdByName,
        ]);

        return $id;
    }

    public function update(int $id, array $payload, int $userId, bool $isAdmin): void
    {
        $existing = $this->mortality->findWithUser($id);
        if (!$existing) {
            throw new RuntimeException('Запись не найдена', 404);
        }

        if (!$isAdmin && (int)$existing['created_by'] !== $userId) {
            throw new RuntimeException('Вы можете редактировать только свои записи', 403);
        }
        if (!$isAdmin && !$this->canEdit($existing, $userId, false)) {
            throw new RuntimeException(
                'Редактирование возможно только в течение ' . $this->editTimeoutMinutes . ' минут после создания записи',
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

        $weight = $this->extractFloat($payload, 'weight', (float)$existing['weight']);
        if ($weight <= 0) {
            throw new ValidationException('weight', 'Вес должен быть положительным числом');
        }

        $fishCount = $this->extractInt($payload, 'fish_count', (int)$existing['fish_count']);
        if ($fishCount < 0) {
            throw new ValidationException('fish_count', 'Количество рыб должно быть неотрицательным числом');
        }

        $recordedAt = $isAdmin && !empty($payload['recorded_at'])
            ? $this->normalizeDateTime($payload['recorded_at'])
            : $existing['recorded_at'];

        $this->mortality->update($id, [
            'pool_id' => $poolId,
            'weight' => $weight,
            'fish_count' => $fishCount,
            'recorded_at' => $recordedAt,
        ]);

        \logActivity('update', 'mortality', $id, 'Обновлён падеж для бассейна: ' . ($existing['pool_name'] ?? $poolId), [
            'pool_id' => ['old' => $existing['pool_id'], 'new' => $poolId],
            'weight' => ['old' => (float)$existing['weight'], 'new' => $weight],
            'fish_count' => ['old' => (int)$existing['fish_count'], 'new' => $fishCount],
            'recorded_at' => ['old' => $existing['recorded_at'], 'new' => $recordedAt],
        ]);
    }

    public function delete(int $id, bool $isAdmin): void
    {
        if (!$isAdmin) {
            throw new RuntimeException('Доступ запрещен', 403);
        }

        $existing = $this->mortality->findWithUser($id);
        if (!$existing) {
            throw new RuntimeException('Запись не найдена', 404);
        }

        $this->mortality->delete($id);

        \logActivity('delete', 'mortality', $id, 'Удалён падеж для бассейна: ' . ($existing['pool_name'] ?? ''), [
            'pool_id' => $existing['pool_id'],
            'weight' => $existing['weight'],
            'fish_count' => $existing['fish_count'],
            'recorded_at' => $existing['recorded_at'],
        ]);
    }

    public function totalsLast30(): array
    {
        $today = new DateTimeImmutable('today');
        $startDate = $today->sub(new DateInterval('P29D'));
        $endDate = $today->format('Y-m-d 23:59:59');
        $start = $startDate->format('Y-m-d 00:00:00');

        $rows = $this->mortality->getDailyTotalsInRange($start, $endDate);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['record_date']] = [
                'count' => (int)$row['total_count'],
                'weight' => (float)$row['total_weight'],
            ];
        }

        $period = new DatePeriod($startDate, new DateInterval('P1D'), $today->add(new DateInterval('P1D')));
        $result = [];
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $metrics = $map[$dateStr] ?? ['count' => 0, 'weight' => 0.0];
            $result[] = (new MortalityTotalsPoint([
                'date' => $dateStr,
                'date_label' => $date->format('d.m'),
                'total_count' => $metrics['count'],
                'total_weight' => $metrics['weight'],
            ]))->toArray();
        }

        return $result;
    }

    public function totalsLast14ByPool(): array
    {
        $today = new DateTimeImmutable('today');
        $startDate = $today->sub(new DateInterval('P13D'));

        $dates = [];
        $labels = [];
        $period = new DatePeriod($startDate, new DateInterval('P1D'), $today->add(new DateInterval('P1D')));
        foreach ($period as $date) {
            $dates[] = $date;
            $labels[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('d.m'),
            ];
        }

        $pools = $this->pools->listActive();
        if (empty($pools)) {
            return [
                'labels' => $labels,
                'pools' => [],
            ];
        }

        $poolIds = array_map(fn($pool) => (int)$pool['id'], $pools);
        $metrics = $this->mortality->getDailyTotalsByPool(
            $poolIds,
            $startDate->format('Y-m-d 00:00:00'),
            $today->format('Y-m-d 23:59:59')
        );

        $poolsData = [];
        foreach ($pools as $pool) {
            $poolId = (int)$pool['id'];
            $series = [];
            $totalCount = 0;
            $totalWeight = 0.0;

            foreach ($dates as $date) {
                $dateStr = $date->format('Y-m-d');
                $metricsForDay = $metrics[$poolId][$dateStr] ?? ['total_count' => 0, 'total_weight' => 0.0];
                $count = (int)($metricsForDay['total_count'] ?? 0);
                $weight = (float)($metricsForDay['total_weight'] ?? 0.0);
                $series[] = [
                    'date' => $dateStr,
                    'label' => $date->format('d.m'),
                    'total_count' => $count,
                    'total_weight' => $weight,
                ];
                $totalCount += $count;
                $totalWeight += $weight;
            }

            $poolsData[] = (new MortalityPoolSeries([
                'pool_id' => $poolId,
                'pool_name' => $pool['name'],
                'series' => $series,
                'total_count' => $totalCount,
                'total_weight' => $totalWeight,
            ]))->toArray();
        }

        return [
            'labels' => $labels,
            'pools' => $poolsData,
        ];
    }

    public function getPools(): array
    {
        $pools = $this->pools->getActiveWithSessions();
        $result = [];
        foreach ($pools as $pool) {
            $result[] = [
                'id' => (int)$pool['id'],
                'pool_name' => $pool['pool_name'] ?? $pool['name'],
                'name' => $pool['pool_name'] ?? $pool['name'],
                'active_session' => $pool['active_session'] ?? null,
            ];
        }
        return $result;
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
            throw new ValidationException('recorded_at', 'Некорректная дата и время');
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function extractFloat(array $payload, string $key, ?float $default = null): float
    {
        if (!array_key_exists($key, $payload) && $default !== null) {
            return $default;
        }
        if (!isset($payload[$key]) && $default === null) {
            $messages = [
                'weight' => 'Вес обязателен для заполнения',
                'fish_count' => 'Количество рыб обязательно для заполнения',
            ];
            $message = $messages[$key] ?? 'Поле обязательно для заполнения';
            throw new ValidationException($key, $message);
        }
        if (!isset($payload[$key])) {
            return $default ?? 0.0;
        }
        return (float)$payload[$key];
    }

    private function extractInt(array $payload, string $key, ?int $default = null): int
    {
        if (!array_key_exists($key, $payload) && $default !== null) {
            return $default;
        }
        if (!isset($payload[$key]) && $default === null) {
            $messages = [
                'weight' => 'Вес обязателен для заполнения',
                'fish_count' => 'Количество рыб обязательно для заполнения',
            ];
            $message = $messages[$key] ?? 'Поле обязательно для заполнения';
            throw new ValidationException($key, $message);
        }
        if (!isset($payload[$key])) {
            return $default ?? 0;
        }
        return (int)$payload[$key];
    }

    private function canEdit(array $record, int $currentUserId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }
        if ((int)$record['created_by'] !== $currentUserId) {
            return false;
        }
        $createdAt = strtotime($record['created_at']);
        if ($createdAt === false) {
            return false;
        }
        $minutesPassed = (time() - $createdAt) / 60;
        return $minutesPassed <= $this->editTimeoutMinutes;
    }
}


