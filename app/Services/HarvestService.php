<?php

namespace App\Services;

use App\Models\Harvest\Harvest;
use App\Repositories\CounterpartyRepository;
use App\Repositories\HarvestRepository;
use App\Repositories\PoolRepository;
use App\Support\Exceptions\ValidationException;
use DomainException;
use PDO;
use RuntimeException;

require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/activity_log.php';

/**
 * Сервис для работы с отборами
 * 
 * Содержит бизнес-логику для работы с отборами:
 * - валидация данных
 * - проверка прав доступа на редактирование
 * - логирование действий
 * - форматирование данных для отображения
 */
class HarvestService
{
    /**
     * @var HarvestRepository Репозиторий для работы с отборами
     */
    private HarvestRepository $harvests;
    
    /**
     * @var PoolRepository Репозиторий для работы с бассейнами
     */
    private PoolRepository $pools;
    
    /**
     * @var CounterpartyRepository Репозиторий для работы с контрагентами
     */
    private CounterpartyRepository $counterparties;
    
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
        $this->harvests = new HarvestRepository($pdo);
        $this->pools = new PoolRepository($pdo);
        $this->counterparties = new CounterpartyRepository($pdo);
    }

    /**
     * Получает список отборов для указанного бассейна
     * 
     * @param int $poolId ID бассейна
     * @param int $currentUserId ID текущего пользователя
     * @param bool $isAdmin Является ли пользователь администратором
     * @return array Массив отборов с дополнительными полями (can_edit, display dates, etc.)
     * @throws ValidationException Если бассейн не указан или не найден
     */
    public function listByPool(int $poolId, int $currentUserId, bool $isAdmin): array
    {
        if ($poolId <= 0) {
            throw new ValidationException('pool_id', 'ID бассейна не указан');
        }
        $pool = $this->pools->findActive($poolId);
        if (!$pool) {
            throw new ValidationException('pool_id', 'Бассейн не найден или неактивен');
        }

        $records = $this->harvests->listByPool($poolId);
        $timeoutMinutes = (int)\getSettingInt('measurement_edit_timeout_minutes', 30);
        $result = [];

        foreach ($records as $row) {
            $model = new Harvest($row);
            $model->counterparty_id = isset($row['counterparty_id']) ? (int)$row['counterparty_id'] : null;
            $model->weight = (float)$model->weight;
            $model->fish_count = (int)$model->fish_count;
            $model->recorded_at_display = $this->formatDisplayDate($model->recorded_at);
            $model->created_at_display = $this->formatDisplayDate($model->created_at);
            $model->updated_at_display = $this->formatDisplayDate($model->updated_at);
            $model->created_by_full_name = $model->created_by_name;
            $model->can_edit = $this->canEdit($model, $currentUserId, $isAdmin, $timeoutMinutes);
            $result[] = $model->toArray();
        }

        return $result;
    }

    /**
     * Получает один отбор по ID
     * 
     * @param int $id ID отбора
     * @return array Данные отбора с форматированными датами
     * @throws RuntimeException Если отбор не найден
     */
    public function get(int $id): array
    {
        $record = $this->harvests->find($id);
        if (!$record) {
            throw new RuntimeException('Отбор не найден', 404);
        }
        $model = new Harvest($record);
        $model->counterparty_id = isset($record['counterparty_id']) ? (int)$record['counterparty_id'] : null;
        $model->weight = (float)$model->weight;
        $model->fish_count = (int)$model->fish_count;
        $model->recorded_at = $this->formatInputDate($model->recorded_at);
        $model->recorded_at_display = $this->formatDisplayDate($record['recorded_at']);
        $model->created_by_full_name = $model->created_by_name;
        return $model->toArray();
    }

    /**
     * Создает новый отбор
     * 
     * Валидация:
     * - бассейн должен быть указан и существовать
     * - вес должен быть указан и положительным
     * - количество рыбы должно быть указано и положительным
     * - контрагент (если указан) должен существовать
     * 
     * @param array $payload Данные отбора (pool_id, weight, fish_count, counterparty_id, recorded_at)
     * @param int $userId ID пользователя, создающего отбор
     * @param bool $isAdmin Является ли пользователь администратором
     * @return int ID созданного отбора
     * @throws ValidationException Если данные некорректны
     */
    public function create(array $payload, int $userId, bool $isAdmin): int
    {
        $data = $this->validatePayload($payload, $isAdmin);
        $pool = $this->pools->findActive($data['pool_id']);
        if (!$pool) {
            throw new ValidationException('pool_id', 'Бассейн не найден или неактивен');
        }

        if (isset($data['counterparty_id'])) {
            $this->assertCounterpartyExists($data['counterparty_id']);
        }

        $recordedAt = $data['recorded_at'] ?? null;
        if (!$isAdmin || !$recordedAt) {
            $recordedAt = date('Y-m-d H:i:s');
        }

        $id = $this->harvests->insert([
            'pool_id' => $data['pool_id'],
            'weight' => $data['weight'],
            'fish_count' => $data['fish_count'],
            'counterparty_id' => $data['counterparty_id'] ?? null,
            'recorded_at' => $this->normalizeTimestamp($recordedAt),
            'created_by' => $userId,
        ]);

        \logActivity('create', 'harvest', $id, 'Добавлен отбор для бассейна: ' . $pool['name'], [
            'pool_id' => $data['pool_id'],
            'weight' => $data['weight'],
            'fish_count' => $data['fish_count'],
            'counterparty_id' => $data['counterparty_id'] ?? null,
            'recorded_at' => $recordedAt,
        ]);

        return $id;
    }

    public function update(int $id, array $payload, int $userId, bool $isAdmin): void
    {
        $existing = $this->harvests->find($id);
        if (!$existing) {
            throw new RuntimeException('Отбор не найден', 404);
        }

        $data = $this->validatePayload($payload, $isAdmin);

        if (!$isAdmin && (int)$existing['created_by'] !== $userId) {
            throw new DomainException('Вы можете редактировать только свои записи');
        }
        if (!$isAdmin) {
            $timeoutMinutes = (int)\getSettingInt('measurement_edit_timeout_minutes', 30);
            if (!$this->withinEditWindow($existing['created_at'], $timeoutMinutes)) {
                throw new DomainException("Редактирование возможно только в течение {$timeoutMinutes} минут после создания записи");
            }
        }

        $poolId = $isAdmin && isset($data['pool_id']) ? $data['pool_id'] : (int)$existing['pool_id'];
        $pool = $this->pools->findActive($poolId);
        if (!$pool) {
            throw new ValidationException('pool_id', 'Бассейн не найден или неактивен');
        }

        $counterpartyId = $data['counterparty_id'] ?? $existing['counterparty_id'] ?? null;
        if ($counterpartyId !== null) {
            $this->assertCounterpartyExists((int)$counterpartyId);
        }

        $recordedAt = $existing['recorded_at'];
        if ($isAdmin && isset($data['recorded_at'])) {
            $recordedAt = $this->normalizeTimestamp($data['recorded_at']);
        }

        $updates = [
            'pool_id' => $poolId,
            'weight' => $data['weight'],
            'fish_count' => $data['fish_count'],
            'counterparty_id' => $counterpartyId,
            'recorded_at' => $recordedAt,
        ];

        $this->harvests->update($id, $updates);

        \logActivity('update', 'harvest', $id, 'Обновлён отбор для бассейна: ' . ($existing['pool_name'] ?? $pool['name']), [
            'pool_id' => ['old' => $existing['pool_id'], 'new' => $poolId],
            'weight' => ['old' => (float)$existing['weight'], 'new' => $data['weight']],
            'fish_count' => ['old' => (int)$existing['fish_count'], 'new' => $data['fish_count']],
            'counterparty_id' => ['old' => $existing['counterparty_id'], 'new' => $counterpartyId],
            'recorded_at' => ['old' => $existing['recorded_at'], 'new' => $recordedAt],
        ]);
    }

    public function delete(int $id, bool $isAdmin): void
    {
        if (!$isAdmin) {
            throw new DomainException('Доступ запрещен');
        }

        $existing = $this->harvests->find($id);
        if (!$existing) {
            throw new RuntimeException('Отбор не найден', 404);
        }

        $this->harvests->delete($id);

        \logActivity('delete', 'harvest', $id, 'Удалён отбор для бассейна: ' . ($existing['pool_name'] ?? ''), [
            'pool_id' => $existing['pool_id'],
            'weight' => $existing['weight'],
            'fish_count' => $existing['fish_count'],
            'recorded_at' => $existing['recorded_at'],
        ]);
    }

    public function getPools(): array
    {
        return $this->pools->getActiveWithSessions();
    }

    private function validatePayload(array $payload, bool $isAdmin): array
    {
        $poolId = isset($payload['pool_id']) ? (int)$payload['pool_id'] : 0;
        if ($poolId <= 0) {
            throw new ValidationException('pool_id', 'Бассейн обязателен для выбора');
        }

        $weight = isset($payload['weight']) ? (float)$payload['weight'] : 0.0;
        if ($weight <= 0) {
            throw new ValidationException('weight', 'Вес должен быть положительным числом');
        }

        $fishCount = isset($payload['fish_count']) ? (int)$payload['fish_count'] : -1;
        if ($fishCount < 0) {
            throw new ValidationException('fish_count', 'Количество рыб должно быть неотрицательным числом');
        }

        $counterpartyId = $payload['counterparty_id'] ?? null;
        if ($counterpartyId !== null) {
            $counterpartyId = (int)$counterpartyId;
            if ($counterpartyId <= 0) {
                throw new ValidationException('counterparty_id', 'Выберите корректного контрагента');
            }
        }

        $recordedAt = null;
        if ($isAdmin && isset($payload['recorded_at']) && $payload['recorded_at'] !== '') {
            $recordedAt = $this->normalizeTimestamp($payload['recorded_at']);
        }

        return [
            'pool_id' => $poolId,
            'weight' => $weight,
            'fish_count' => $fishCount,
            'counterparty_id' => $counterpartyId,
            'recorded_at' => $recordedAt,
        ];
    }

    private function normalizeTimestamp(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m-d H:i:s');
        }
        if (strpos($value, 'T') !== false) {
            $value = str_replace('T', ' ', $value);
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new ValidationException('recorded_at', 'Некорректная дата и время');
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function formatDisplayDate(string $value): string
    {
        return date('d.m.Y H:i', strtotime($value));
    }

    private function formatInputDate(string $value): string
    {
        return date('Y-m-d\TH:i', strtotime($value));
    }

    private function canEdit(Harvest $harvest, int $currentUserId, bool $isAdmin, int $timeoutMinutes): bool
    {
        if ($isAdmin) {
            return true;
        }
        if ($harvest->created_by !== $currentUserId) {
            return false;
        }
        return $this->withinEditWindow($harvest->created_at, $timeoutMinutes);
    }

    private function withinEditWindow(string $createdAt, int $timeoutMinutes): bool
    {
        if ($timeoutMinutes <= 0) {
            return false;
        }
        $createdTimestamp = strtotime($createdAt);
        if ($createdTimestamp === false) {
            return false;
        }
        return (time() - $createdTimestamp) <= ($timeoutMinutes * 60);
    }

    private function assertCounterpartyExists(int $counterpartyId): void
    {
        if (!$this->counterparties->findById($counterpartyId)) {
            throw new ValidationException('counterparty_id', 'Контрагент не найден');
        }
    }
}


