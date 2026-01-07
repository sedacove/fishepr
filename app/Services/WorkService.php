<?php

namespace App\Services;

use App\Models\Work\SessionSummary;
use App\Models\Work\WorkPool;
use App\Repositories\FeedRepository;
use App\Repositories\HarvestRepository;
use App\Repositories\MeasurementRepository;
use App\Repositories\MortalityRepository;
use App\Repositories\PoolRepository;
use App\Repositories\SessionRepository;
use App\Repositories\WeighingRepository;
use App\Support\Exceptions\ValidationException;
use App\Support\FeedTableParser;
use DateTime;
use PDO;

require_once __DIR__ . '/../../includes/settings.php';

/**
 * Сервис для работы со страницей "Работа"
 * 
 * Содержит бизнес-логику для агрегации данных по бассейнам:
 * - получение списка активных бассейнов с их статусами
 * - расчет статусов измерений (температура, кислород)
 * - расчет статусов навесок и отборов
 * - расчет статусов смертности
 * - агрегация данных по сессиям
 */
class WorkService
{
    /**
     * @var PoolRepository Репозиторий для работы с бассейнами
     */
    private PoolRepository $pools;
    
    /**
     * @var SessionRepository Репозиторий для работы с сессиями
     */
    private SessionRepository $sessions;
    
    /**
     * @var MeasurementRepository Репозиторий для работы с измерениями
     */
    private MeasurementRepository $measurements;
    
    /**
     * @var MortalityRepository Репозиторий для работы со смертностью
     */
    private MortalityRepository $mortality;
    
    /**
     * @var HarvestRepository Репозиторий для работы с отборами
     */
    private HarvestRepository $harvests;
    
    /**
     * @var WeighingRepository Репозиторий для работы с навесками
     */
    private WeighingRepository $weighings;

    /**
     * @var FeedRepository Репозиторий кормов
     */
    private FeedRepository $feeds;

    /**
     * @var array<int,array> Кеш загруженных кормов
     */
    private array $feedCache = [];

    /**
     * @var array<string,array|null> Кеш разобранных таблиц кормления (feedId|strategy)
     */
    private array $feedTableCache = [];

    /**
     * @var array Настройки температуры (bad_below, acceptable_min, good_min, good_max, acceptable_max, bad_above)
     */
    private array $temperatureSettings;
    
    /**
     * @var array Настройки кислорода (bad_below, acceptable_min, good_min, good_max, acceptable_max, bad_above)
     */
    private array $oxygenSettings;
    
    /**
     * @var int Тайм-аут предупреждения для измерений (в минутах)
     */
    private int $measurementWarningMinutes;
    
    /**
     * @var int Тайм-аут предупреждения для навесок (в минутах)
     */
    private int $weighingWarningMinutes;
    
    /**
     * @var int Период расчета смертности (в часах)
     */
    private int $mortalityHours;
    
    /**
     * @var int Порог смертности для зеленого статуса
     */
    private int $mortalityThresholdGreen;
    
    /**
     * @var int Порог смертности для желтого статуса
     */
    private int $mortalityThresholdYellow;

    /**
     * Конструктор сервиса
     * 
     * Инициализирует репозитории и загружает настройки из системы.
     * 
     * @param PDO $pdo Подключение к базе данных
     */
    public function __construct(PDO $pdo)
    {
        $this->pools = new PoolRepository($pdo);
        $this->sessions = new SessionRepository($pdo);
        $this->measurements = new MeasurementRepository($pdo);
        $this->mortality = new MortalityRepository($pdo);
        $this->harvests = new HarvestRepository($pdo);
        $this->weighings = new WeighingRepository($pdo);
        $this->feeds = new FeedRepository($pdo);

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

        $weighingWarningDays = \getSettingInt('weighing_warning_days', 3);
        $this->weighingWarningMinutes = max(0, $weighingWarningDays * 24 * 60);
        $this->measurementWarningMinutes = max(0, \getSettingInt('measurement_warning_timeout_minutes', 60));
        $this->mortalityHours = \getSettingInt('mortality_calculation_hours', 24);
        $this->mortalityThresholdGreen = \getSettingInt('mortality_threshold_green', 5);
        $this->mortalityThresholdYellow = \getSettingInt('mortality_threshold_yellow', 10);
    }

    /**
     * Получает список активных бассейнов с их статусами
     * 
     * Для каждого бассейна рассчитывает:
     * - статус измерений (температура, кислород)
     * - статус навесок и отборов
     * - статус смертности
     * - информацию об активной сессии
     * 
     * @return array Массив бассейнов с расчетными статусами
     */
    public function getPools(): array
    {
        $pools = $this->pools->listActive();
        $result = [];

        foreach ($pools as $poolRow) {
            $poolId = (int)$poolRow['id'];
            $pool = new WorkPool([
                'id' => $poolId,
                'name' => $poolRow['name'],
            ]);

            $sessionData = $this->sessions->findActiveByPool($poolId);
            if ($sessionData) {
                $pool->active_session = $this->buildSessionSummary($poolId, $sessionData);
            } else {
                $pool->active_session = null;
            }

            $result[] = $pool->toArray();
        }

        return $result;
    }

    private function buildSessionSummary(int $poolId, array $sessionRow): array
    {
        $session = new SessionSummary([
            'id' => (int)$sessionRow['id'],
            'name' => $sessionRow['name'] ?? null,
            'start_date' => $sessionRow['start_date'] ?? null,
            'start_mass' => isset($sessionRow['start_mass']) ? (float)$sessionRow['start_mass'] : null,
            'start_fish_count' => isset($sessionRow['start_fish_count']) ? (int)$sessionRow['start_fish_count'] : null,
            'planting_name' => $sessionRow['planting_name'] ?? null,
            'planting_fish_breed' => $sessionRow['planting_fish_breed'] ?? null,
            'feed_id' => isset($sessionRow['feed_id']) ? (int)$sessionRow['feed_id'] : null,
            'feed_name' => $sessionRow['feed_name'] ?? null,
            'feeding_strategy' => $sessionRow['feeding_strategy'] ?? null,
            'daily_feedings' => isset($sessionRow['daily_feedings']) ? (int)$sessionRow['daily_feedings'] : null,
        ]);

        $startDate = $session->start_date ?? $sessionRow['created_at'] ?? null;
        if (!$startDate) {
            throw new ValidationException('start_date', 'Дата начала сессии отсутствует', 400);
        }

        $sessionId = (int)$sessionRow['id'];
        $this->applyWeighingInfo($session, $poolId, $startDate, $sessionId);
        $this->applyMeasurementInfo($session, $poolId);
        $this->applyMortalityInfo($session, $sessionRow['id']);
        $this->applyCurrentLoad($session, $sessionRow['id']);
        $this->applyFeedingPlan($session, $sessionRow, $poolId);

        return $session->toArray();
    }

    private function applyWeighingInfo(SessionSummary $session, int $poolId, string $startDate, int $sessionId): void
    {
        // ВАЖНО: Используем только навески с session_id текущей сессии
        // Это гарантирует, что мы не используем навески от других сессий
        $lastWeighing = $this->weighings->findLatestForSession($sessionId);
        
        // Если навесок с session_id нет, НЕ используем старый метод findLatestSince,
        // так как он может найти навески от других сессий в том же бассейне
        // Вместо этого используем начальные данные сессии

        $session->avg_fish_weight = null;
        $session->avg_weight_source = null;

        if ($lastWeighing && (int)$lastWeighing['fish_count'] > 0) {
            $session->avg_fish_weight = (float)$lastWeighing['weight'] / (int)$lastWeighing['fish_count'];
            $session->avg_weight_source = 'weighing';
            
            // Отладка для бассейна 9
            if ($poolId == 9) {
                $debugInfo = sprintf(
                    "[WEIGHING DEBUG pool=9] Found weighing: weight=%.2f kg, count=%d, avg=%.3f kg (%.1f g), date=%s, source=weighing\n",
                    $lastWeighing['weight'],
                    $lastWeighing['fish_count'],
                    $session->avg_fish_weight,
                    $session->avg_fish_weight * 1000,
                    $lastWeighing['recorded_at'] ?? 'unknown'
                );
                file_put_contents(__DIR__ . '/../../storage/feeding_debug.log', date('Y-m-d H:i:s') . ' ' . $debugInfo, FILE_APPEND);
            }
        } elseif ($session->start_mass !== null && $session->start_fish_count && $session->start_fish_count > 0) {
            $session->avg_fish_weight = $session->start_mass / $session->start_fish_count;
            $session->avg_weight_source = 'session';
            
            // Отладка для бассейна 9
            if ($poolId == 9) {
                $debugInfo = sprintf(
                    "[WEIGHING DEBUG pool=9] Using start data: mass=%.2f kg, count=%d, avg=%.3f kg (%.1f g), source=session\n",
                    $session->start_mass,
                    $session->start_fish_count,
                    $session->avg_fish_weight,
                    $session->avg_fish_weight * 1000
                );
                file_put_contents(__DIR__ . '/../../storage/feeding_debug.log', date('Y-m-d H:i:s') . ' ' . $debugInfo, FILE_APPEND);
            }
        }

        $session->last_weighing_at = $lastWeighing['recorded_at'] ?? null;
        [$diffMinutes, $diffLabel] = $this->calculateDiff($session->last_weighing_at);
        $session->last_weighing_diff_minutes = $diffMinutes;
        $session->last_weighing_diff_label = $diffLabel;
        $session->weighing_warning = $diffMinutes === null || $diffMinutes > $this->weighingWarningMinutes;
    }

    private function applyMeasurementInfo(SessionSummary $session, int $poolId): void
    {
        $measurements = $this->measurements->getLatestForPool($poolId, 2);

        if (empty($measurements)) {
            $session->last_measurement_at = null;
            $session->last_measurement_diff_minutes = null;
            $session->last_measurement_diff_label = $this->formatDiffLabel(null);
            $session->measurement_warning = true;
            $session->measurement_warning_label = null;
            $session->last_measurement = null;
            $session->previous_measurement = null;
            return;
        }

        $latest = $measurements[0];
        $previous = $measurements[1] ?? null;

        $session->last_measurement_at = $latest['measured_at'];
        [$diffMinutes, $diffLabel] = $this->calculateDiff($session->last_measurement_at);
        $session->last_measurement_diff_minutes = $diffMinutes;
        $session->last_measurement_diff_label = $diffLabel;
        $session->measurement_warning = $diffMinutes !== null && $diffMinutes > $this->measurementWarningMinutes;
        $session->measurement_warning_label = $session->measurement_warning ? $diffLabel : null;

        $lastTemp = $latest['temperature'] !== null ? (float)$latest['temperature'] : null;
        $lastOxygen = $latest['oxygen'] !== null ? (float)$latest['oxygen'] : null;

        $prevTemp = $previous && $previous['temperature'] !== null ? (float)$previous['temperature'] : null;
        $prevOxygen = $previous && $previous['oxygen'] !== null ? (float)$previous['oxygen'] : null;

        $session->last_measurement = [
            'temperature' => $lastTemp,
            'oxygen' => $lastOxygen,
            'temperature_stratum' => $lastTemp !== null ? $this->getValueStratum($lastTemp, $this->temperatureSettings) : null,
            'oxygen_stratum' => $lastOxygen !== null ? $this->getValueStratum($lastOxygen, $this->oxygenSettings) : null,
            'temperature_trend' => $this->compareTrend($lastTemp, $prevTemp),
            'temperature_trend_direction' => $this->getTrendDirection($lastTemp, $prevTemp, $this->temperatureSettings),
            'oxygen_trend' => $this->compareTrend($lastOxygen, $prevOxygen),
            'oxygen_trend_direction' => $this->getTrendDirection($lastOxygen, $prevOxygen, $this->oxygenSettings),
        ];

        $session->previous_measurement = $previous ? [
            'temperature' => $prevTemp,
            'oxygen' => $prevOxygen,
        ] : null;
    }

    private function applyMortalityInfo(SessionSummary $session, int $sessionId): void
    {
        $count = $this->mortality->sumCountForHours($sessionId, $this->mortalityHours);
        $colorClass = 'text-danger';
        if ($count <= $this->mortalityThresholdGreen) {
            $colorClass = 'text-success';
        } elseif ($count <= $this->mortalityThresholdYellow) {
            $colorClass = 'text-warning';
        }

        $session->mortality_last_hours = [
            'hours' => $this->mortalityHours,
            'total_count' => $count,
            'color_class' => $colorClass,
        ];
    }

    private function applyCurrentLoad(SessionSummary $session, int $sessionId): void
    {
        $startMass = $session->start_mass;
        $startFishCount = $session->start_fish_count;

        if ($startMass === null && $startFishCount === null) {
            $session->current_load = null;
            return;
        }

        $mortalityTotals = $this->mortality->sumForSession($sessionId);
        $harvestTotals = $this->harvests->sumForSession($sessionId);

        $currentWeight = null;
        $currentFishCount = null;
        $weightApproximate = false;

        if ($startMass !== null) {
            $currentWeight = max(0, $startMass - $mortalityTotals['total_weight'] - $harvestTotals['total_weight']);
        }
        if ($startFishCount !== null) {
            $currentFishCount = max(0, $startFishCount - $mortalityTotals['total_count'] - $harvestTotals['total_count']);
        }

        if ($session->avg_weight_source === 'weighing' && $session->avg_fish_weight !== null && $currentFishCount !== null) {
            $currentWeight = max(0, $session->avg_fish_weight * $currentFishCount);
            $weightApproximate = true;
        }

        $session->current_load = [
            'weight' => $currentWeight,
            'fish_count' => $currentFishCount,
            'weight_is_approximate' => $weightApproximate,
        ];
    }

    /**
     * Рассчитывает рекомендованный объём кормления для текущей сессии
     *
     * Алгоритм:
     * 1. Проверяем, что у сессии задан корм, стратегия и количество кормлений в день.
     * 2. Собираем исходные данные: последнюю температуру воды, средний вес рыбы и текущую биомассу.
     * 3. Читаем YAML-таблицу из карточки корма, определяем подходящий диапазон веса и температуры.
     * 4. Таблица хранит норму в «кг корма на 100 кг биомассы в сутки». Приводим к фактической биомассе
     *    и делим на количество кормлений, получая норму на одно кормление.
     * 5. Все шаги защищены проверками и логированием, чтобы карточка сессии оставалась стабильной
     *    даже при некорректных данных или неполных таблицах.
     */
    private function applyFeedingPlan(SessionSummary $session, array $sessionRow, int $poolId): void
    {
        $session->feeding_plan = null;
        $session->feed_ratio = null;

        $feedId = $session->feed_id;
        $strategy = $session->feeding_strategy ?: $sessionRow['feeding_strategy'] ?? null;
        if (!$feedId || !$strategy) {
            return;
        }

        $dailyFeedings = (int)($sessionRow['daily_feedings'] ?? $session->daily_feedings ?? 0);
        if ($dailyFeedings <= 0) {
            return;
        }

        $temperature = $session->last_measurement['temperature'] ?? null;
        $avgWeightKg = $session->avg_fish_weight;
        $biomassKg = $session->current_load['weight'] ?? null;

        if ($temperature === null || $avgWeightKg === null || $biomassKg === null) {
            return;
        }

        $feed = $this->getFeed($feedId);
        if (!$feed) {
            return;
        }
        $session->feed_name = $feed['name'] ?? $session->feed_name;

        // Используем одну таблицу для всех стратегий (берем из formula_normal)
        $table = $this->getFeedTable($feed);
        if (!$table) {
            return;
        }

        $avgWeightGrams = $avgWeightKg * 1000;
        // Вычисляем коэффициент с учетом стратегии кормления
        $match = FeedTableParser::resolveRateWithStrategy($table, (float)$temperature, (float)$avgWeightGrams, $strategy);
        if (!$match) {
            return;
        }

        $ratioPer100Kg = max(0, (float)$match['value']);
        
        // ВРЕМЕННАЯ ОТЛАДКА для бассейна 9 - записываем в файл для удобства
        if ($poolId == 9) {
            $debugInfo = sprintf(
                "[FEEDING DEBUG pool=9] temp=%.2f°C, weight_kg=%.3f, weight_g=%.1f, strategy=%s, range=%s, temp_range=[%.1f-%.1f]°C, coeff=[%.2f-%.2f], final=%.4f (rounded=%.2f), biomass=%.2f kg\n",
                $temperature,
                $avgWeightKg,
                $avgWeightGrams,
                $strategy,
                $match['weight_label'] ?? 'unknown',
                $match['temp_lower'] ?? 0,
                $match['temp_upper'] ?? 0,
                $match['coeff_lower'] ?? 0,
                $match['coeff_upper'] ?? 0,
                $ratioPer100Kg,
                round($ratioPer100Kg, 2),
                $biomassKg
            );
            error_log($debugInfo);
            // Также записываем в отдельный файл для удобства
            file_put_contents(__DIR__ . '/../../storage/feeding_debug.log', date('Y-m-d H:i:s') . ' ' . $debugInfo, FILE_APPEND);
        }
        $recommendedPerDay = max(0, ($ratioPer100Kg / 100) * (float)$biomassKg);
        $perFeeding = max(0, $recommendedPerDay / $dailyFeedings);
        $session->feed_ratio = $ratioPer100Kg;

        $session->feeding_plan = [
            'feed_name' => $session->feed_name,
            'strategy' => $strategy,
            'strategy_label' => $this->getStrategyLabel($strategy),
            'unit' => $table['unit'] ?? null,
            'ratio_per_100kg' => $ratioPer100Kg,
            'temperature_slot' => $match['temperature'],
            'weight_label' => $match['weight_label'],
            'daily_amount' => $recommendedPerDay,
            'per_feeding' => $perFeeding,
            'temperature' => (float)$temperature,
            'avg_weight_kg' => $avgWeightKg,
            'avg_weight_g' => $avgWeightGrams,
            'biomass_kg' => (float)$biomassKg,
        ];
    }

    private function getFeed(int $feedId): ?array
    {
        if (!isset($this->feedCache[$feedId])) {
            $this->feedCache[$feedId] = $this->feeds->findById($feedId) ?: null;
        }

        return $this->feedCache[$feedId];
    }

    /**
     * Получает таблицу кормления из корма.
     * Используется одна таблица (formula_normal) для всех стратегий.
     *
     * @param array $feed Данные корма
     * @return array|null Распарсенная таблица или null
     */
    private function getFeedTable(array $feed): ?array
    {
        $feedId = (int)($feed['id'] ?? 0);
        $cacheKey = $feedId . '|table';

        if (array_key_exists($cacheKey, $this->feedTableCache)) {
            return $this->feedTableCache[$cacheKey];
        }

        // Используем formula_normal как основную таблицу для всех стратегий
        $rawYaml = $feed['formula_normal'] ?? null;
        if (!$rawYaml) {
            $this->feedTableCache[$cacheKey] = null;
            return null;
        }

        try {
            $table = FeedTableParser::parse($rawYaml);
        } catch (\Throwable $e) {
            error_log('Feed table parse error for feed #' . $feedId . ': ' . $e->getMessage());
            $table = null;
        }

        $this->feedTableCache[$cacheKey] = $table;
        return $table;
    }

    private function getStrategyLabel(string $strategy): string
    {
        return match ($strategy) {
            'econom' => 'Эконом',
            'growth' => 'Рост',
            default => 'Норма',
        };
    }

    private function calculateDiff(?string $timestamp): array
    {
        if (!$timestamp) {
            return [null, $this->formatDiffLabel(null)];
        }

        try {
            $dateTime = new DateTime($timestamp);
        } catch (\Exception $e) {
            return [null, $this->formatDiffLabel(null)];
        }

        $now = new DateTime();
        $diffMinutes = max(0, (int)floor(($now->getTimestamp() - $dateTime->getTimestamp()) / 60));
        return [$diffMinutes, $this->formatDiffLabel($diffMinutes)];
    }

    private function formatDiffLabel(?int $minutes): string
    {
        if ($minutes === null) {
            return 'ещё не проводился';
        }
        if ($minutes <= 1) {
            return 'менее минуты';
        }

        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;
        $parts = [];

        if ($days > 0) {
            $parts[] = $days . ' ' . $this->plural($days, ['день', 'дня', 'дней']);
        }
        if ($hours > 0) {
            $parts[] = $hours . ' ' . $this->plural($hours, ['час', 'часа', 'часов']);
        }
        if ($mins > 0 && $days === 0) {
            $parts[] = $mins . ' ' . $this->plural($mins, ['минута', 'минуты', 'минут']);
        }

        if (empty($parts)) {
            $parts[] = 'менее минуты';
        }

        return implode(' ', $parts);
    }

    private function plural(int $number, array $forms): string
    {
        $number = abs($number) % 100;
        $n1 = $number % 10;
        if ($number > 10 && $number < 20) {
            return $forms[2];
        }
        if ($n1 > 1 && $n1 < 5) {
            return $forms[1];
        }
        if ($n1 === 1) {
            return $forms[0];
        }
        return $forms[2];
    }

    private function getValueStratum(float $value, array $settings): string
    {
        if ($value < $settings['bad_below'] || $value > $settings['bad_above']) {
            return 'bad';
        }
        if (($value >= $settings['acceptable_min'] && $value < $settings['good_min']) ||
            ($value > $settings['good_max'] && $value <= $settings['acceptable_max'])) {
            return 'acceptable';
        }
        if ($value >= $settings['good_min'] && $value <= $settings['good_max']) {
            return 'good';
        }
        return 'bad';
    }

    private function getTrendDirection(?float $currentValue, ?float $previousValue, array $settings): ?string
    {
        if ($currentValue === null || $previousValue === null) {
            return null;
        }

        $currentStratum = $this->getValueStratum($currentValue, $settings);
        $previousStratum = $this->getValueStratum($previousValue, $settings);

        $goodCenter = ($settings['good_min'] + $settings['good_max']) / 2;
        $currentDistance = abs($currentValue - $goodCenter);
        $previousDistance = abs($previousValue - $goodCenter);

        if ($currentDistance < $previousDistance) {
            return 'improving';
        }
        if ($currentDistance > $previousDistance) {
            return 'worsening';
        }

        if ($currentStratum === 'good' && $previousStratum !== 'good') {
            return 'improving';
        }
        if ($currentStratum !== 'good' && $previousStratum === 'good') {
            return 'worsening';
        }
        return null;
    }

    private function compareTrend(?float $currentValue, ?float $previousValue): ?string
    {
        if ($currentValue === null || $previousValue === null) {
            return null;
        }
        if ($currentValue > $previousValue) {
            return 'up';
        }
        if ($currentValue < $previousValue) {
            return 'down';
        }
        return 'same';
    }
}


