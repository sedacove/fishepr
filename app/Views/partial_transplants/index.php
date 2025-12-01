<?php

use App\Support\View;

View::extends('layouts.app');

$config = $partialTransplantsConfig ?? [
    'baseUrl' => BASE_URL,
];
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Частичные пересадки</h1>
            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Добавить пересадку
            </button>
        </div>
    </div>

    <div id="alert-container"></div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Сессия отбора</th>
                            <th>Сессия реципиент</th>
                            <th>Вес (кг)</th>
                            <th>Количество</th>
                            <th>Статус</th>
                            <th>Создал</th>
                            <th class="text-end">Действия</th>
                        </tr>
                    </thead>
                    <tbody id="transplantsTableBody">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
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

<!-- Модальное окно для добавления/редактирования пересадки -->
<div class="modal fade" id="transplantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transplantModalTitle">Добавить пересадку</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="transplantForm">
                    <input type="hidden" id="transplantId" name="id">
                    
                    <div class="mb-3">
                        <label for="transplantDate" class="form-label">Дата пересадки <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="transplantDate" name="transplant_date" required>
                    </div>

                    <div class="mb-3">
                        <label for="sourceSessionId" class="form-label">Сессия отбора <span class="text-danger">*</span></label>
                        <select class="form-select" id="sourceSessionId" name="source_session_id" required>
                            <option value="">Выберите сессию...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="recipientSessionId" class="form-label">Сессия реципиент <span class="text-danger">*</span></label>
                        <select class="form-select" id="recipientSessionId" name="recipient_session_id" required>
                            <option value="">Выберите сессию...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="transplantWeight" class="form-label">Вес (кг) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="transplantWeight" name="weight" step="0.01" min="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label for="transplantFishCount" class="form-label">Количество особей <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="transplantFishCount" name="fish_count" min="1" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveTransplant()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.partialTransplantsConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo asset_url('assets/js/pages/partial_transplants.js'); ?>"></script>

