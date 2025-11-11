<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

class MeasurementsController extends Controller
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

        return $this->view('measurements.index', [
            'pageTitle' => 'Замеры',
            'measurementsConfig' => $config,
            'extra_body_scripts' => ['assets/js/pages/measurements.js'],
        ]);
    }
}
