<?php
/**
 * Страница управления ФЗП
 * Доступна только администраторам
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';

$page_title = 'ФЗП';
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

<script>
let currentEditId = null;
let extraWorkUsers = [];

document.addEventListener('DOMContentLoaded', function() {
    loadSalaryUsers();
    loadExtraWorkUsers(function() {
        loadExtraWorks(0);
        loadExtraWorks(1);
    });

    document.getElementById('current-tab').addEventListener('shown.bs.tab', function() {
        loadExtraWorks(0);
    });

    document.getElementById('paid-tab').addEventListener('shown.bs.tab', function() {
        loadExtraWorks(1);
    });

    document.getElementById('extraWorkForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveExtraWork();
    });
});

function loadSalaryUsers() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/payroll.php?action=salary_users',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderSalaryUsers(response.data);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const res = xhr.responseJSON || {};
            showAlert('danger', res.message || 'Ошибка при загрузке данных ФЗП');
        }
    });
}

function renderSalaryUsers(users) {
    const tbody = $('#salaryUsersTable tbody');
    tbody.empty();

    if (!users.length) {
        tbody.html('<tr><td colspan="4" class="text-center">Нет пользователей с указанной зарплатой</td></tr>');
        return;
    }

    users.forEach(function(user) {
        const name = user.full_name ? escapeHtml(user.full_name) : escapeHtml(user.login);
        const salary = user.salary !== null ? formatCurrency(user.salary) : '-';
        const phone = user.payroll_phone ? formatPhone(user.payroll_phone) : '-';
        const bank = user.payroll_bank ? escapeHtml(user.payroll_bank) : '-';

        tbody.append(`
            <tr>
                <td>${name}</td>
                <td>${salary}</td>
                <td>${phone}</td>
                <td>${bank}</td>
            </tr>
        `);
    });
}

function loadExtraWorkUsers(callback) {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/payroll.php?action=users',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                extraWorkUsers = response.data || [];
                populateAssignedSelect();
                if (callback) callback();
            } else {
                showAlert('danger', response.message);
                if (callback) callback();
            }
        },
        error: function(xhr) {
            const res = xhr.responseJSON || {};
            showAlert('danger', res.message || 'Ошибка при загрузке списка пользователей');
            if (callback) callback();
        }
    });
}

function populateAssignedSelect() {
    const select = $('#extraWorkAssignedTo');
    if (!select.length) {
        return;
    }
    select.empty();
    select.append('<option value="">Выберите сотрудника</option>');
    extraWorkUsers.forEach(function(user) {
        const displayName = user.full_name ? `${user.full_name} (${user.login})` : user.login;
        select.append(`<option value="${user.id}">${escapeHtml(displayName)}</option>`);
    });
}

function loadExtraWorks(isPaid) {
    const targetTable = isPaid ? '#paidExtraWorksTable tbody' : '#currentExtraWorksTable tbody';
    const tbody = $(targetTable);
    tbody.html(`
        <tr>
            <td colspan="6" class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </td>
        </tr>
    `);

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/payroll.php?action=list&is_paid=' + (isPaid ? 1 : 0),
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderExtraWorks(response.data, isPaid);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const res = xhr.responseJSON || {};
            showAlert('danger', res.message || 'Ошибка при загрузке дополнительных работ');
        }
    });
}

function renderExtraWorks(items, isPaid) {
    const tbody = $(isPaid ? '#paidExtraWorksTable tbody' : '#currentExtraWorksTable tbody');
    tbody.empty();

    if (!items.length) {
        tbody.html('<tr><td colspan="6" class="text-center">Нет записей</td></tr>');
        return;
    }

    items.forEach(function(item) {
        const createdBy = item.created_by_name ? escapeHtml(item.created_by_name) : (item.created_by_login ? escapeHtml(item.created_by_login) : '—');
        const assignedTo = item.assigned_name ? escapeHtml(item.assigned_name) : (item.assigned_login ? escapeHtml(item.assigned_login) : '—');
        const paidBy = item.paid_by_name ? escapeHtml(item.paid_by_name) : (item.paid_by_login ? escapeHtml(item.paid_by_login) : '—');
        const workDate = escapeHtml(formatDate(item.work_date));
        const actions = `
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-primary" onclick="openExtraWorkModal(${item.id})" title="Редактировать">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="deleteExtraWork(${item.id})" title="Удалить">
                    <i class="bi bi-trash"></i>
                </button>
                <button type="button" class="btn btn-sm btn-success" onclick="markExtraWorkPaid(${item.id})" title="Выплатить">
                    <i class="bi bi-cash"></i>
                </button>
            </div>
        `;

        const row = isPaid
            ? `
                <tr>
                    <td>${escapeHtml(item.title)}</td>
                    <td>${workDate}</td>
                    <td>${assignedTo}</td>
                    <td>${formatCurrency(item.amount)}</td>
                    <td>${createdBy}</td>
                    <td>${paidBy}<br><small class="text-muted">${item.paid_at ? escapeHtml(item.paid_at) : ''}</small></td>
                </tr>
            `
            : `
                <tr>
                    <td>${escapeHtml(item.title)}</td>
                    <td>${workDate}</td>
                    <td>${assignedTo}</td>
                    <td>${formatCurrency(item.amount)}</td>
                    <td>${createdBy}</td>
                    <td class="text-end">${actions}</td>
                </tr>
            `;

        tbody.append(row);
    });
}

function openExtraWorkModal(id = null) {
    currentEditId = id;
    $('#extraWorkForm')[0].reset();
    populateAssignedSelect();

    if (id) {
        $('#extraWorkModalTitle').text('Редактировать работу');
        $.ajax({
            url: '<?php echo BASE_URL; ?>api/payroll.php?action=get&id=' + id,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const item = response.data;
                    $('#extraWorkId').val(item.id);
                    $('#extraWorkTitle').val(item.title);
                    $('#extraWorkDescription').val(item.description || '');
                    $('#extraWorkDate').val(item.work_date);
                    $('#extraWorkAmount').val(item.amount);
                    $('#extraWorkAssignedTo').val(item.assigned_to);
                    const modal = new bootstrap.Modal(document.getElementById('extraWorkModal'));
                    modal.show();
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr) {
                const res = xhr.responseJSON || {};
                showAlert('danger', res.message || 'Ошибка при загрузке записи');
            }
        });
    } else {
        $('#extraWorkModalTitle').text('Добавить работу');
        $('#extraWorkId').val('');
        const modal = new bootstrap.Modal(document.getElementById('extraWorkModal'));
        modal.show();
    }
}

function saveExtraWork() {
    const form = $('#extraWorkForm')[0];
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = {
        title: $('#extraWorkTitle').val(),
        description: $('#extraWorkDescription').val(),
        work_date: $('#extraWorkDate').val(),
        amount: $('#extraWorkAmount').val(),
        assigned_to: $('#extraWorkAssignedTo').val()
    };

    let url = '<?php echo BASE_URL; ?>api/payroll.php?action=create';
    if (currentEditId) {
        formData.id = currentEditId;
        url = '<?php echo BASE_URL; ?>api/payroll.php?action=update';
    }

    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#extraWorkModal').modal('hide');
                currentEditId = null;
                loadExtraWorks(0);
                loadExtraWorks(1);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const res = xhr.responseJSON || {};
            showAlert('danger', res.message || 'Ошибка при сохранении');
        }
    });
}

function deleteExtraWork(id) {
    if (!confirm('Вы уверены, что хотите удалить запись?')) {
        return;
    }

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/payroll.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id: id }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadExtraWorks(0);
                loadExtraWorks(1);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const res = xhr.responseJSON || {};
            showAlert('danger', res.message || 'Ошибка при удалении');
        }
    });
}

function markExtraWorkPaid(id) {
    if (!confirm('Пометить работу как выплаченную?')) {
        return;
    }

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/payroll.php?action=mark_paid',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id: id }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadExtraWorks(0);
                loadExtraWorks(1);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const res = xhr.responseJSON || {};
            showAlert('danger', res.message || 'Ошибка при обновлении записи');
        }
    });
}

function showAlert(type, message) {
    const html = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('#alert-container').html(html);
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
}

function formatDate(dateString) {
    if (!dateString) {
        return '—';
    }
    const date = new Date(dateString + 'T00:00:00');
    if (Number.isNaN(date.getTime())) {
        return dateString;
    }
    return date.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
}

function formatCurrency(value) {
    const number = parseFloat(value);
    if (isNaN(number)) {
        return '-';
    }
    return number.toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₽';
}

function formatPhone(value) {
    if (!value) {
        return '-';
    }
    const digits = value.replace(/\D/g, '');
    if (digits.length !== 11 || digits[0] !== '7') {
        return escapeHtml(value);
    }
    return escapeHtml(`+7 ${digits.slice(1,4)} ${digits.slice(4,7)}-${digits.slice(7,9)}-${digits.slice(9)}`);
}

function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

