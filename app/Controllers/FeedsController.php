<?php

namespace App\Controllers;

use App\Support\FeedTableParser;

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
        $template = FeedTableParser::getTemplate();

        return $this->view('feeds.index', [
            'pageTitle' => 'Корма',
            'feedsConfig' => [
                'baseUrl' => BASE_URL,
                'tableTemplate' => $template,
            ],
            'feedTableTemplate' => $template,
            'extra_styles' => ['assets/css/pages/feeds.css'],
            'extra_body_scripts' => ['assets/js/pages/feeds.js'],
        ]);
    }
}

