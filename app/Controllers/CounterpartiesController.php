<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы контрагентов
 * 
 * Отвечает за отображение страницы управления контрагентами:
 * - список всех контрагентов
 * - формы для добавления/редактирования контрагентов
 * - управление документами контрагентов
 * 
 * Доступна только администраторам.
 */
class CounterpartiesController extends Controller
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
     * Отображает страницу контрагентов
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'baseUrl' => BASE_URL,
        ];

        return $this->view('counterparties.index', [
            'pageTitle' => 'Контрагенты',
            'counterpartiesConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/counterparties.js'],
        ]);
    }
}
