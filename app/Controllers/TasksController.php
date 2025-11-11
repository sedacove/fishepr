<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';

class TasksController extends Controller
{
    public function __construct()
    {
        requireAuth();
    }

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

