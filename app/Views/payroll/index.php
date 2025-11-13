<?php

use App\Support\View;

View::extends('layouts.app');

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Фонд заработной платы</h1>
            <?php renderSectionDescription('payroll'); ?>
        </div>
    </div>

    <div id="alert-container"></div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Основные выплаты</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="salaryUsersTable">
                    <thead>
                        <tr>
                            <th>Сотрудник</th>
                            <th>Зарплата</th>
                            <th>Телефон для ЗП</th>
                            <th>Банк</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" class="text-center">
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

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Дополнительные работы</h5>
                <button type="button" class="btn btn-primary" onclick="openExtraWorkModal()">
                    <i class="bi bi-plus-circle"></i> Добавить работу
                </button>
            </div>

            <ul class="nav nav-tabs mb-3" id="extraWorksTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="current-tab" data-bs-toggle="tab" data-bs-target="#currentExtraWorks" type="button" role="tab">
                        Текущие
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="paid-tab" data-bs-toggle="tab" data-bs-target="#paidExtraWorks" type="button" role="tab">
                        Выплаченные
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="extraWorksContent">
                <div class="tab-pane fade show active" id="currentExtraWorks" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="currentExtraWorksTable">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Дата</th>
                                    <th>Сотрудник</th>
                                    <th>Стоимость</th>
                                    <th>Добавил</th>
                                    <th class="text-end">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Загрузка...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="paidExtraWorks" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="paidExtraWorksTable">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Дата</th>
                                    <th>Сотрудник</th>
                                    <th>Стоимость</th>
                                    <th>Добавил</th>
                                    <th>Выплачено</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center">
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
    </div>
</div>

<!-- Модальное окно для доп. работы -->
<div class="modal fade" id="extraWorkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="extraWorkModalTitle">Добавить дополнительную работу</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="extraWorkForm">
                <div class="modal-body">
                    <input type="hidden" id="extraWorkId">

                    <div class="mb-3">
                        <label for="extraWorkTitle" class="form-label">Название <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="extraWorkTitle" name="title" required maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label for="extraWorkAssignedTo" class="form-label">Сотрудник <span class="text-danger">*</span></label>
                        <select class="form-select" id="extraWorkAssignedTo" name="assigned_to" required>
                            <option value="">Загрузка…</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="extraWorkDescription" class="form-label">Описание</label>
                        <textarea class="form-control" id="extraWorkDescription" name="description" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="extraWorkDate" class="form-label">Дата <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="extraWorkDate" name="work_date" required>
                    </div>

                    <div class="mb-3">
                        <label for="extraWorkAmount" class="form-label">Стоимость (₽) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="extraWorkAmount" name="amount" min="0" step="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo asset_url('assets/js/pages/payroll.js'); ?>"></script>

