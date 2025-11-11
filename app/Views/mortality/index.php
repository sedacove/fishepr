<?php

use App\Support\View;

View::extends('layouts.app');

$mortalityConfig = $mortalityConfig ?? [
    'isAdmin' => false,
    'baseUrl' => BASE_URL,
];

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Падеж</h1>
            <?php renderSectionDescription('mortality'); ?>
        </div>
    </div>

    <div id="alert-container"></div>

    <ul class="nav nav-tabs mb-3" id="poolsTabs" role="tablist"></ul>
    <div class="tab-content" id="poolsTabContent"></div>
</div>

<?php
$modalId = 'recordModal';
$formId = 'recordForm';
$poolSelectId = 'recordPool';
$datetimeFieldId = 'datetimeField';
$datetimeInputId = 'recordDateTime';
$weightId = 'recordWeight';
$fishCountId = 'recordFishCount';
$currentPoolId = 'currentPoolId';
$modalTitleId = 'recordModalTitle';
$saveFunction = 'saveRecord';
require __DIR__ . '/../../../templates/mortality_modal.php';
?>

<script>
    window.mortalityConfig = <?php echo json_encode($mortalityConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
