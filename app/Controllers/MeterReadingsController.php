<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';

/**
 * Контроллер страницы показаний приборов учета
 * 
 * Отвечает за отображение страницы с показаниями приборов учета:
 * - список приборов
 * - список показаний для выбранного прибора
 * - формы для добавления/редактирования показаний
 */
class MeterReadingsController extends Controller
{
    /**
     * Конструктор контроллера
     * 
     * Проверяет авторизацию пользователя
     */
    public function __construct()
    {
        requireAuth();
    }

    /**
     * Отображает страницу показаний приборов учета
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $isAdmin = isAdmin();

        return $this->view('meter_readings.index', [
            'pageTitle' => 'Показания приборов учета',
            'isAdmin' => $isAdmin,
            'baseUrl' => BASE_URL,
            'extra_body_scripts' => ['assets/js/pages/meter_readings.js'],
        ]);
    }
}
