<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы отборов
 * 
 * Отвечает за отображение страницы с отборами:
 * - список бассейнов с вкладками
 * - список отборов для выбранного бассейна
 * - формы для добавления/редактирования отборов
 */
class HarvestsController extends Controller
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
     * Отображает страницу отборов
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
        ];

        return $this->view('harvests.index', [
            'pageTitle' => 'Отборы',
            'harvestsConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/harvests.js'],
        ]);
    }
}
