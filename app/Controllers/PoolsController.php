<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы управления бассейнами
 * 
 * Отвечает за отображение страницы управления бассейнами:
 * - список всех бассейнов
 * - формы для добавления/редактирования бассейнов
 * - drag-and-drop сортировка бассейнов (SortableJS)
 * 
 * Доступна только администраторам.
 */
class PoolsController extends Controller
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
     * Отображает страницу управления бассейнами
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'baseUrl' => BASE_URL,
        ];

        return $this->view('pools.index', [
            'pageTitle' => 'Управление бассейнами',
            'poolsConfig' => $config,
            'extra_styles' => ['assets/css/pages/pools.css'],
            'extra_head_scripts' => [
                'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
            ],
            'extra_body_scripts' => ['assets/js/pages/pools.js'],
        ]);
    }
}


