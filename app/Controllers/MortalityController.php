<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы падежа
 * 
 * Отвечает за отображение страницы с падежом:
 * - список бассейнов с вкладками
 * - список записей падежа для выбранного бассейна
 * - графики смертности
 * - формы для добавления/редактирования записей падежа
 */
class MortalityController extends Controller
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
     * Отображает страницу падежа
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
        ];

        return $this->view('mortality.index', [
            'pageTitle' => 'Падеж',
            'mortalityConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/mortality.js'],
        ]);
    }
}
