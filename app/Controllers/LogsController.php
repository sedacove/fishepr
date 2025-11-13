<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

class LogsController extends Controller
{
    public function __construct()
    {
        requireAuth();
        requireAdmin();
    }

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

