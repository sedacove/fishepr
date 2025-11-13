<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы фонда заработной платы (ФЗП)
 * 
 * Отвечает за отображение страницы ФЗП:
 * - управление зарплатами сотрудников
 * - расчеты и отчеты по ФЗП
 * 
 * Доступна только администраторам.
 */
class PayrollController extends Controller
{
    /**
     * Конструктор контроллера
     * 
     * Проверяет авторизацию и права администратора
     */
    public function __construct()
    {
        requireAuth();
        requireAdmin();
    }

    /**
     * Отображает страницу ФЗП
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        return $this->view('payroll.index', [
            'pageTitle' => 'ФЗП',
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
            'extra_body_scripts' => ['assets/js/pages/payroll.js'],
        ]);
    }
}

