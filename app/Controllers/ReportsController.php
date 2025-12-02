<?php

namespace App\Controllers;

use App\Repositories\CounterpartyRepository;
use App\Repositories\PlantingRepository;

require_once __DIR__ . '/../../includes/auth.php';

/**
 * Контроллер для страниц отчетов
 * 
 * Обрабатывает запросы на страницы отчетов.
 * Все отчеты доступны только администраторам.
 */
class ReportsController extends Controller
{
    private CounterpartyRepository $counterparties;
    private PlantingRepository $plantings;

    public function __construct()
    {
        require_once __DIR__ . '/../../config/database.php';
        $pdo = getDBConnection();
        $this->counterparties = new CounterpartyRepository($pdo);
        $this->plantings = new PlantingRepository($pdo);
    }

    /**
     * Страница отчета по отборам
     * 
     * @return string HTML страницы отчета
     */
    public function harvests(): string
    {
        requireAuth();
        
        if (!isAdmin()) {
            http_response_code(403);
            return 'Доступ запрещен';
        }

        // Получаем списки для фильтров
        $counterparties = $this->counterparties->listAll();
        $plantings = $this->plantings->listByArchived(false);

        return $this->view('reports/harvests', [
            'page_title' => 'Отчет по отборам',
            'counterparties' => $counterparties,
            'plantings' => $plantings,
            'extra_styles' => ['assets/css/pages/reports.css'],
        ]);
    }
}

