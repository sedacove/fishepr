<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';

class MeterReadingsController extends Controller
{
    public function __construct()
    {
        requireAuth();
    }

    public function index(): string
    {
        $isAdmin = isAdmin();

        return $this->view('meter_readings.index', [
            'pageTitle' => 'Показания приборов учета',
            'isAdmin' => $isAdmin,
            'baseUrl' => BASE_URL,
            'extra_body_scripts' => ['assets/js/pages/meter_readings.js'],
        ]);
    }
}
