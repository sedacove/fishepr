<?php

use App\Support\View;

View::extends('layouts.app');

$config = $feedsConfig ?? [
    'baseUrl' => BASE_URL,
];

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="mb-0">Корма</h1>
            <button type="button" class="btn btn-primary" onclick="openFeedModal()">
                <i class="bi bi-plus-circle"></i> Добавить корм
            </button>
        </div>
    </div>

    <?php renderSectionDescription('feeds'); ?>

    <div id="feedsAlertContainer"></div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="feedsTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Название</th>
                            <th>Гранула</th>
                            <th>Производитель</th>
                            <th>Нормы</th>
                            <th>Обновлён</th>
                            <th style="width: 150px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody id="feedsTableBody">
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Загрузка...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="feedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="feedModalTitle">Добавить корм</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="feedForm" novalidate>
                    <input type="hidden" id="feedId">

                    <div class="mb-3">
                        <label for="feedName" class="form-label">Название <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="feedName" name="name" maxlength="255" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="feedManufacturer" class="form-label">Производитель</label>
                            <input type="text" class="form-control" id="feedManufacturer" name="manufacturer" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label for="feedGranule" class="form-label">Размер гранулы / тип</label>
                            <input type="text" class="form-control" id="feedGranule" name="granule" maxlength="255">
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label for="feedDescription" class="form-label">Описание</label>
                        <textarea class="form-control" id="feedDescription" name="description" rows="3"></textarea>
                    </div>

                    <div class="alert alert-light border mb-3">
                        <strong>Как задавать формулы кормления:</strong>
                        <ul class="mb-0 mt-2 small">
                            <li>Используйте переменную <code>T</code> для температуры воды (°C) и <code>W</code> для среднего веса рыбы (в граммах).</li>
                            <li>Допустимы операции <code>+</code>, <code>-</code>, <code>*</code>, <code>/</code>, <code>^</code> и круглые скобки. Десятичные дроби задавайте через точку.</li>
                            <li>Формула должна возвращать норму кормления (кг на 1 кг биомассы). Пример: <code>((0.08 + (T - 6) * 0.06) * (2.5 * W ^ -0.3)) / 100</code></li>
                        </ul>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="feedFormulaEconom" class="form-label">Формула «Эконом»</label>
                            <textarea class="form-control" id="feedFormulaEconom" name="formula_econom" rows="3"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="feedFormulaNormal" class="form-label">Формула «Норма»</label>
                            <textarea class="form-control" id="feedFormulaNormal" name="formula_normal" rows="3"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="feedFormulaGrowth" class="form-label">Формула «Рост»</label>
                            <textarea class="form-control" id="feedFormulaGrowth" name="formula_growth" rows="3"></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div id="feedNormsUnavailable" class="alert alert-secondary">
                        Чтобы добавить нормы кормления, сначала сохраните карточку корма.
                    </div>

                    <div id="feedNormsSection" class="d-none">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="mb-0">Нормы кормления (изображения)</h5>
                            <label class="btn btn-outline-primary mb-0">
                                <i class="bi bi-upload"></i> Загрузить изображения
                                <input type="file" id="feedNormUploadInput" multiple accept="image/*" hidden>
                            </label>
                        </div>
                        <div id="feedNormsGallery" class="feed-norms-grid">
                            <div class="text-muted small">Изображения ещё не добавлены</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveFeed()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.feedsConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>

