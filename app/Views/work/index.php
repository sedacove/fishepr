<?php

use App\Support\View;

View::extends('layouts.app');

require_once __DIR__ . '/../../../includes/section_descriptions.php';

$config = $workConfig ?? [
    'maxPoolCapacityKg' => 0,
    'isAdmin' => false,
    'baseUrl' => BASE_URL,
];
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1>Рабочая</h1>
            <?php renderSectionDescription('work'); ?>
        </div>
    </div>

    <div id="alert-container"></div>

    <div id="poolsGrid" class="pools-grid">
        <div class="text-center w-100">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    </div>
</div>

<?php
// Модальное окно для замеров
$modalId = 'measurementModal';
$formId = 'measurementForm';
$poolSelectId = 'measurementPool';
$datetimeFieldId = 'measurementDateTimeField';
$datetimeInputId = 'measurementDateTime';
$temperatureId = 'measurementTemperature';
$oxygenId = 'measurementOxygen';
$currentPoolId = 'currentMeasurementPoolId';
$modalTitleId = 'measurementModalTitle';
$saveFunction = 'saveMeasurement';
require __DIR__ . '/../../../templates/measurement_modal.php';

// Модальное окно для падежа
$modalId = 'mortalityModal';
$formId = 'mortalityForm';
$poolSelectId = 'mortalityPool';
$datetimeFieldId = 'mortalityDateTimeField';
$datetimeInputId = 'mortalityDateTime';
$weightId = 'mortalityWeight';
$fishCountId = 'mortalityFishCount';
$currentPoolId = 'currentMortalityPoolId';
$modalTitleId = 'mortalityModalTitle';
$saveFunction = 'saveMortality';
require __DIR__ . '/../../../templates/mortality_modal.php';

// Модальное окно для отборов
$modalId = 'harvestModal';
$formId = 'harvestForm';
$poolSelectId = 'harvestPool';
$datetimeFieldId = 'harvestDateTimeField';
$datetimeInputId = 'harvestDateTime';
$weightId = 'harvestWeight';
$fishCountId = 'harvestFishCount';
$counterpartySelectId = 'harvestCounterparty';
$currentPoolId = 'currentHarvestPoolId';
$modalTitleId = 'harvestModalTitle';
$saveFunction = 'saveHarvest';
require __DIR__ . '/../../../templates/harvest_modal.php';
?>

<script>
    window.workConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
