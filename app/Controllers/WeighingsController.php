<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы навесок
 * 
 * Отвечает за отображение страницы с навесками:
 * - список бассейнов с вкладками
 * - список навесок для выбранного бассейна
 * - формы для добавления/редактирования навесок
 */
class WeighingsController extends Controller
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
     * Отображает страницу навесок
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
        ];

        return $this->view('weighings.index', [
            'pageTitle' => 'Навески',
            'weighingsConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/weighings.js'],
        ]);
    }
}
