<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';

class FeedsController extends Controller
{
    public function __construct()
    {
        requireAuth();
        requireAdmin();
    }

    public function index(): string
    {
        return $this->view('feeds.index', [
            'pageTitle' => 'Корма',
            'feedsConfig' => [
                'baseUrl' => BASE_URL,
            ],
            'extra_styles' => ['assets/css/pages/feeds.css'],
            'extra_body_scripts' => ['assets/js/pages/feeds.js'],
        ]);
    }
}

