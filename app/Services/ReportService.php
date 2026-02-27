<?php

namespace App\Services;

use App\Repositories\HarvestRepository;
use App\Repositories\CounterpartyRepository;
use App\Repositories\PlantingRepository;
use PDO;

/**
 * Сервис для формирования отчетов
 * 
 * Содержит бизнес-логику для генерации различных отчетов.
 */
class ReportService
{
    private PDO $pdo;
    private HarvestRepository $harvests;
    private CounterpartyRepository $counterparties;
    private PlantingRepository $plantings;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->harvests = new HarvestRepository($pdo);
        $this->counterparties = new CounterpartyRepository($pdo);
        $this->plantings = new PlantingRepository($pdo);
    }

    /**
     * Формирует отчет по отборам
     * 
     * @param string|null $dateFrom Дата начала периода (формат YYYY-MM-DD)
     * @param string|null $dateTo Дата окончания периода (формат YYYY-MM-DD)
     * @param array|null $counterpartyIds Массив ID контрагентов (null для всех)
     * @param int|null $plantingId ID посадки (null для всех)
     * @return array Данные отчета: список отборов, итоги, информация о фильтрах
     */
    public function getHarvestsReport(?string $dateFrom, ?string $dateTo, ?array $counterpartyIds, ?int $plantingId): array
    {
        // Валидация дат
        if ($dateFrom && !$this->isValidDate($dateFrom)) {
            throw new \InvalidArgumentException('Неверный формат даты начала');
        }
        if ($dateTo && !$this->isValidDate($dateTo)) {
            throw new \InvalidArgumentException('Неверный формат даты окончания');
        }

        // Получаем отборы с фильтрами
        $harvests = $this->harvests->listForReport($dateFrom, $dateTo, $counterpartyIds, $plantingId);

        // Вычисляем итоги
        $totalWeight = 0.0;
        $totalFishCount = 0;
        foreach ($harvests as $harvest) {
            $totalWeight += (float)$harvest['weight'];
            $totalFishCount += (int)$harvest['fish_count'];
        }

        // Получаем информацию о фильтрах для отображения
        $counterpartyNames = [];
        if ($counterpartyIds !== null && !empty($counterpartyIds)) {
            foreach ($counterpartyIds as $counterpartyId) {
                $counterparty = $this->counterparties->findById($counterpartyId);
                if ($counterparty) {
                    $counterpartyNames[] = $counterparty['name'];
                }
            }
        }

        $plantingName = null;
        if ($plantingId !== null) {
            $planting = $this->plantings->find($plantingId);
            $plantingName = $planting ? $planting->name : null;
        }

        return [
            'harvests' => $harvests,
            'totals' => [
                'weight' => $totalWeight,
                'fish_count' => $totalFishCount,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'counterparty_ids' => $counterpartyIds,
                'counterparty_names' => $counterpartyNames,
                'planting_id' => $plantingId,
                'planting_name' => $plantingName,
            ],
        ];
    }

    /**
     * Формирует данные для отчета по росту посадки
     *
     * По заданной посадке ищет все сессии (включая завершенные),
     * выбирает все навески этих сессий и возвращает точки вида:
     * - дата навески
     * - средняя навеска (кг и г)
     * - бассейн и сессия
     *
     * @param int $plantingId ID посадки
     * @param string|null $dateFrom дата начала периода (YYYY-MM-DD) или null
     * @param string|null $dateTo дата окончания периода (YYYY-MM-DD) или null
     * @return array
     */
    public function getPlantingGrowthReport(int $plantingId, ?string $dateFrom, ?string $dateTo): array
    {
        if ($plantingId <= 0) {
            throw new \InvalidArgumentException('Не указан идентификатор посадки');
        }

        if ($dateFrom && !$this->isValidDate($dateFrom)) {
            throw new \InvalidArgumentException('Неверный формат даты начала');
        }
        if ($dateTo && !$this->isValidDate($dateTo)) {
            throw new \InvalidArgumentException('Неверный формат даты окончания');
        }

        $planting = $this->plantings->find($plantingId);
        if (!$planting) {
            throw new \InvalidArgumentException('Посадка не найдена');
        }

        $params = [':planting_id' => $plantingId];
        $conditions = ['s.planting_id = :planting_id'];

        if ($dateFrom) {
            $conditions[] = 'DATE(w.recorded_at) >= :date_from';
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo) {
            $conditions[] = 'DATE(w.recorded_at) <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        $whereSql = implode(' AND ', $conditions);

        $sql = "
            SELECT 
                w.recorded_at,
                w.weight,
                w.fish_count,
                s.id AS session_id,
                s.name AS session_name,
                s.is_completed,
                p.name AS pool_name
            FROM weighings w
            INNER JOIN sessions s ON s.id = w.session_id
            INNER JOIN pools p ON p.id = w.pool_id
            WHERE {$whereSql}
            ORDER BY w.recorded_at ASC, w.id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $points = [];
        foreach ($rows as $row) {
            $weight = (float)($row['weight'] ?? 0);
            $fishCount = (int)($row['fish_count'] ?? 0);
            $avgKg = null;
            $avgG = null;

            if ($fishCount > 0 && $weight > 0) {
                $avgKg = $weight / $fishCount;
                $avgG = $avgKg * 1000;
            }

            $points[] = [
                'recorded_at' => $row['recorded_at'],
                'weight' => $weight,
                'fish_count' => $fishCount,
                'avg_weight_kg' => $avgKg,
                'avg_weight_g' => $avgG,
                'session_id' => (int)$row['session_id'],
                'session_name' => $row['session_name'],
                'session_is_completed' => (bool)$row['is_completed'],
                'pool_name' => $row['pool_name'],
                'is_initial' => false,
                'is_planting' => false,
            ];
        }

        // Самое первое значение — из свойств самой посадки (дата посадки, биомасса, количество)
        $plantingPoint = null;
        if ($planting->fish_count > 0 && $planting->biomass_weight !== null && (float)$planting->biomass_weight > 0) {
            $bio = (float)$planting->biomass_weight;
            $cnt = (int)$planting->fish_count;
            $plantingPoint = [
                'recorded_at' => $planting->planting_date . ' 00:00:00',
                'weight' => $bio,
                'fish_count' => $cnt,
                'avg_weight_kg' => $bio / $cnt,
                'avg_weight_g' => ($bio / $cnt) * 1000,
                'session_id' => null,
                'session_name' => null,
                'pool_name' => null,
                'is_initial' => false,
                'is_planting' => true,
            ];
        }

        // Первоначальные данные из первой по дате сессии этой посадки
        $initial = null;
        $stmtInitial = $this->pdo->prepare(
            'SELECT s.id, s.start_date, s.start_mass, s.start_fish_count, s.name AS session_name, p.name AS pool_name
             FROM sessions s
             INNER JOIN pools p ON p.id = s.pool_id
             WHERE s.planting_id = ?
             ORDER BY s.start_date ASC, s.id ASC
             LIMIT 1'
        );
        $stmtInitial->execute([$plantingId]);
        $firstSession = $stmtInitial->fetch(PDO::FETCH_ASSOC);
        if ($firstSession && (int)($firstSession['start_fish_count'] ?? 0) > 0 && isset($firstSession['start_mass'])) {
            $startMass = (float)$firstSession['start_mass'];
            $startCount = (int)$firstSession['start_fish_count'];
            $initial = [
                'recorded_at' => $firstSession['start_date'] . ' 00:00:00',
                'weight' => $startMass,
                'fish_count' => $startCount,
                'avg_weight_kg' => $startMass / $startCount,
                'avg_weight_g' => ($startMass / $startCount) * 1000,
                'session_id' => (int)$firstSession['id'],
                'session_name' => $firstSession['session_name'],
                'pool_name' => $firstSession['pool_name'],
                'is_initial' => true,
                'is_planting' => false,
            ];
        }

        // Первоначальные данные из самой посадки (для блока над графиком)
        $initialWeightKg = $planting->biomass_weight !== null ? (float)$planting->biomass_weight : null;
        $initialFishCount = (int)$planting->fish_count;
        $initialAvgG = ($initialWeightKg !== null && $initialFishCount > 0)
            ? ($initialWeightKg / $initialFishCount) * 1000
            : null;

        // Дней с момента посадки
        $plantingDate = new \DateTimeImmutable($planting->planting_date);
        $today = new \DateTimeImmutable('today');
        $daysSincePlanting = $plantingDate->diff($today)->days;
        if ($plantingDate > $today) {
            $daysSincePlanting = -$daysSincePlanting;
        }

        // Текущая биомасса и средняя навеска — только по активным сессиям в активных бассейнах (как на «Рабочей»)
        $stmtCurrent = $this->pdo->prepare(
            'SELECT 
                s.id,
                s.start_mass,
                s.start_fish_count,
                (SELECT COALESCE(SUM(h.weight), 0) FROM harvests h WHERE h.session_id = s.id) AS harvest_kg,
                (SELECT COALESCE(SUM(h.fish_count), 0) FROM harvests h WHERE h.session_id = s.id) AS harvest_cnt,
                (SELECT COALESCE(SUM(m.weight), 0) FROM mortality m WHERE m.session_id = s.id) AS mort_kg,
                (SELECT COALESCE(SUM(m.fish_count), 0) FROM mortality m WHERE m.session_id = s.id) AS mort_cnt
             FROM sessions s
             INNER JOIN pools p ON p.id = s.pool_id AND p.is_active = 1
             WHERE s.planting_id = ? AND s.is_completed = 0'
        );
        $stmtCurrent->execute([$plantingId]);
        $sessionsRows = $stmtCurrent->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $currentBiomassKg = 0.0;
        $currentFishCount = 0;
        foreach ($sessionsRows as $row) {
            $startMass = (float)($row['start_mass'] ?? 0);
            $startCnt = (int)($row['start_fish_count'] ?? 0);
            $hKg = (float)($row['harvest_kg'] ?? 0);
            $hCnt = (int)($row['harvest_cnt'] ?? 0);
            $mKg = (float)($row['mort_kg'] ?? 0);
            $mCnt = (int)($row['mort_cnt'] ?? 0);
            $currentBiomassKg += max(0, $startMass - $hKg - $mKg);
            $currentFishCount += max(0, $startCnt - $hCnt - $mCnt);
        }
        $currentAvgG = ($currentFishCount > 0 && $currentBiomassKg >= 0)
            ? ($currentBiomassKg / $currentFishCount) * 1000
            : null;

        // Итого отгрузки и падеж по данной посадке
        $stmtHarvest = $this->pdo->prepare(
            'SELECT COALESCE(SUM(h.weight), 0) AS total_kg, COALESCE(SUM(h.fish_count), 0) AS total_cnt
             FROM harvests h INNER JOIN sessions s ON s.id = h.session_id WHERE s.planting_id = ?'
        );
        $stmtHarvest->execute([$plantingId]);
        $harvestRow = $stmtHarvest->fetch(PDO::FETCH_ASSOC);
        $totalHarvestKg = (float)($harvestRow['total_kg'] ?? 0);
        $totalHarvestCnt = (int)($harvestRow['total_cnt'] ?? 0);

        $stmtMortality = $this->pdo->prepare(
            'SELECT COALESCE(SUM(m.weight), 0) AS total_kg, COALESCE(SUM(m.fish_count), 0) AS total_cnt
             FROM mortality m INNER JOIN sessions s ON s.id = m.session_id WHERE s.planting_id = ?'
        );
        $stmtMortality->execute([$plantingId]);
        $mortalityRow = $stmtMortality->fetch(PDO::FETCH_ASSOC);
        $totalMortalityKg = (float)($mortalityRow['total_kg'] ?? 0);
        $totalMortalityCnt = (int)($mortalityRow['total_cnt'] ?? 0);

        return [
            'planting' => [
                'id' => $planting->id,
                'name' => $planting->name,
                'fish_breed' => $planting->fish_breed,
                'planting_date' => $planting->planting_date,
                'is_archived' => $planting->is_archived ?? null,
            ],
            'planting_point' => $plantingPoint,
            'initial' => $initial,
            'points' => $points,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'summary' => [
                'current_biomass_kg' => $currentBiomassKg,
                'current_avg_weight_g' => $currentAvgG,
                'initial_weight_kg' => $initialWeightKg,
                'initial_avg_weight_g' => $initialAvgG,
                'days_since_planting' => $daysSincePlanting,
                'total_harvest_kg' => $totalHarvestKg,
                'total_harvest_count' => $totalHarvestCnt,
                'total_mortality_kg' => $totalMortalityKg,
                'total_mortality_count' => $totalMortalityCnt,
            ],
        ];
    }

    /**
     * Проверяет валидность даты в формате YYYY-MM-DD
     * 
     * @param string $date Дата для проверки
     * @return bool true если дата валидна, false иначе
     */
    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

