<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';

/**
 * Контроллер страницы задач
 * 
 * Отвечает за отображение страницы задач:
 * - список задач пользователя (мои задачи и назначенные мной)
 * - формы для добавления/редактирования задач (только для админов)
 * - управление подзадачами
 * - drag-and-drop сортировка подзадач (SortableJS)
 * - управление файлами задач
 */
class TasksController extends Controller
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
     * Отображает страницу задач
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $isAdmin = isAdmin();

        return $this->view('tasks.index', [
            'pageTitle' => 'Задачи',
            'isAdmin' => $isAdmin,
            'baseUrl' => BASE_URL,
            'extra_styles' => ['assets/css/tasks.css'],
            'extra_head_scripts' => [
                'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
            ],
            'extra_body_scripts' => ['assets/js/pages/tasks.js'],
        ]);
    }
}

