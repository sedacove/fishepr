<?php

namespace App\Services;

use App\Models\Session\Session;
use App\Repositories\FeedRepository;
use App\Repositories\SessionRepository;
use App\Support\Exceptions\ValidationException;
use PDO;
use RuntimeException;

require_once __DIR__ . '/../../includes/activity_log.php';

/**
 * Сервис для работы с сессиями
 * 
 * Содержит бизнес-логику для работы с сессиями:
 * - валидация данных
 * - расчет FCR (Feed Conversion Ratio)
 * - логирование действий
 * - форматирование данных для отображения
 */
class SessionService
{
    private const FEEDING_STRATEGIES = ['econom', 'normal', 'growth'];
    private const DEFAULT_DAILY_FEEDINGS = 3;

    /**
     * @var SessionRepository Репозиторий для работы с сессиями
     */
    private SessionRepository $sessions;

    /**
     * @var FeedRepository Репозиторий кормов
     */
    private FeedRepository $feeds;
    
    /**
     * @var PDO Подключение к базе данных
     */
    private PDO $pdo;

    /**
     * Конструктор сервиса
     * 
     * @param PDO $pdo Подключение к базе данных
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->sessions = new SessionRepository($pdo);
        $this->feeds = new FeedRepository($pdo);
    }

    /**
     * Получает список сессий (активных или завершенных)
     * 
     * Для каждой сессии рассчитывает FCR, если есть все необходимые данные.
     * 
     * @param bool $completed true для завершенных сессий, false для активных
     * @return array<int,array<string,mixed>> Массив сессий с форматированными датами и рассчитанным FCR
     */
    public function list(bool $completed): array
    {
        $items = $this->sessions->listByCompletion($completed);
        return array_map(function (Session $session) {
            $data = $session->toArray();
            $data['start_date'] = $this->formatDisplayDate($data['start_date']);
            $data['end_date'] = $data['end_date'] ? $this->formatDisplayDate($data['end_date']) : null;
            $data['created_at'] = $this->formatDateTime($data['created_at']);
            $data['updated_at'] = $this->formatDateTime($data['updated_at']);
            $data['is_completed'] = (bool) $data['is_completed'];

            if (!empty($data['end_mass']) && !empty($data['feed_amount']) && !empty($data['start_mass'])) {
                $gain = $data['end_mass'] - $data['start_mass'];
                if ($gain > 0) {
                    $data['fcr'] = round($data['feed_amount'] / $gain, 4);
                }
            }
            $data['feeding_strategy'] = $data['feeding_strategy'] ?? 'normal';
            $data['daily_feedings'] = (int)($data['daily_feedings'] ?? self::DEFAULT_DAILY_FEEDINGS);
            return $data;
        }, $items);
    }

    /**
     * Получает сессию по ID
     * 
     * Рассчитывает FCR, если есть все необходимые данные.
     * 
     * @param int $id ID сессии
     * @return array Данные сессии с форматированными датами и рассчитанным FCR
     * @throws RuntimeException Если сессия не найдена
     */
    public function get(int $id): array
    {
        $session = $this->sessions->find($id);
        if (!$session) {
            throw new RuntimeException('Сессия не найдена', 404);
        }

        $data = $session->toArray();
        $data['start_date'] = $this->formatFormDate($data['start_date']);
        $data['end_date'] = $data['end_date'] ? $this->formatFormDate($data['end_date']) : null;
        $data['is_completed'] = (bool) $data['is_completed'];
        $data['feeding_strategy'] = $data['feeding_strategy'] ?? 'normal';
        $data['daily_feedings'] = (int)($data['daily_feedings'] ?? self::DEFAULT_DAILY_FEEDINGS);

        if (!empty($data['end_mass']) && !empty($data['feed_amount']) && !empty($data['start_mass'])) {
            $gain = $data['end_mass'] - $data['start_mass'];
            if ($gain > 0) {
                $data['fcr'] = round($data['feed_amount'] / $gain, 4);
            }
        }

        return $data;
    }

    /**
     * Получает список активных бассейнов
     * 
     * Используется для выпадающих списков при создании/редактировании сессий.
     * 
     * @return array<int,array{id:int,name:string}> Массив бассейнов (id, name)
     */
    public function getActivePools(): array
    {
        return $this->sessions->getActivePools();
    }

    /**
     * @return array<int,array{id:int,name:string,fish_breed:string}>
     */
    public function getActivePlantings(): array
    {
        return $this->sessions->getActivePlantings();
    }

    /**
     * @return array<int,array{id:int,name:string}>
     */
    public function getFeeds(): array
    {
        return $this->feeds->options();
    }

    public function create(array $payload, int $userId): int
    {
        $data = $this->validateSessionPayload($payload);

        if ($this->sessions->hasActiveSessionInPool($data['pool_id'])) {
            throw new RuntimeException('В этом бассейне уже есть текущая сессия. Завершите ее прежде, чем добавить новую.', 400);
        }

        $data['created_by'] = $userId;
        $id = $this->sessions->create($data);

        \logActivity('create', 'session', $id, "Создана сессия: {$data['name']}", [
            'name' => $data['name'],
            'pool_id' => $data['pool_id'],
            'planting_id' => $data['planting_id'],
            'start_date' => $data['start_date'],
            'start_mass' => $data['start_mass'],
            'start_fish_count' => $data['start_fish_count'],
            'previous_fcr' => $data['previous_fcr'],
        ]);

        return $id;
    }

    public function update(int $id, array $payload): void
    {
        $session = $this->sessions->find($id);
        if (!$session) {
            throw new RuntimeException('Сессия не найдена', 404);
        }

        if ($session->is_completed) {
            $data = $this->validateCompletionUpdatePayload($payload);
            if (empty($data)) {
                throw new RuntimeException('Нет данных для обновления', 400);
            }
            $this->sessions->updateCompletion($id, $data);
            \logActivity('update', 'session', $id, "Обновлены данные завершения сессии: {$session->name}", $data);
            return;
        }

        $data = $this->validateSessionPayload($payload);
        if ($this->sessions->hasActiveSessionInPool($data['pool_id'], $id)) {
            throw new RuntimeException('В этом бассейне уже есть текущая сессия. Завершите ее прежде, чем добавить новую.', 400);
        }

        $this->sessions->update($id, $data);
        $changes = $this->detectChanges($session->toArray(), $data);
        if (!empty($changes)) {
            \logActivity('update', 'session', $id, "Обновлена сессия: {$session->name}", $changes);
        }
    }

    public function complete(int $id, array $payload): array
    {
        $session = $this->sessions->find($id);
        if (!$session) {
            throw new RuntimeException('Сессия не найдена', 404);
        }
        if ($session->is_completed) {
            throw new RuntimeException('Сессия уже завершена', 400);
        }

        $data = $this->validateCompletionPayload($payload, $session);
        $this->sessions->markCompleted($id, $data);

        \logActivity('update', 'session', $id, "Завершена сессия: {$session->name}" . ($data['fcr'] !== null ? " (FCR: {$data['fcr']})" : ''), [
            'is_completed' => ['old' => false, 'new' => true],
            'end_date' => $data['end_date'],
            'end_mass' => $data['end_mass'],
            'feed_amount' => $data['feed_amount'],
            'fcr' => $data['fcr'],
        ]);

        return ['fcr' => $data['fcr']];
    }

    public function delete(int $id): void
    {
        $session = $this->sessions->find($id);
        if (!$session) {
            throw new RuntimeException('Сессия не найдена', 404);
        }

        $this->sessions->delete($id);

        \logActivity('delete', 'session', $id, "Удалена сессия: {$session->name}", [
            'name' => $session->name,
            'pool_id' => $session->pool_id,
            'planting_id' => $session->planting_id,
            'start_date' => $session->start_date,
            'start_mass' => $session->start_mass,
            'start_fish_count' => $session->start_fish_count,
            'is_completed' => (bool) $session->is_completed,
            'end_date' => $session->end_date,
            'end_mass' => $session->end_mass,
            'feed_amount' => $session->feed_amount,
            'fcr' => $session->fcr,
        ]);
    }

    /**
     * @return array{
     *     name:string,
     *     pool_id:int,
     *     planting_id:int,
     *     start_date:string,
     *     start_mass:float,
     *     start_fish_count:int,
     *     previous_fcr:?float
     * }
     */
    private function validateSessionPayload(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('name', 'Название обязательно для заполнения');
        }

        $poolId = (int) ($payload['pool_id'] ?? 0);
        if ($poolId <= 0) {
            throw new ValidationException('pool_id', 'Бассейн обязателен для выбора');
        }

        $plantingId = (int) ($payload['planting_id'] ?? 0);
        if ($plantingId <= 0) {
            throw new ValidationException('planting_id', 'Посадка обязательна для выбора');
        }

        $startDate = $this->parseDate($payload['start_date'] ?? null, 'start_date', 'Дата начала обязательна для заполнения');

        $startMass = $this->parsePositiveFloat($payload['start_mass'] ?? null, 'start_mass', 'Масса посадки должна быть больше 0');
        $startFishCount = $this->parsePositiveInt($payload['start_fish_count'] ?? null, 'start_fish_count', 'Количество рыб должно быть больше 0');

        $previousFcr = null;
        if ($payload['previous_fcr'] !== null && $payload['previous_fcr'] !== '') {
            $previousFcr = $this->parseNonNegativeFloat($payload['previous_fcr'], 'previous_fcr', 'Прошлый FCR должен быть неотрицательным числом');
        }

        $dailyFeedings = (int)($payload['daily_feedings'] ?? self::DEFAULT_DAILY_FEEDINGS);
        if ($dailyFeedings <= 0) {
            throw new ValidationException('daily_feedings', 'Количество кормежек должно быть больше нуля');
        }

        $feedId = (int)($payload['feed_id'] ?? 0);
        if ($feedId <= 0 || !$this->feeds->exists($feedId)) {
            throw new ValidationException('feed_id', 'Выберите корм из списка');
        }

        $strategy = $payload['feeding_strategy'] ?? 'normal';
        if (!in_array($strategy, self::FEEDING_STRATEGIES, true)) {
            throw new ValidationException('feeding_strategy', 'Некорректная стратегия кормления');
        }

        return [
            'name' => $name,
            'pool_id' => $poolId,
            'planting_id' => $plantingId,
            'start_date' => $startDate,
            'start_mass' => $startMass,
            'start_fish_count' => $startFishCount,
            'previous_fcr' => $previousFcr,
            'daily_feedings' => $dailyFeedings,
            'feed_id' => $feedId,
            'feeding_strategy' => $strategy,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function validateCompletionUpdatePayload(array $payload): array
    {
        $data = [];
        if (array_key_exists('end_mass', $payload) && $payload['end_mass'] !== null && $payload['end_mass'] !== '') {
            $data['end_mass'] = $this->parsePositiveFloat($payload['end_mass'], 'end_mass', 'Масса в конце должна быть больше 0');
        }
        if (array_key_exists('feed_amount', $payload) && $payload['feed_amount'] !== null && $payload['feed_amount'] !== '') {
            $data['feed_amount'] = $this->parseNonNegativeFloat($payload['feed_amount'], 'feed_amount', 'Количество корма должно быть неотрицательным');
        }
        if (!empty($payload['end_date'])) {
            $data['end_date'] = $this->parseDate($payload['end_date'], 'end_date', 'Некорректная дата окончания');
        }
        if (isset($data['end_mass'], $data['feed_amount'], $payload['start_mass'])) {
            $startMass = (float) $payload['start_mass'];
            $gain = $data['end_mass'] - $startMass;
            if ($gain > 0) {
                $data['fcr'] = round($data['feed_amount'] / $gain, 4);
            }
        }
        return $data;
    }

    /**
     * @return array{end_date:string,end_mass:float,feed_amount:float,fcr:?float}
     */
    private function validateCompletionPayload(array $payload, Session $session): array
    {
        $endMass = $this->parsePositiveFloat($payload['end_mass'] ?? null, 'end_mass', 'Масса в конце обязательна и должна быть больше 0');
        $feedAmount = $this->parseNonNegativeFloat($payload['feed_amount'] ?? null, 'feed_amount', 'Количество внесенного корма обязательно');
        $endDate = $this->parseDate($payload['end_date'] ?? date('Y-m-d'), 'end_date', 'Дата окончания обязательна для заполнения');

        $fcr = null;
        $gain = $endMass - $session->start_mass;
        if ($gain > 0) {
            $fcr = round($feedAmount / $gain, 4);
        }

        return [
            'end_date' => $endDate,
            'end_mass' => $endMass,
            'feed_amount' => $feedAmount,
            'fcr' => $fcr,
        ];
    }

    /**
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $updated
     * @return array<string,mixed>
     */
    private function detectChanges(array $existing, array $updated): array
    {
        $changes = [];
        foreach ($updated as $key => $value) {
            $old = $existing[$key] ?? null;
            if (in_array($key, ['start_mass', 'previous_fcr'])) {
                $old = $old !== null ? (float) $old : null;
                $value = $value !== null ? (float) $value : null;
            }
            if (in_array($key, ['start_fish_count', 'pool_id', 'planting_id', 'daily_feedings', 'feed_id'])) {
                $old = $old !== null ? (int) $old : null;
                $value = $value !== null ? (int) $value : null;
            }
            if ($old !== $value) {
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
        }
        return $changes;
    }

    private function parseDate(?string $value, string $field, string $errorMessage): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new ValidationException($field, $errorMessage);
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new ValidationException($field, 'Некорректный формат даты');
        }
        return date('Y-m-d', $timestamp);
    }

    private function parsePositiveFloat($value, string $field, string $errorMessage): float
    {
        if (!is_numeric($value)) {
            throw new ValidationException($field, $errorMessage);
        }
        $float = (float) $value;
        if ($float <= 0) {
            throw new ValidationException($field, $errorMessage);
        }
        return round($float, 2);
    }

    private function parseNonNegativeFloat($value, string $field, string $errorMessage): float
    {
        if (!is_numeric($value)) {
            throw new ValidationException($field, $errorMessage);
        }
        $float = (float) $value;
        if ($float < 0) {
            throw new ValidationException($field, $errorMessage);
        }
        return round($float, 2);
    }

    private function parsePositiveInt($value, string $field, string $errorMessage): int
    {
        if (!is_numeric($value)) {
            throw new ValidationException($field, $errorMessage);
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new ValidationException($field, $errorMessage);
        }
        return $int;
    }

    private function formatDisplayDate(string $value): string
    {
        return date('d.m.Y', strtotime($value));
    }

    private function formatFormDate(string $value): string
    {
        return date('Y-m-d', strtotime($value));
    }

    private function formatDateTime(string $value): string
    {
        return date('d.m.Y H:i', strtotime($value));
    }
}


