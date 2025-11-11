<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

class CounterpartiesController extends Controller
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

        return $this->view('counterparties.index', [
            'pageTitle' => 'Контрагенты',
            'counterpartiesConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/counterparties.js'],
        ]);
    }
}
