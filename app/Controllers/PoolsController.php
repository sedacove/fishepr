<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

class PoolsController extends Controller
{
    public function __construct()
    {
        requireAuth();
        requireAdmin();
    }

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


