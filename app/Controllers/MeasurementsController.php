<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы замеров
 * 
 * Отвечает за отображение страницы с замерами:
 * - список бассейнов с вкладками
 * - список замеров для выбранного бассейна
 * - графики температуры и кислорода
 * - формы для добавления/редактирования замеров
 */
class MeasurementsController extends Controller
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
     * Отображает страницу замеров
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
        ];

        return $this->view('measurements.index', [
            'pageTitle' => 'Замеры',
            'measurementsConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/measurements.js'],
        ]);
    }
}
