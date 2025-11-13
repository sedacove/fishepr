<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы календаря дежурств
 * 
 * Отвечает за отображение страницы календаря дежурств:
 * - календарь с назначенными дежурными
 * - управление дежурствами (только для админов)
 * - отображение дежурств на текущую неделю
 */
class DutyCalendarController extends Controller
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
     * Отображает страницу календаря дежурств
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        return $this->view('duty_calendar.index', [
            'pageTitle' => 'Календарь дежурств',
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
            'extra_styles' => ['assets/css/pages/duty_calendar.css'],
            'extra_body_scripts' => ['assets/js/pages/duty_calendar.js'],
        ]);
    }
}

