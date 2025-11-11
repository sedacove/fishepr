<?php

use App\Support\View;

View::extends('layouts.app');

$config = $counterpartiesConfig ?? [
    'baseUrl' => BASE_URL,
];

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Контрагенты</h1>
            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Добавить контрагента
            </button>
        </div>
    </div>

    <?php renderSectionDescription('counterparties'); ?>

    <div id="alert-container"></div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="counterpartiesTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Название</th>
                            <th>ИНН</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Документы</th>
                            <th>Обновлён</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="counterpartiesTableBody">
                        <tr>
                            <td colspan="8" class="text-center">
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

<div class="modal fade" id="counterpartyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="counterpartyModalTitle">Добавить контрагента</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="counterpartyForm" novalidate>
                    <input type="hidden" id="counterpartyId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="counterpartyName" class="form-label">Название <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="counterpartyName" name="name" maxlength="255" required>
                        </div>
                        <div class="col-md-6">
                            <label for="counterpartyInn" class="form-label">ИНН</label>
                            <input type="text" class="form-control" id="counterpartyInn" name="inn" placeholder="10 или 12 цифр" maxlength="12">
                        </div>
                        <div class="col-md-6">
                            <label for="counterpartyPhone" class="form-label">Телефон</label>
                            <input type="tel" class="form-control" id="counterpartyPhone" name="phone" placeholder="+7 (___) ___-__-__">
                            <div class="form-hint">Формат: +7 (XXX) XXX-XX-XX</div>
                        </div>
                        <div class="col-md-6">
                            <label for="counterpartyEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="counterpartyEmail" name="email" maxlength="255" placeholder="name@example.com">
                        </div>
                        <div class="col-12">
                            <label for="counterpartyDescription" class="form-label">Описание</label>
                            <textarea class="form-control" id="counterpartyDescription" name="description" rows="3" placeholder="Необязательное описание"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Цвет из палитры</label>
                            <div id="colorPaletteContainer" class="d-flex flex-wrap gap-2"></div>
                            <div class="form-hint">Цвет используется для визуальной идентификации контрагента.</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div id="documentsSection" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">Документы</h5>
                            <label class="btn btn-outline-secondary mb-0">
                                <i class="bi bi-upload"></i> Загрузить файлы
                                <input type="file" id="documentUploadInput" multiple hidden>
                            </label>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Название</th>
                                        <th>Размер</th>
                                        <th>Загружено</th>
                                        <th style="width: 120px;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody id="documentsTableBody">
                                    <tr>
                                        <td colspan="4" class="text-muted text-center">Документы отсутствуют</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div id="documentsSectionHint" class="alert alert-light border" role="alert">
                        После сохранения контрагента вы сможете добавить документы.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveCounterparty()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.counterpartiesConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
