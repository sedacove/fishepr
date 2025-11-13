<?php

namespace App\Services;

use App\Repositories\MeterReadingRepository;
use App\Repositories\MeterRepository;
use DomainException;
use PDO;
use RuntimeException;

/**
 * Сервис для работы с показаниями приборов учета
 * 
 * Содержит бизнес-логику для работы с показаниями:
 * - валидация данных
 * - проверка прав доступа
 * - логирование действий
 * - расчет расхода (разница между показаниями)
 * 
 * Валидация:
 * - новое показание не может быть меньше предыдущего
 * - при редактировании проверяется, что значение находится между предыдущим и следующим
 */
class MeterReadingService
{
    /**
     * @var MeterReadingRepository Репозиторий для работы с показаниями
     */
    private MeterReadingRepository $readings;
    
    /**
     * @var MeterRepository Репозиторий для работы с приборами
     */
    private MeterRepository $meters;
    
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
        $this->readings = new MeterReadingRepository($pdo);
        $this->meters = new MeterRepository($pdo);
    }

    /**
     * Получает список показаний для указанного прибора
     * 
     * @param int $meterId ID прибора учета
     * @param int $userId ID текущего пользователя
     * @param bool $isAdmin Является ли пользователь администратором
     * @return array Массив показаний с дополнительными полями (can_edit, can_delete, etc.)
     */
    public function listReadings(int $meterId, int $userId, bool $isAdmin): array
    {
        $this->ensureMeterExists($meterId);
        $rows = $this->readings->getByMeter($meterId);
        return array_map(fn ($reading) => $this->formatReading($reading, $userId, $isAdmin), $rows);
    }

    /**
     * Получает одно показание по ID
     * 
     * @param int $id ID показания
     * @param int $userId ID текущего пользователя
     * @param bool $isAdmin Является ли пользователь администратором
     * @return array Показание с дополнительными полями (can_edit, can_delete, etc.)
     * @throws RuntimeException Если показание не найдено
     */
    public function getReading(int $id, int $userId, bool $isAdmin): array
    {
        $reading = $this->readings->find($id);
        if (!$reading) {
            throw new RuntimeException('Показание не найдено');
        }
        return $this->formatReading($reading, $userId, $isAdmin);
    }

    /**
     * Создает новое показание прибора учета
     * 
     * Валидация:
     * - прибор учета должен быть выбран
     * - значение показания должно быть указано
     * - новое показание не может быть меньше последнего
     * 
     * @param array $payload Данные показания (meter_id, reading_value, recorded_at)
     * @param int $userId ID пользователя, создающего показание
     * @param bool $isAdmin Является ли пользователь администратором
     * @return int ID созданного показания
     * @throws DomainException Если данные некорректны или валидация не пройдена
     */
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

        // Валидация: новое показание не может быть меньше последнего
        $latestReading = $this->readings->getLatestByMeter($meterId);
        if ($latestReading !== null) {
            $latestValue = (float)$latestReading['reading_value'];
            if ($value < $latestValue) {
                throw new DomainException("Новое показание ({$value}) не может быть меньше последнего показания ({$latestValue})");
            }
        }

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

    /**
     * Обновляет существующее показание прибора учета
     * 
     * Валидация:
     * - ID показания должен быть указан
     * - значение показания должно быть указано
     * - пользователь должен иметь права на редактирование
     * - новое показание не может быть меньше предыдущего
     * - новое показание не может быть больше следующего
     * 
     * Для администраторов:
     * - можно изменить дату показания, если она передана
     * 
     * @param array $payload Данные показания (id, reading_value, recorded_at)
     * @param int $userId ID пользователя, обновляющего показание
     * @param bool $isAdmin Является ли пользователь администратором
     * @return void
     * @throws DomainException Если данные некорректны, нет прав или валидация не пройдена
     * @throws RuntimeException Если показание не найдено
     */
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
        } else {
            $recordedAt = $reading['recorded_at'];
        }

        // Валидация: новое показание не может быть меньше предыдущего и не больше следующего
        $meterId = (int)$reading['meter_id'];
        $previousReading = $this->readings->getPreviousReading($meterId, $recordedAt, $id);
        if ($previousReading !== null) {
            $previousValue = (float)$previousReading['reading_value'];
            if ($value < $previousValue) {
                throw new DomainException("Новое показание ({$value}) не может быть меньше предыдущего показания ({$previousValue})");
            }
        }

        $nextReading = $this->readings->getNextReading($meterId, $recordedAt, $id);
        if ($nextReading !== null) {
            $nextValue = (float)$nextReading['reading_value'];
            if ($value > $nextValue) {
                throw new DomainException("Новое показание ({$value}) не может быть больше следующего показания ({$nextValue})");
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

    /**
     * Удаляет показание прибора учета
     * 
     * Права доступа:
     * - администратор может удалить любое показание
     * - обычный пользователь может удалить только свое показание в течение тайм-аута
     * 
     * @param int $id ID показания для удаления
     * @param int $userId ID текущего пользователя
     * @param bool $isAdmin Является ли пользователь администратором
     * @return void
     * @throws DomainException Если нет прав на удаление
     * @throws RuntimeException Если показание не найдено
     */
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

    /**
     * Форматирует показание для отображения
     * 
     * Добавляет дополнительные поля:
     * - recorded_at_display: дата в формате для отображения
     * - recorded_at_iso: дата в ISO формате для input[type="datetime-local"]
     * - recorded_by_label: метка пользователя (имя + логин)
     * - can_edit: может ли пользователь редактировать показание
     * - can_delete: может ли пользователь удалить показание
     * 
     * @param array $reading Данные показания из БД
     * @param int $userId ID текущего пользователя
     * @param bool $isAdmin Является ли пользователь администратором
     * @return array Отформатированное показание
     */
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

    /**
     * Проверяет, может ли пользователь редактировать показание
     * 
     * Правила:
     * - администратор может редактировать любое показание
     * - обычный пользователь может редактировать только свое показание
     * - обычный пользователь может редактировать только в течение тайм-аута
     * 
     * @param array $reading Данные показания
     * @param int $userId ID текущего пользователя
     * @param bool $isAdmin Является ли пользователь администратором
     * @return bool true если можно редактировать, false в противном случае
     */
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

    /**
     * Разрешает дату показания из различных форматов
     * 
     * Поддерживает форматы:
     * - 'Y-m-d H:i:s' (стандартный формат БД)
     * - 'Y-m-d\TH:i' (формат datetime-local input)
     * - 'Y-m-d\TH:i:s' (полный ISO формат)
     * 
     * @param string|null $value Значение даты (может быть null или пустым)
     * @param bool $allowOverride Разрешено ли переопределение даты
     * @param string|null $fallback Значение по умолчанию, если $value пустое
     * @return string Дата в формате 'Y-m-d H:i:s'
     * @throws DomainException Если дата некорректна
     */
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

    /**
     * Получает тайм-аут редактирования показаний в минутах
     * 
     * Значение берется из настроек системы (meter_reading_edit_timeout_minutes).
     * По умолчанию: 30 минут.
     * 
     * @return int Тайм-аут в минутах
     */
    private function getEditTimeoutMinutes(): int
    {
        if (\function_exists('getSettingInt')) {
            return max(0, (int)\getSettingInt('meter_reading_edit_timeout_minutes', 30));
        }
        return 30;
    }

    /**
     * Получает данные для виджета приборов учета на дашборде
     * 
     * Возвращает данные за последние 30 дней с расчетом расхода
     * (разница между соседними показаниями).
     * 
     * @param int $meterId ID прибора учета
     * @return array Массив с ключами 'meter' (информация о приборе) и 'data' (данные за 30 дней)
     */
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

    /**
     * Получает список всех приборов учета с данными за последние 14 дней
     * 
     * Фильтрует приборы, оставляя только те, у которых есть показания
     * за последние 14 дней. Используется для виджета на дашборде.
     * 
     * @return array Массив приборов с данными за последние 14 дней
     */
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

    /**
     * Проверяет существование прибора учета
     * 
     * @param int $meterId ID прибора учета
     * @return void
     * @throws RuntimeException Если прибор не найден
     */
    private function ensureMeterExists(int $meterId): void
    {
        if (!$this->meters->exists($meterId)) {
            throw new RuntimeException('Прибор учета не найден');
        }
    }
}
