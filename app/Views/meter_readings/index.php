<?php

use App\Support\View;

View::extends('layouts.app');

require_once __DIR__ . '/../../../includes/section_descriptions.php';

$config = [
    'baseUrl' => $baseUrl ?? BASE_URL,
    'isAdmin' => !empty($isAdmin),
];
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="mb-0">Показания приборов учета</h1>
                    <?php renderSectionDescription('meter_readings'); ?>
                </div>
            </div>
            <div id="alert-container"></div>
            <ul class="nav nav-tabs mb-3" id="metersTabs" role="tablist"></ul>
            <div class="tab-content" id="metersTabContent"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="readingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="readingModalTitle">Добавить показание</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="readingForm">
                    <input type="hidden" id="readingId">
                    <input type="hidden" id="readingMeterId">
                    <div class="mb-3">
                        <label for="readingValue" class="form-label">Показание</label>
                        <input type="number" class="form-control" id="readingValue" step="0.0001" min="0" required>
                    </div>
                    <?php if (!empty($isAdmin)): ?>
                    <div class="mb-3">
                        <label for="readingRecordedAt" class="form-label">Дата и время показания</label>
                        <input type="datetime-local" class="form-control" id="readingRecordedAt">
                        <div class="form-hint">Если оставить поле пустым, будет использовано текущее время.</div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveReading()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.meterReadingsConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
