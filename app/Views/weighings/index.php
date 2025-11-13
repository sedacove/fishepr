<?php

use App\Support\View;

View::extends('layouts.app');

$config = $weighingsConfig ?? [
    'isAdmin' => false,
    'baseUrl' => BASE_URL,
];

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Навески</h1>
            <?php renderSectionDescription('weighings'); ?>
        </div>
    </div>

    <div id="alert-container"></div>

    <ul class="nav nav-tabs mb-3" id="poolsTabs" role="tablist"></ul>
    <div class="tab-content" id="poolsTabContent"></div>
</div>

<div class="modal fade" id="recordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recordModalTitle">Добавить навеску</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="recordForm">
                    <input type="hidden" id="recordId" name="id">
                    <input type="hidden" id="currentPoolId" name="pool_id">

                    <div class="mb-3">
                        <label for="recordPool" class="form-label">Бассейн <span class="text-danger">*</span></label>
                        <select class="form-select" id="recordPool" name="pool_id" required>
                            <option value="">Выберите бассейн</option>
                        </select>
                    </div>

                    <div class="mb-3" id="datetimeField" style="display: none;">
                        <label for="recordDateTime" class="form-label">Дата и время записи <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="recordDateTime" name="recorded_at">
                        <div class="form-hint">Если оставить поле пустым, будет использовано текущее время.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="recordWeight" class="form-label">Вес (кг) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="recordWeight" name="weight" step="0.01" min="0.01" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="recordFishCount" class="form-label">Количество рыб (шт) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="recordFishCount" name="fish_count" min="1" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveRecord()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.weighingsConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
