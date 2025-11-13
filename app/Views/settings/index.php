<?php

use App\Support\View;

View::extends('layouts.app');

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Настройки системы</h1>
            <?php renderSectionDescription('settings'); ?>
        </div>
    </div>
    
    <div id="alert-container"></div>
    
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-4">Системные настройки</h5>
            
            <div id="settingsContainer">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/wnumb/1.2.0/wNumb.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.js"></script>
<style>
.threshold-range-slider {
    margin: 0.4rem 0 0.15rem;
}
.threshold-slider-wrapper {
    position: relative;
    padding: 1.25rem 0 1.5rem;
}
.threshold-range-slider .noUi-target {
    border: none;
    box-shadow: none;
}
.threshold-range-slider.noUi-target,
.threshold-range-slider.noUi-horizontal {
    height: 50px;
}
.threshold-range-slider .noUi-base,
.threshold-range-slider .noUi-connects {
    height: 100%;
}
.threshold-range-slider .noUi-connect {
    height: 100%;
}
.threshold-range-slider .noUi-handle {
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    top: -13px;
    background-color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
}
.threshold-range-slider .noUi-handle:before,
.threshold-range-slider .noUi-handle:after {
    display: none;
}
.threshold-range-slider .threshold-segment-red {
    background-color: #dc3545;
}
.threshold-range-slider .threshold-segment-yellow {
    background-color: #ffc107;
}
.threshold-range-slider .threshold-segment-green {
    background-color: #198754;
}
.threshold-zone-labels,
.threshold-value-labels {
    position: absolute;
    left: 0;
    right: 0;
    width: 100%;
    pointer-events: none;
}
.threshold-zone-labels {
    top: 0;
}
.threshold-value-labels {
    bottom: 0;
}
.threshold-zone-label,
.threshold-value-label {
    position: absolute;
    transform: translateX(-50%);
    white-space: nowrap;
}
.threshold-zone-label {
    font-size: 0.85rem;
    font-weight: 500;
}
.threshold-value-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #212529;
}
[data-theme="dark"] .threshold-value-label {
    color: #f8f9fa;
}
.threshold-save-btn {
    margin-top: 0.75rem;
}
</style>

<script src="<?php echo asset_url('assets/js/pages/settings.js'); ?>"></script>

