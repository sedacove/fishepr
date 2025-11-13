<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/settings.php';

class WorkController extends Controller
{
    public function __construct()
    {
        requireAuth();
    }

    public function index(): string
    {
        $config = [
            'maxPoolCapacityKg' => (float) getSetting('max_pool_capacity_kg', 5000),
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
        ];

        return $this->view('work.index', [
            'pageTitle' => 'Рабочая',
            'workConfig' => $config,
            'extra_styles' => ['assets/css/pool_blocks.css'],
            'extra_body_scripts' => ['assets/js/pages/work.js'],
        ]);
    }
}

