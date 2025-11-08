<?php
/**
 * Страница финансов
 * Доступна только администраторам
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

$page_title = 'Финансы';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';
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

    <div class="row mb-4">
        <div class="col-12 text-center">
            <div class="card shadow-sm">
                <div class="card-body">
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

<script>
let currentFilter = 'month';
let expensesPage = 1;
let incomesPage = 1;

function loadFinances() {
    loadSummary();
    loadExpenses(expensesPage);
    loadIncomes(incomesPage);
}

function loadSummary() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/finances.php',
        method: 'GET',
        data: { action: 'summary', filter: currentFilter },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const data = response.data;
                $('#balanceValue').text(formatCurrency(data.balance));
                $('#incomesValue').text(formatCurrency(data.incomes));
                $('#expensesValue').text(formatCurrency(data.expenses));
            }
        }
    });
}

function loadExpenses(page = 1) {
    expensesPage = page;
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/finances.php',
        method: 'GET',
        data: { action: 'list_expenses', filter: currentFilter, page: expensesPage },
        dataType: 'json',
        success: function(response) {
            if (!response.success) {
                showAlert('danger', response.message || 'Ошибка загрузки расходов');
                return;
            }
            renderExpensesTable(response.data);
            updatePagerInfo('#expensesPagerInfo', response.pagination);
        },
        error: function() {
            showAlert('danger', 'Ошибка загрузки расходов');
        }
    });
}

function loadIncomes(page = 1) {
    incomesPage = page;
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/finances.php',
        method: 'GET',
        data: { action: 'list_incomes', filter: currentFilter, page: incomesPage },
        dataType: 'json',
        success: function(response) {
            if (!response.success) {
                showAlert('danger', response.message || 'Ошибка загрузки приходов');
                return;
            }
            renderIncomesTable(response.data);
            updatePagerInfo('#incomesPagerInfo', response.pagination);
        },
        error: function() {
            showAlert('danger', 'Ошибка загрузки приходов');
        }
    });
}

function renderExpensesTable(records) {
    const tbody = $('#expensesTableBody');
    tbody.empty();

    if (!records.length) {
        tbody.html('<tr><td colspan="5" class="text-center text-muted py-4">Расходы не найдены</td></tr>');
        return;
    }

    records.forEach(function(record) {
        const dateDisplay = formatDateHuman(record.record_date);
        const row = `
            <tr>
                <td>${escapeHtml(dateDisplay)}</td>
                <td>${escapeHtml(record.title)}</td>
                <td class="text-danger fw-semibold">${formatCurrency(record.amount)}</td>
                <td>${record.comment ? escapeHtml(record.comment) : '—'}</td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-primary btn-sm" onclick="editExpense(${record.id})" title="Редактировать">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteExpense(${record.id})" title="Удалить">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function renderIncomesTable(records) {
    const tbody = $('#incomesTableBody');
    tbody.empty();

    if (!records.length) {
        tbody.html('<tr><td colspan="5" class="text-center text-muted py-4">Приходы не найдены</td></tr>');
        return;
    }

    records.forEach(function(record) {
        const dateDisplay = formatDateHuman(record.record_date);
        const row = `
            <tr>
                <td>${escapeHtml(dateDisplay)}</td>
                <td>${escapeHtml(record.title)}</td>
                <td class="text-success fw-semibold">${formatCurrency(record.amount)}</td>
                <td>${record.comment ? escapeHtml(record.comment) : '—'}</td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-primary btn-sm" onclick="editIncome(${record.id})" title="Редактировать">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteIncome(${record.id})" title="Удалить">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function updatePagerInfo(selector, pagination) {
    const info = pagination.total === 0
        ? 'Нет страниц'
        : `Страница ${pagination.page} из ${pagination.pages || 1}`;
    $(selector).text(info);
}

function changeExpensesPage(delta) {
    if (delta < 0 && expensesPage === 1) {
        return;
    }
    expensesPage = Math.max(1, expensesPage + delta);
    loadExpenses(expensesPage);
}

function changeIncomesPage(delta) {
    if (delta < 0 && incomesPage === 1) {
        return;
    }
    incomesPage = Math.max(1, incomesPage + delta);
    loadIncomes(incomesPage);
}

function openExpenseModal(id = null) {
    $('#expenseForm')[0].reset();
    $('#expenseId').val('');
    setTodayDate('#expenseDate');
    if (id) {
        $('#expenseModalTitle').text('Редактировать расход');
        fetchFinanceRecord(id, 'get_expense', fillExpenseForm);
    } else {
        $('#expenseModalTitle').text('Добавить расход');
    }
    new bootstrap.Modal(document.getElementById('expenseModal')).show();
}

function openIncomeModal(id = null) {
    $('#incomeForm')[0].reset();
    $('#incomeId').val('');
    setTodayDate('#incomeDate');
    if (id) {
        $('#incomeModalTitle').text('Редактировать приход');
        fetchFinanceRecord(id, 'get_income', fillIncomeForm);
    } else {
        $('#incomeModalTitle').text('Добавить приход');
    }
    new bootstrap.Modal(document.getElementById('incomeModal')).show();
}

function fetchFinanceRecord(id, action, callback) {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/finances.php',
        method: 'GET',
        data: { action, id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                callback(response.data);
            } else {
                showAlert('danger', response.message || 'Ошибка получения записи');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка получения записи');
        }
    });
}

function fillExpenseForm(data) {
    $('#expenseId').val(data.id);
    $('#expenseDate').val(data.record_date);
    $('#expenseTitle').val(data.title);
    $('#expenseAmount').val(parseFloat(data.amount));
    $('#expenseComment').val(data.comment || '');
}

function fillIncomeForm(data) {
    $('#incomeId').val(data.id);
    $('#incomeDate').val(data.record_date);
    $('#incomeTitle').val(data.title);
    $('#incomeAmount').val(parseFloat(data.amount));
    $('#incomeComment').val(data.comment || '');
}

function saveExpense() {
    const form = $('#expenseForm')[0];
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const payload = {
        id: parseInt($('#expenseId').val(), 10) || null,
        record_date: $('#expenseDate').val(),
        title: $('#expenseTitle').val(),
        amount: $('#expenseAmount').val(),
        comment: $('#expenseComment').val()
    };

    const action = payload.id ? 'update_expense' : 'create_expense';
    submitFinanceForm(payload, action, '#expenseModal', function() {
        loadExpenses(expensesPage);
        loadSummary();
    });
}

function saveIncome() {
    const form = $('#incomeForm')[0];
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const payload = {
        id: parseInt($('#incomeId').val(), 10) || null,
        record_date: $('#incomeDate').val(),
        title: $('#incomeTitle').val(),
        amount: $('#incomeAmount').val(),
        comment: $('#incomeComment').val()
    };

    const action = payload.id ? 'update_income' : 'create_income';
    submitFinanceForm(payload, action, '#incomeModal', function() {
        loadIncomes(incomesPage);
        loadSummary();
    });
}

function submitFinanceForm(payload, action, modalSelector, onSuccess) {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/finances.php?action=' + action,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $(modalSelector).modal('hide');
                if (typeof onSuccess === 'function') {
                    onSuccess();
                }
            } else {
                showAlert('danger', response.message || 'Ошибка при сохранении');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении');
        }
    });
}

function editExpense(id) {
    openExpenseModal(id);
}

function editIncome(id) {
    openIncomeModal(id);
}

function deleteExpense(id) {
    if (!confirm('Удалить расход?')) {
        return;
    }
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/finances.php?action=delete_expense',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadExpenses(expensesPage);
                loadSummary();
            } else {
                showAlert('danger', response.message || 'Ошибка удаления');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка удаления');
        }
    });
}

function deleteIncome(id) {
    if (!confirm('Удалить приход?')) {
        return;
    }
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/finances.php?action=delete_income',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadIncomes(incomesPage);
                loadSummary();
            } else {
                showAlert('danger', response.message || 'Ошибка удаления');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка удаления');
        }
    });
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text).replace(/[&<>"']/g, function(m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m];
    });
}

function formatCurrency(value) {
    const number = parseFloat(value);
    if (isNaN(number)) {
        return '0 ₽';
    }
    return number.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₽';
}

function formatDateHuman(dateStr) {
    if (!dateStr) {
        return '';
    }
    const parts = dateStr.split('-');
    if (parts.length === 3) {
        return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }
    const parsed = new Date(dateStr);
    if (Number.isNaN(parsed.getTime())) {
        return dateStr;
    }
    return parsed.toLocaleDateString('ru-RU');
}

function setTodayDate(selector) {
    const input = document.querySelector(selector);
    if (!input) return;
    const today = new Date();
    const formatted = today.toISOString().split('T')[0];
    input.value = formatted;
}

function showAlert(type, message) {
    const html = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('#alert-container').html(html);
}

$('#dateFilter').on('change', function() {
    currentFilter = $(this).val();
    expensesPage = 1;
    incomesPage = 1;
    loadFinances();
});

$(document).ready(function() {
    loadFinances();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

