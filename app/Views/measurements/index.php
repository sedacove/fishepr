<?php

use App\Support\View;

View::extends('layouts.app');

$measurementsConfig = $measurementsConfig ?? [
    'isAdmin' => false,
    'baseUrl' => BASE_URL,
];

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Замеры</h1>
            <?php renderSectionDescription('measurements'); ?>
        </div>
    </div>

    <div id="alert-container"></div>

    <ul class="nav nav-tabs mb-3" id="poolsTabs" role="tablist"></ul>
    <div class="tab-content" id="poolsTabContent"></div>
</div>

<?php
$modalId = 'measurementModal';
$formId = 'measurementForm';
$poolSelectId = 'measurementPool';
$datetimeFieldId = 'datetimeField';
$datetimeInputId = 'measurementDateTime';
$temperatureId = 'measurementTemperature';
$oxygenId = 'measurementOxygen';
$currentPoolId = 'currentPoolId';
$modalTitleId = 'measurementModalTitle';
$saveFunction = 'saveMeasurement';
require __DIR__ . '/../../../templates/measurement_modal.php';
?>

<script>
    window.measurementsConfig = <?php echo json_encode($measurementsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
