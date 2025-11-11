<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

class HarvestsController extends Controller
{
    public function __construct()
    {
        requireAuth();
    }

    public function index(): string
    {
        $config = [
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
        ];

        return $this->view('harvests.index', [
            'pageTitle' => 'Отборы',
            'harvestsConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/harvests.js'],
        ]);
    }
}
