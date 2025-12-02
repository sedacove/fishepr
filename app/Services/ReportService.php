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
    private HarvestRepository $harvests;
    private CounterpartyRepository $counterparties;
    private PlantingRepository $plantings;

    public function __construct(PDO $pdo)
    {
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

