<?php

namespace App\Services;

use App\Models\Work\SessionSummary;
use App\Models\Work\WorkPool;
use App\Repositories\HarvestRepository;
use App\Repositories\MeasurementRepository;
use App\Repositories\MortalityRepository;
use App\Repositories\PoolRepository;
use App\Repositories\SessionRepository;
use App\Repositories\WeighingRepository;
use App\Support\Exceptions\ValidationException;
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
        ]);

        $startDate = $session->start_date ?? $sessionRow['created_at'] ?? null;
        if (!$startDate) {
            throw new ValidationException('start_date', 'Дата начала сессии отсутствует', 400);
        }

        $this->applyWeighingInfo($session, $poolId, $startDate);
        $this->applyMeasurementInfo($session, $poolId);
        $this->applyMortalityInfo($session, $poolId);
        $this->applyCurrentLoad($session, $poolId, $startDate);

        return $session->toArray();
    }

    private function applyWeighingInfo(SessionSummary $session, int $poolId, string $startDate): void
    {
        $lastWeighing = $this->weighings->findLatestSince($poolId, $startDate);

        $session->avg_fish_weight = null;
        $session->avg_weight_source = null;

        if ($lastWeighing && (int)$lastWeighing['fish_count'] > 0) {
            $session->avg_fish_weight = (float)$lastWeighing['weight'] / (int)$lastWeighing['fish_count'];
            $session->avg_weight_source = 'weighing';
        } elseif ($session->start_mass !== null && $session->start_fish_count && $session->start_fish_count > 0) {
            $session->avg_fish_weight = $session->start_mass / $session->start_fish_count;
            $session->avg_weight_source = 'session';
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

    private function applyMortalityInfo(SessionSummary $session, int $poolId): void
    {
        $count = $this->mortality->sumCountForHours($poolId, $this->mortalityHours);
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

    private function applyCurrentLoad(SessionSummary $session, int $poolId, string $startDate): void
    {
        $startMass = $session->start_mass;
        $startFishCount = $session->start_fish_count;

        if ($startMass === null && $startFishCount === null) {
            $session->current_load = null;
            return;
        }

        $mortalityTotals = $this->mortality->sumForPoolSince($poolId, $startDate);
        $harvestTotals = $this->harvests->sumForPoolSince($poolId, $startDate);

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


