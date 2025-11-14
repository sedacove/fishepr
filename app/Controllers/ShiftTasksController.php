<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';

class ShiftTasksController extends Controller
{
    public function __construct()
    {
        requireAuth();
        if (!isAdmin()) {
            header('Location: ' . BASE_URL);
            exit;
        }
    }

    public function index(): string
    {
        return $this->view('shift_tasks.index', [
            'pageTitle' => 'Задания смены',
            'extra_styles' => ['assets/css/pages/shift_tasks.css'],
            'extra_head_scripts' => ['https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js'],
            'extra_body_scripts' => ['assets/js/pages/shift_tasks.js'],
        ]);
    }
}


