<?php

namespace App\Services;

use App\Models\Harvest\HarvestSummary;
use App\Models\Measurement\MeasurementPoint;
use App\Models\Mortality\MortalityPoint;
use App\Models\Session\SessionDetails;
use App\Models\Weighing\WeighingSummary;
use App\Repositories\HarvestRepository;
use App\Repositories\MeasurementRepository;
use App\Repositories\MortalityRepository;
use App\Repositories\SessionRepository;
use App\Repositories\WeighingRepository;
use App\Support\Exceptions\ValidationException;
use PDO;
use RuntimeException;

/**
 * Сервис для работы с деталями сессии
 * 
 * Содержит бизнес-логику для получения полной информации о сессии:
 * - основная информация о сессии (посадка, бассейн)
 * - история измерений (температура, кислород)
 * - история смертности
 * - история отборов
 * - история навесок
 */
class SessionDetailsService
{
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
     * Конструктор сервиса
     * 
     * @param PDO $pdo Подключение к базе данных
     */
    public function __construct(PDO $pdo)
    {
        $this->sessions = new SessionRepository($pdo);
        $this->measurements = new MeasurementRepository($pdo);
        $this->mortality = new MortalityRepository($pdo);
        $this->harvests = new HarvestRepository($pdo);
        $this->weighings = new WeighingRepository($pdo);
    }

    /**
     * Получает полную информацию о сессии со всеми связанными данными
     * 
     * Возвращает:
     * - основную информацию о сессии (посадка, бассейн, даты, массы, FCR)
     * - историю измерений (температура, кислород) с начала сессии
     * - историю смертности (дневные итоги) с начала сессии
     * - историю отборов с начала сессии
     * - историю навесок с начала сессии
     * 
     * @param int $sessionId ID сессии
     * @return array Массив с ключами: session, measurements, mortality, harvests, weighings
     * @throws ValidationException Если ID сессии не указан
     * @throws RuntimeException Если сессия не найдена
     */
    public function getDetails(int $sessionId): array
    {
        if ($sessionId <= 0) {
            throw new ValidationException('id', 'ID сессии не указан', 400);
        }

        $session = $this->sessions->findWithRelations($sessionId);
        if (!$session) {
            throw new RuntimeException('Сессия не найдена', 404);
        }

        $sessionModel = new SessionDetails($session);
        $poolId = (int)$sessionModel->pool_id;
        $startDate = $sessionModel->start_date ?? $sessionModel->created_at ?? date('Y-m-d H:i:s', 0);

        $measurements = array_map(
            fn(array $row) => (new MeasurementPoint([
                'measured_at' => $row['measured_at'],
                'temperature' => $row['temperature'] !== null ? (float)$row['temperature'] : null,
                'oxygen' => $row['oxygen'] !== null ? (float)$row['oxygen'] : null,
                'created_by' => $row['created_by'] !== null ? (int)$row['created_by'] : null,
            ]))->toArray(),
            $this->measurements->listForPoolSince($poolId, $startDate)
        );

        $mortality = array_map(function (array $row) {
            $day = $row['day'];
            return (new MortalityPoint([
                'day' => $day,
                'day_label' => date('d.m.Y', strtotime($day)),
                'total_weight' => (float)($row['total_weight'] ?? 0),
                'total_count' => (int)($row['total_count'] ?? 0),
            ]))->toArray();
        }, $this->mortality->getDailyTotalsForPoolSince($poolId, $startDate));

        $harvests = array_map(function (array $row) {
            return (new HarvestSummary([
                'recorded_at' => $row['recorded_at'],
                'weight' => (float)$row['weight'],
                'fish_count' => (int)$row['fish_count'],
                'counterparty_id' => $row['counterparty_id'] !== null ? (int)$row['counterparty_id'] : null,
                'counterparty_name' => $row['counterparty_name'] ?? null,
                'counterparty_color' => $row['counterparty_color'] ?? null,
            ]))->toArray();
        }, $this->harvests->listForPoolSince($poolId, $startDate));

        $weighings = array_map(function (array $row) {
            $fishCount = (int)$row['fish_count'];
            $weight = (float)$row['weight'];
            $avg = $fishCount > 0 ? $weight / $fishCount : null;
            return (new WeighingSummary([
                'recorded_at' => $row['recorded_at'],
                'weight' => $weight,
                'fish_count' => $fishCount,
                'avg_weight' => $avg,
            ]))->toArray();
        }, $this->weighings->listForPoolSince($poolId, $startDate));

        return [
            'session' => $sessionModel->toArray(),
            'measurements' => $measurements,
            'mortality' => $mortality,
            'harvests' => $harvests,
            'weighings' => $weighings,
        ];
    }
}


