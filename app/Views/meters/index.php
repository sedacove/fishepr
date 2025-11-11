<?php

use App\Support\View;

View::extends('layouts.app');

$config = $metersConfig ?? [
    'baseUrl' => BASE_URL,
];

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="mb-0">Приборы учета</h1>
                    <?php renderSectionDescription('meters'); ?>
                </div>
                <button class="btn btn-primary" onclick="openMeterModal()">
                    <i class="bi bi-plus-circle"></i> Добавить прибор
                </button>
            </div>
            <div id="alert-container"></div>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Описание</th>
                                    <th>Создан</th>
                                    <th class="text-end">Действия</th>
                                </tr>
                            </thead>
                            <tbody id="metersTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm" role="status"></div>
                                        <div>Загрузка...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="meterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="meterModalTitle">Добавить прибор</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="meterForm">
                    <input type="hidden" id="meterId">
                    <div class="mb-3">
                        <label for="meterName" class="form-label">Название</label>
                        <input type="text" class="form-control" id="meterName" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="meterDescription" class="form-label">Описание</label>
                        <textarea class="form-control" id="meterDescription" rows="4"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveMeter()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.metersConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>


