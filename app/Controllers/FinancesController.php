<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

class FinancesController extends Controller
{
    public function __construct()
    {
        requireAuth();
        requireAdmin();
    }

    public function index(): string
    {
        return $this->view('finances.index', [
            'pageTitle' => 'Финансы',
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
            'extra_body_scripts' => ['assets/js/pages/finances.js'],
        ]);
    }
}

