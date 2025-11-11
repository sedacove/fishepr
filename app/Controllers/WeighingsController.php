<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

class WeighingsController extends Controller
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

        return $this->view('weighings.index', [
            'pageTitle' => 'Навески',
            'weighingsConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/weighings.js'],
        ]);
    }
}
