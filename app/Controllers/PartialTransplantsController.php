<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';

/**
 * MVC контроллер для страницы частичных пересадок
 * 
 * Отображает страницу со списком пересадок и формой для создания новых.
 * Доступна только администраторам.
 */
class PartialTransplantsController extends Controller
{
    /**
     * Конструктор контроллера
     * 
     * Проверяет авторизацию и права администратора
     */
    public function __construct()
    {
        requireAuth();
        if (!isAdmin()) {
            header('Location: ' . BASE_URL);
            exit;
        }
    }

    /**
     * Отображает страницу со списком пересадок
     * 
     * @return string
     */
    public function index(): string
    {
        return $this->view('partial_transplants.index', [
            'pageTitle' => 'Частичные пересадки',
            'partialTransplantsConfig' => [
                'baseUrl' => BASE_URL,
            ],
            'extra_body_scripts' => ['assets/js/pages/partial_transplants.js'],
        ]);
    }
}

