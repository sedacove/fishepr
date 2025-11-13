<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

class DutyCalendarController extends Controller
{
    public function __construct()
    {
        requireAuth();
    }

    public function index(): string
    {
        return $this->view('duty_calendar.index', [
            'pageTitle' => 'Календарь дежурств',
            'isAdmin' => isAdmin(),
            'baseUrl' => BASE_URL,
            'extra_styles' => ['assets/css/pages/duty_calendar.css'],
            'extra_body_scripts' => ['assets/js/pages/duty_calendar.js'],
        ]);
    }
}

