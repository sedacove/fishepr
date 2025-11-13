<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы управления посадками
 * 
 * Отвечает за отображение страницы управления посадками:
 * - список активных и архивных посадок
 * - формы для добавления/редактирования посадок
 * - управление файлами посадок
 * 
 * Доступна только администраторам.
 */
class PlantingsController extends Controller
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
     * Отображает страницу управления посадками
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'baseUrl' => BASE_URL,
        ];

        return $this->view('plantings.index', [
            'pageTitle' => 'Управление посадками',
            'plantingsConfig' => $config,
            'extra_styles' => ['assets/css/pages/plantings.css'],
            'extra_body_scripts' => ['assets/js/pages/plantings.js'],
        ]);
    }
}


