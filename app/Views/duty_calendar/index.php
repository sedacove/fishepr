<?php

use App\Support\View;

View::extends('layouts.app');

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>
<div class="container mt-4 mb-4">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Календарь дежурств</h1>
        </div>
    </div>
    
    <?php renderSectionDescription('duty_calendar'); ?>
    
    <div id="alert-container"></div>
    
    <!-- Навигация календаря -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-primary" id="prevMonth">
                    <i class="bi bi-chevron-left"></i> Предыдущий
                </button>
                <h3 class="mb-0" id="calendarTitle"></h3>
                <button type="button" class="btn btn-outline-primary" id="nextMonth">
                    Следующий <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Календарь -->
    <div class="card">
        <div class="card-body p-3">
            <div id="calendar" data-is-admin="<?php echo $isAdmin ? 'true' : 'false'; ?>"></div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo asset_url('assets/css/pages/duty_calendar.css'); ?>">
<script src="<?php echo asset_url('assets/js/pages/duty_calendar.js'); ?>"></script>
<script>
    // Передаем isAdmin в JavaScript
    (function() {
        const calendarEl = document.getElementById('calendar');
        if (calendarEl) {
            window.dutyCalendarIsAdmin = calendarEl.getAttribute('data-is-admin') === 'true';
        }
    })();
</script>

