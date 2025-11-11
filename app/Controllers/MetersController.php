<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

class MetersController extends Controller
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

        return $this->view('meters.index', [
            'pageTitle' => 'Приборы учета',
            'metersConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/meters.js'],
        ]);
    }
}


