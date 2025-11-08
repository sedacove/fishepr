<?php
/**
 * Страница управления пользователями
 * Доступна только администраторам
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';

$page_title = 'Управление пользователями';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Управление пользователями</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Добавить пользователя
            </button>
        </div>
    </div>
    
    <?php renderSectionDescription('users'); ?>
    
    <div id="alert-container"></div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>ФИО</th>
                            <th>Телефон</th>
                            <th>Зарплата</th>
                            <th>Тип</th>
                            <th>Статус</th>
                            <th>Создан</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr>
                            <td colspan="9" class="text-center">
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

<!-- Модальное окно для добавления/редактирования пользователя -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Добавить пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userId" name="id">
                    
                    <div class="mb-3">
                        <label for="userLogin" class="form-label">Логин <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="userLogin" name="login" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="userPassword" class="form-label">
                            Пароль <span class="text-danger">*</span>
                            <small class="text-muted" id="passwordHint">(минимум 6 символов)</small>
                        </label>
                        <input type="password" class="form-control" id="userPassword" name="password" minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <label for="userType" class="form-label">Тип пользователя <span class="text-danger">*</span></label>
                        <select class="form-select" id="userType" name="user_type" required>
                            <option value="user">Пользователь</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="userFullName" class="form-label">ФИО</label>
                        <input type="text" class="form-control" id="userFullName" name="full_name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="userEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="userEmail" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="userPhone" class="form-label">Телефон</label>
                        <input type="tel" class="form-control" id="userPhone" name="phone" placeholder="+7 (___) ___-__-__">
                        <small class="text-muted">Укажите номер в формате +7 (XXX) XXX-XX-XX</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="userPayrollPhone" class="form-label">Телефон для зарплаты</label>
                        <input type="tel" class="form-control" id="userPayrollPhone" name="payroll_phone" placeholder="+7 (___) ___-__-__">
                        <small class="text-muted">Номер распределения зарплаты, формат +7 (XXX) XXX-XX-XX</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="userPayrollBank" class="form-label">Банк для зарплаты</label>
                        <input type="text" class="form-control" id="userPayrollBank" name="payroll_bank" maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label for="userSalary" class="form-label">Зарплата (₽)</label>
                        <input type="number" class="form-control" id="userSalary" name="salary" min="0" step="0.01">
                        <small class="text-muted">Укажите сумму в рублях, например 45000 или 45000.50</small>
                    </div>
                    
                    <div class="mb-3" id="isActiveContainer" style="display: none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="userIsActive" name="is_active" value="1">
                            <label class="form-check-label" for="userIsActive">
                                Активен
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentEditId = null;

// Загрузка списка пользователей
function loadUsers() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/users.php?action=list',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderUsersTable(response.data);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке пользователей');
        }
    });
}

// Отображение таблицы пользователей
function renderUsersTable(users) {
    const tbody = $('#usersTableBody');
    tbody.empty();
    
    if (users.length === 0) {
        tbody.html('<tr><td colspan="9" class="text-center">Пользователи не найдены</td></tr>');
        return;
    }
    
    users.forEach(function(user) {
        const phoneDisplay = user.phone ? formatPhone(user.phone) : '-';
        const salaryDisplay = (user.salary !== null && user.salary !== undefined)
            ? formatSalary(user.salary)
            : '-';
        const statusBadge = user.is_active 
            ? '<span class="badge bg-success">Активен</span>'
            : '<span class="badge bg-danger">Заблокирован</span>';
        
        const typeBadge = user.user_type === 'admin'
            ? '<span class="badge bg-danger">Админ</span>'
            : '<span class="badge bg-primary">Пользователь</span>';
        
        const row = `
            <tr>
                <td>${user.id}</td>
                <td>${escapeHtml(user.login)}</td>
                <td>${escapeHtml(user.full_name || '-')}</td>
                <td>${phoneDisplay}</td>
                <td>${salaryDisplay}</td>
                <td>${typeBadge}</td>
                <td>${statusBadge}</td>
                <td>${user.created_at}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="openEditModal(${user.id})" title="Редактировать">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="toggleActive(${user.id}, ${user.is_active})" title="${user.is_active ? 'Заблокировать' : 'Разблокировать'}">
                        <i class="bi bi-${user.is_active ? 'lock' : 'unlock'}"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})" title="Удалить">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

// Открыть модальное окно для добавления
function openAddModal() {
    currentEditId = null;
    $('#userModalTitle').text('Добавить пользователя');
    $('#userForm')[0].reset();
    $('#userId').val('');
    $('#userPassword').attr('required', true);
    $('#passwordHint').show();
    $('#isActiveContainer').hide();
    setPhoneInputValue('#userPhone', '');
    setPhoneInputValue('#userPayrollPhone', '');
    $('#userPayrollBank').val('');
    $('#userSalary').val('');
    $('#userLogin').removeAttr('readonly');
}

// Открыть модальное окно для редактирования
function openEditModal(id) {
    currentEditId = id;
    $('#userModalTitle').text('Редактировать пользователя');
    $('#userPassword').removeAttr('required');
    $('#passwordHint').text('(оставьте пустым, чтобы не менять)');
    $('#isActiveContainer').show();
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/users.php?action=get&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const user = response.data;
                $('#userId').val(user.id);
                $('#userLogin').val(user.login).attr('readonly', true);
                $('#userPassword').val('');
                $('#userType').val(user.user_type);
                $('#userFullName').val(user.full_name || '');
                $('#userEmail').val(user.email || '');
                setPhoneInputValue('#userPhone', user.phone || '');
                setPhoneInputValue('#userPayrollPhone', user.payroll_phone || '');
                $('#userPayrollBank').val(user.payroll_bank || '');
                $('#userSalary').val(user.salary !== null && user.salary !== undefined ? user.salary : '');
                $('#userIsActive').prop('checked', user.is_active == 1);
                
                const modal = new bootstrap.Modal(document.getElementById('userModal'));
                modal.show();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке данных пользователя');
        }
    });
}

// Сохранить пользователя
function saveUser() {
    const form = $('#userForm')[0];
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = {
        login: $('#userLogin').val(),
        password: $('#userPassword').val(),
        user_type: $('#userType').val(),
        full_name: $('#userFullName').val(),
        email: $('#userEmail').val(),
        phone: $('#userPhone').val(),
        payroll_phone: $('#userPayrollPhone').val(),
        payroll_bank: $('#userPayrollBank').val(),
        salary: $('#userSalary').val()
    };
    
    if (currentEditId) {
        formData.id = currentEditId;
        if (!formData.password) {
            delete formData.password;
        }
        formData.is_active = $('#userIsActive').is(':checked') ? 1 : 0;
    }
    
    const action = currentEditId ? 'update' : 'create';
    const url = '<?php echo BASE_URL; ?>api/users.php?action=' + action;
    
    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#userModal').modal('hide');
                loadUsers();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении пользователя');
        }
    });
}

// Удалить пользователя
function deleteUser(id) {
    if (!confirm('Вы уверены, что хотите удалить этого пользователя?')) {
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/users.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({id: id}),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadUsers();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении пользователя');
        }
    });
}

// Блокировка/разблокировка пользователя
function toggleActive(id, currentStatus) {
    const action = currentStatus ? 'заблокировать' : 'разблокировать';
    if (!confirm(`Вы уверены, что хотите ${action} этого пользователя?`)) {
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/users.php?action=toggle_active',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({id: id}),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadUsers();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при изменении статуса пользователя');
        }
    });
}

function formatSalary(value) {
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
    const formatted = `+7 ${digits.slice(1,4)} ${digits.slice(4,7)}-${digits.slice(7,9)}-${digits.slice(9)}`;
    return escapeHtml(formatted);
}

const phoneInputSelectors = ['#userPhone', '#userPayrollPhone'];

function normalizePhoneDigits(value) {
    if (!value) {
        return '';
    }
    let digits = String(value).replace(/\D/g, '');
    if (!digits.length) {
        return '';
    }
    if (digits[0] === '8') {
        digits = '7' + digits.slice(1);
    }
    if (digits[0] !== '7') {
        digits = '7' + digits;
    }
    return digits.slice(0, 11);
}

function formatPhoneMaskValue(value) {
    const digits = normalizePhoneDigits(value);
    if (!digits) {
        return '';
    }
    let result = '+7';
    const tail = digits.slice(1);
    if (!tail.length) {
        return result;
    }
    result += ' (' + tail.slice(0, Math.min(3, tail.length));
    if (tail.length >= 3) {
        result += ')';
    }
    if (tail.length > 3) {
        result += ' ' + tail.slice(3, Math.min(6, tail.length));
    }
    if (tail.length > 6) {
        result += '-' + tail.slice(6, Math.min(8, tail.length));
    }
    if (tail.length > 8) {
        result += '-' + tail.slice(8, Math.min(10, tail.length));
    }
    return result;
}

function handlePhoneInput(event) {
    const input = event.target;
    const formatted = formatPhoneMaskValue(input.value);
    if (formatted) {
        input.value = formatted;
    } else {
        input.value = '';
    }
}

function handlePhoneBlur(event) {
    const input = event.target;
    const digits = normalizePhoneDigits(input.value);
    if (!digits || digits.length === 1) {
        input.value = '';
    } else {
        input.value = formatPhoneMaskValue(digits);
    }
}

function initializePhoneMasks() {
    phoneInputSelectors.forEach(function(selector) {
        const input = document.querySelector(selector);
        if (!input || input.dataset.maskInitialized === 'true') {
            return;
        }
        input.addEventListener('input', handlePhoneInput);
        input.addEventListener('focus', handlePhoneInput);
        input.addEventListener('blur', handlePhoneBlur);
        if (input.value) {
            input.value = formatPhoneMaskValue(input.value);
        }
        input.dataset.maskInitialized = 'true';
    });
}

function setPhoneInputValue(selector, value) {
    const input = document.querySelector(selector);
    if (!input) {
        return;
    }
    const formatted = formatPhoneMaskValue(value);
    input.value = formatted;
}

// Показать уведомление
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('#alert-container').html(alertHtml);
    
    // Автоматически скрыть через 5 секунд
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
}

// Экранирование HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Загрузка при открытии страницы
$(document).ready(function() {
    loadUsers();
    initializePhoneMasks();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
