<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

class PlantingsController extends Controller
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

        return $this->view('plantings.index', [
            'pageTitle' => 'Управление посадками',
            'plantingsConfig' => $config,
            'extra_styles' => ['assets/css/pages/plantings.css'],
            'extra_body_scripts' => ['assets/js/pages/plantings.js'],
        ]);
    }
}


