<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';

/**
 * Контроллер страницы деталей сессии
 * 
 * Отвечает за отображение страницы с деталями сессии:
 * - полная информация о сессии
 * - связанные данные (посадка, бассейн)
 * - история измерений, навесок, отборов, смертности
 * - графики и статистика
 */
class SessionDetailsController extends Controller
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
     * Отображает страницу деталей сессии
     * 
     * Если ID сессии не указан или некорректен, перенаправляет на страницу "Работа".
     * 
     * @return string HTML содержимое страницы
     */
    public function show(): string
    {
        $sessionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($sessionId <= 0) {
            header('Location: ' . BASE_URL . 'work');
            exit;
        }

        return $this->view('session_details.index', [
            'pageTitle' => 'Детали сессии',
            'sessionId' => $sessionId,
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
            'extra_styles' => ['assets/css/session_details.css'],
            'extra_body_scripts' => ['assets/js/pages/session_details.js'],
        ]);
    }
}
