<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы приборов учета
 * 
 * Отвечает за отображение страницы управления приборами учета:
 * - список всех приборов
 * - формы для добавления/редактирования приборов
 * 
 * Доступна только администраторам.
 */
class MetersController extends Controller
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
     * Отображает страницу приборов учета
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'baseUrl' => BASE_URL,
        ];

        return $this->view('meters.index', [
            'pageTitle' => 'Приборы учета',
            'metersConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/meters.js'],
        ]);
    }
}


