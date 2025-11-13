<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

/**
 * Контроллер страницы новостей
 * 
 * Отвечает за отображение страницы управления новостями:
 * - список всех новостей
 * - формы для добавления/редактирования новостей
 * - WYSIWYG редактор для текста новостей (Summernote)
 * 
 * Доступна только администраторам.
 */
class NewsController extends Controller
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
     * Отображает страницу новостей
     * 
     * @return string HTML содержимое страницы
     */
    public function index(): string
    {
        $config = [
            'baseUrl' => BASE_URL,
        ];

        return $this->view('news.index', [
            'pageTitle' => 'Новости',
            'newsConfig' => $config,
            'extra_styles' => [
                'https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css',
            ],
            'extra_body_scripts' => [
                'https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js',
                'assets/js/pages/news.js',
            ],
        ]);
    }
}


