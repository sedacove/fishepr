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

/**
 * Сервис для работы со смертностью
 * 
 * Содержит бизнес-логику для работы со смертностью:
 * - валидация данных
 * - проверка прав доступа на редактирование
 * - логирование действий
 * - отправка уведомлений в Telegram при превышении порогов
 * - форматирование данных для отображения
 * - построение графиков смертности
 */
class MortalityService
{
    /**
     * @var MortalityRepository Репозиторий для работы со смертностью
     */
    private MortalityRepository $mortality;
    
    /**
     * @var PoolRepository Репозиторий для работы с бассейнами
     */
    private PoolRepository $pools;
    
    /**
     * @var SessionRepository Репозиторий для работы с сессиями
     */
    private SessionRepository $sessions;
    
    /**
     * @var int Тайм-аут редактирования записей смертности (в минутах)
     */
    private int $editTimeoutMinutes;

    /**
     * Конструктор сервиса
     * 
     * Инициализирует репозитории и загружает настройки из системы.
     * 
     * @param PDO $pdo Подключение к базе данных
     */
    public function __construct(PDO $pdo)
    {
        $this->mortality = new MortalityRepository($pdo);
        $this->pools = new PoolRepository($pdo);
        $this->sessions = new SessionRepository($pdo);
        $this->editTimeoutMinutes = \getSettingInt('measurement_edit_timeout_minutes', 30);
    }

    /**
     * Получает список записей смертности для указанной сессии
     * 
     * @param int $sessionId ID сессии
     * @param int $currentUserId ID текущего пользователя
     * @param bool $isAdmin Является ли пользователь администратором
     * @return array Массив записей смертности с правами доступа
     * @throws ValidationException Если сессия не указана
     */
    public function listBySession(int $sessionId, int $currentUserId, bool $isAdmin): array
    {
        if ($sessionId <= 0) {
            throw new ValidationException('session_id', 'ID сессии не указан', 400);
        }

        $records = $this->mortality->listBySession($sessionId);
        $result = [];

        foreach ($records as $row) {
            $recordedAt = new DateTime($row['recorded_at']);
            $createdAt = new DateTime($row['created_at']);

            $record = new MortalityRecord([
                'id' => (int)$row['id'],
                'pool_id' => isset($row['pool_id']) ? (int)$row['pool_id'] : null,
                'session_id' => isset($row['session_id']) ? (int)$row['session_id'] : null,
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

    /**
     * Получает список записей смертности из завершенных сессий
     * 
     * @param int $currentUserId ID текущего пользователя
     * @param bool $isAdmin Является ли пользователь администратором
     * @return array Массив записей смертности с информацией о сессии и бассейне
     */
    public function listCompletedSessionsMortality(int $currentUserId, bool $isAdmin): array
    {
        $records = $this->mortality->listForCompletedSessions();
        $result = [];

        foreach ($records as $row) {
            $recordedAt = new DateTime($row['recorded_at']);
            $createdAt = new DateTime($row['created_at']);

            $record = new MortalityRecord([
                'id' => (int)$row['id'],
                'pool_id' => isset($row['pool_id']) ? (int)$row['pool_id'] : null,
                'session_id' => isset($row['session_id']) ? (int)$row['session_id'] : null,
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

            // Добавляем информацию о сессии и бассейне
            $data = $record->toArray();
            $data['session_id'] = isset($row['session_id']) ? (int)$row['session_id'] : null;
            $data['session_name'] = $row['session_name'] ?? null;
            $data['pool_id'] = isset($row['pool_id']) ? (int)$row['pool_id'] : null;
            $data['pool_name'] = $row['pool_name'] ?? null;
            
            $result[] = $data;
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
        $sessionId = isset($payload['session_id']) ? (int)$payload['session_id'] : 0;
        if ($sessionId <= 0) {
            throw new ValidationException('session_id', 'Сессия обязательна для выбора');
        }

        $session = $this->sessions->find($sessionId);
        if (!$session || $session->is_completed) {
            throw new RuntimeException('Активная сессия не найдена', 404);
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

        $id = $this->mortality->insert($sessionId, $weight, $fishCount, $recordedAt, $userId);

        if ($isAdmin) {
            \logActivity('create', 'mortality', $id, 'Добавлен падеж для сессии: ' . $session->name, [
                'session_id' => $sessionId,
                'pool_id' => $session->pool_id,
                'weight' => $weight,
                'fish_count' => $fishCount,
                'recorded_at' => $recordedAt,
            ]);
        }

        try {
            $pool = $this->pools->find($session->pool_id);
            $createdByName = $_SESSION['user_full_name'] ?? $_SESSION['user_login'] ?? null;

            $poolName = $pool ? $pool->name : ('Бассейн #' . $session->pool_id);

            \maybeSendMortalityAlert([
                'pool_name' => $poolName,
                'session_name' => $session->name,
                'fish_count' => $fishCount,
                'weight' => $weight,
                'recorded_at' => $recordedAt,
                'created_by' => $createdByName,
            ]);
        } catch (\Throwable $e) {
            // Логируем ошибку, но не прерываем выполнение, так как запись уже создана
            error_log('Ошибка при отправке уведомления о падеже: ' . $e->getMessage());
        }

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

        $sessionId = $isAdmin && isset($payload['session_id'])
            ? (int)$payload['session_id']
            : (int)$existing['session_id'];
        if ($sessionId <= 0) {
            throw new ValidationException('session_id', 'Сессия обязательна для выбора');
        }
        
        $session = $this->sessions->find($sessionId);
        if (!$session) {
            throw new RuntimeException('Сессия не найдена', 404);
        }
        if ($isAdmin && $sessionId !== (int)$existing['session_id'] && $session->is_completed) {
            throw new RuntimeException('Активная сессия не найдена', 404);
        }
        
        // Обновляем pool_id из сессии для обратной совместимости
        $updateData = [
            'session_id' => $sessionId,
            'pool_id' => $session->pool_id,
        ];

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

        $updateData['weight'] = $weight;
        $updateData['fish_count'] = $fishCount;
        $updateData['recorded_at'] = $recordedAt;
        
        $this->mortality->update($id, $updateData);

        \logActivity('update', 'mortality', $id, 'Обновлён падеж для сессии: ' . ($existing['session_name'] ?? $sessionId), [
            'session_id' => ['old' => $existing['session_id'], 'new' => $sessionId],
            'pool_id' => ['old' => $existing['pool_id'] ?? null, 'new' => isset($session) ? $session->pool_id : ($existing['pool_id'] ?? null)],
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

        \logActivity('delete', 'mortality', $id, 'Удалён падеж для сессии: ' . ($existing['session_name'] ?? ''), [
            'session_id' => $existing['session_id'],
            'pool_id' => $existing['pool_id'] ?? null,
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
        // Возвращаем активные бассейны с активными сессиями для обратной совместимости
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

    /**
     * Получает список активных сессий с информацией о бассейнах
     * 
     * @return array Массив активных сессий с информацией о бассейнах
     */
    public function getActiveSessions(): array
    {
        $sessions = $this->sessions->listByCompletionWithPoolSort(false);
        $result = [];
        
        foreach ($sessions as $session) {
            $result[] = [
                'id' => (int)$session['id'],
                'name' => $session['name'],
                'session_name' => $session['name'],
                'pool_id' => (int)$session['pool_id'],
                'pool_name' => $session['pool_name'],
                'start_date' => $session['start_date'],
            ];
        }
        
        // Данные уже отсортированы в SQL запросе по pool_sort_order
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


