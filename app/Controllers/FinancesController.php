<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы финансов
 * 
 * Отвечает за отображение страницы финансов:
 * - управление финансовыми операциями
 * - отчеты и аналитика
 * 
 * Доступна только администраторам.
 */
class FinancesController extends Controller
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
     * Отображает страницу финансов
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        return $this->view('finances.index', [
            'pageTitle' => 'Финансы',
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
            'extra_body_scripts' => ['assets/js/pages/finances.js'],
        ]);
    }
}

