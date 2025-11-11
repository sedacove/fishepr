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

class SessionDetailsService
{
    private SessionRepository $sessions;
    private MeasurementRepository $measurements;
    private MortalityRepository $mortality;
    private HarvestRepository $harvests;
    private WeighingRepository $weighings;

    public function __construct(PDO $pdo)
    {
        $this->sessions = new SessionRepository($pdo);
        $this->measurements = new MeasurementRepository($pdo);
        $this->mortality = new MortalityRepository($pdo);
        $this->harvests = new HarvestRepository($pdo);
        $this->weighings = new WeighingRepository($pdo);
    }

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


