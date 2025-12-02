<?php

use App\Support\View;

View::extends('layouts.app');

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Финансы</h1>
            <div class="d-flex align-items-center gap-3">
                <select id="dateFilter" class="form-select">
                    <option value="week">Текущая неделя</option>
                    <option value="month" selected>Текущий месяц</option>
                    <option value="all">Все записи</option>
                </select>
                <button class="btn btn-success" onclick="openIncomeModal()">
                    <i class="bi bi-plus-circle"></i> Добавить приход
                </button>
                <button class="btn btn-danger" onclick="openExpenseModal()">
                    <i class="bi bi-dash-circle"></i> Добавить расход
                </button>
            </div>
        </div>
    </div>

    <div id="alert-container"></div>

    <div class="row mb-4">
        <div class="col-md-6 mb-3 mb-md-0">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted">Общий баланс</div>
                    <div id="totalBalanceValue" class="display-4 fw-bold">0 ₽</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted">Баланс</div>
                    <div id="balanceValue" class="display-4 fw-bold">0 ₽</div>
                    <div class="d-flex justify-content-center gap-4 mt-3">
                        <div>
                            <div class="text-muted">Приходы</div>
                            <div id="incomesValue" class="fs-5 fw-semibold text-success">0 ₽</div>
                        </div>
                        <div>
                            <div class="text-muted">Расходы</div>
                            <div id="expensesValue" class="fs-5 fw-semibold text-danger">0 ₽</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Расходы</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Дата</th>
                                    <th>Цель</th>
                                    <th>Сумма</th>
                                    <th>Комментарий</th>
                                    <th class="text-end">Действия</th>
                                </tr>
                            </thead>
                            <tbody id="expensesTableBody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Загрузка...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span id="expensesPagerInfo" class="text-muted small">Страница 1 из 1</span>
                    <div>
                        <button class="btn btn-outline-secondary btn-sm" onclick="changeExpensesPage(-1)">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="changeExpensesPage(1)">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Приходы</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Дата</th>
                                    <th>Источник</th>
                                    <th>Сумма</th>
                                    <th>Комментарий</th>
                                    <th class="text-end">Действия</th>
                                </tr>
                            </thead>
                            <tbody id="incomesTableBody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Загрузка...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span id="incomesPagerInfo" class="text-muted small">Страница 1 из 1</span>
                    <div>
                        <button class="btn btn-outline-secondary btn-sm" onclick="changeIncomesPage(-1)">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="changeIncomesPage(1)">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно расхода -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="expenseModalTitle">Добавить расход</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="expenseForm">
                    <input type="hidden" id="expenseId">
                    <div class="mb-3">
                        <label for="expenseDate" class="form-label">Дата</label>
                        <input type="date" class="form-control" id="expenseDate" required>
                    </div>
                    <div class="mb-3">
                        <label for="expenseTitle" class="form-label">Цель</label>
                        <input type="text" class="form-control" id="expenseTitle" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="expenseAmount" class="form-label">Сумма</label>
                        <input type="number" class="form-control" id="expenseAmount" min="0.01" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="expenseComment" class="form-label">Комментарий</label>
                        <textarea class="form-control" id="expenseComment" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" onclick="saveExpense()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно прихода -->
<div class="modal fade" id="incomeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="incomeModalTitle">Добавить приход</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="incomeForm">
                    <input type="hidden" id="incomeId">
                    <div class="mb-3">
                        <label for="incomeDate" class="form-label">Дата</label>
                        <input type="date" class="form-control" id="incomeDate" required>
                    </div>
                    <div class="mb-3">
                        <label for="incomeTitle" class="form-label">Источник</label>
                        <input type="text" class="form-control" id="incomeTitle" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="incomeAmount" class="form-label">Сумма</label>
                        <input type="number" class="form-control" id="incomeAmount" min="0.01" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="incomeComment" class="form-label">Комментарий</label>
                        <textarea class="form-control" id="incomeComment" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-success" onclick="saveIncome()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo asset_url('assets/js/pages/finances.js'); ?>"></script>

