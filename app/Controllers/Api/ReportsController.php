<?php

namespace App\Controllers\Api;

use App\Services\ReportService;
use App\Support\JsonResponse;

require_once __DIR__ . '/../../../includes/auth.php';

/**
 * API контроллер для отчетов
 * 
 * Обрабатывает запросы на получение данных для отчетов.
 * Все отчеты доступны только администраторам.
 */
class ReportsController
{
    private ReportService $reportService;

    public function __construct()
    {
        require_once __DIR__ . '/../../../config/database.php';
        $pdo = \getDBConnection();
        $this->reportService = new ReportService($pdo);
    }

    /**
     * Обрабатывает запросы к API отчетов
     * 
     * @param string $action Действие (harvests)
     * @return void
     */
    public function handle(string $action): void
    {
        requireAuth();
        
        if (!isAdmin()) {
            JsonResponse::error('Доступ запрещен', 403);
            return;
        }

        switch ($action) {
            case 'harvests':
                $this->getHarvestsReport();
                break;
            default:
                JsonResponse::error('Неизвестное действие', 400);
        }
    }

    /**
     * Получает данные отчета по отборам
     * 
     * @return void
     */
    private function getHarvestsReport(): void
    {
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $counterpartyId = isset($_GET['counterparty_id']) && $_GET['counterparty_id'] !== '' 
            ? (int)$_GET['counterparty_id'] 
            : null;
        $plantingId = isset($_GET['planting_id']) && $_GET['planting_id'] !== '' 
            ? (int)$_GET['planting_id'] 
            : null;

        try {
            $data = $this->reportService->getHarvestsReport($dateFrom, $dateTo, $counterpartyId, $plantingId);
            JsonResponse::success($data);
        } catch (\Exception $e) {
            JsonResponse::error($e->getMessage(), 500);
        }
    }
}

