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
        url: BASE_URL + 'api/finances.php',
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
        url: BASE_URL + 'api/finances.php',
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
        url: BASE_URL + 'api/finances.php',
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
        url: BASE_URL + 'api/finances.php',
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
        url: BASE_URL + 'api/finances.php?action=' + action,
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
        url: BASE_URL + 'api/finances.php?action=delete_expense',
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
        url: BASE_URL + 'api/finances.php?action=delete_income',
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

