<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/settings.php';

/**
 * Контроллер страницы "Работа"
 * 
 * Отвечает за отображение страницы "Работа":
 * - список активных бассейнов с их статусами
 * - статусы измерений (температура, кислород)
 * - статусы навесок и отборов
 * - статусы смертности
 * - информация об активных сессиях
 */
class WorkController extends Controller
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
     * Отображает страницу "Работа"
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'maxPoolCapacityKg' => (float) getSetting('max_pool_capacity_kg', 5000),
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
        ];

        return $this->view('work.index', [
            'pageTitle' => 'Рабочая',
            'workConfig' => $config,
            'extra_styles' => ['assets/css/pool_blocks.css'],
            'extra_body_scripts' => ['assets/js/pages/work.js'],
        ]);
    }
}

