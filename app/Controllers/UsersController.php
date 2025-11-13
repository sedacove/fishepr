<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы управления пользователями
 * 
 * Отвечает за отображение страницы управления пользователями:
 * - список всех пользователей
 * - формы для добавления/редактирования пользователей
 * - управление активностью пользователей
 * 
 * Доступна только администраторам.
 */
class UsersController extends Controller
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
     * Отображает страницу управления пользователями
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'baseUrl' => BASE_URL,
        ];

        return $this->view('users.index', [
            'pageTitle' => 'Управление пользователями',
            'usersConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/users.js'],
        ]);
    }
}


