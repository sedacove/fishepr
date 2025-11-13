<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/section_descriptions.php';

class SettingsController extends Controller
{
    public function __construct()
    {
        requireAuth();
        requireAdmin();
    }

    public function index(): string
    {
        return $this->view('settings.index');
    }
}

