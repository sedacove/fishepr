<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы логов действий
 * 
 * Отвечает за отображение страницы с логами действий пользователей:
 * - список всех действий в системе
 * - фильтрация по типу действия, сущности, пользователю
 * - детальная информация о каждом действии
 * 
 * Доступна только администраторам.
 */
class LogsController extends Controller
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
     * Отображает страницу логов действий
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        return $this->view('logs.index', [
            'pageTitle' => 'Логи действий',
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
            'extra_body_scripts' => ['assets/js/pages/logs.js'],
        ]);
    }
}

