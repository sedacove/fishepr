<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы управления сессиями
 * 
 * Отвечает за отображение страницы управления сессиями:
 * - список активных и завершенных сессий
 * - формы для добавления/редактирования сессий
 * - завершение сессий с расчетом FCR
 * 
 * Доступна только администраторам.
 */
class SessionsController extends Controller
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
     * Отображает страницу управления сессиями
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'baseUrl' => BASE_URL,
        ];

        return $this->view('sessions.index', [
            'pageTitle' => 'Управление сессиями',
            'sessionsConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/sessions.js'],
        ]);
    }
}


