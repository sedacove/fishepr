<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';

class SessionDetailsController extends Controller
{
    public function __construct()
    {
        requireAuth();
    }

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
