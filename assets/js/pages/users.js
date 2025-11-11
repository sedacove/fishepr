(function () {
    'use strict';

    if (window.__usersPageInitialized) {
        return;
    }
    window.__usersPageInitialized = true;

    const config = window.usersConfig || {};
    const baseUrl = config.baseUrl || '';
    const apiBase = new URL('.', baseUrl || window.location.href).toString();

    let currentEditId = null;
    let userModal = null;

    const phoneInputSelectors = ['#userPhone', '#userPayrollPhone'];

    function apiUrl(path) {
        return new URL(path, apiBase).toString();
    }

    document.addEventListener('DOMContentLoaded', function () {
        userModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal'));
        loadUsers();
        initializePhoneMasks();
    });

    function loadUsers() {
        $.ajax({
            url: apiUrl('api/users.php?action=list'),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderUsersTable(response.data || []);
                } else {
                    showAlert('danger', response.message || 'Ошибка при загрузке пользователей');
                }
            },
            error: function (xhr, status, error) {
                console.error('loadUsers error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке пользователей');
            },
        });
    }

    function renderUsersTable(users) {
        const tbody = $('#usersTableBody');
        tbody.empty();

        if (!users.length) {
            tbody.html('<tr><td colspan="9" class="text-center">Пользователи не найдены</td></tr>');
            return;
        }

        users.forEach(function (user) {
            const phoneDisplay = user.phone ? formatPhone(user.phone) : '-';
            const salaryDisplay =
                user.salary !== null && user.salary !== undefined
                    ? formatSalary(user.salary)
                    : '-';
            const statusBadge = user.is_active
                ? '<span class="badge bg-success">Активен</span>'
                : '<span class="badge bg-danger">Заблокирован</span>';

            const typeBadge =
                user.user_type === 'admin'
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
                    <td>${escapeHtml(user.created_at)}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="openEditModal(${user.id})" title="Редактировать">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="toggleActive(${user.id}, ${user.is_active ? 1 : 0})" title="${
                user.is_active ? 'Заблокировать' : 'Разблокировать'
            }">
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

    function openAddModal() {
        currentEditId = null;
        $('#userModalTitle').text('Добавить пользователя');
        $('#userForm')[0].reset();
        $('#userId').val('');
        $('#userPassword').attr('required', true);
        $('#passwordHint').text('(минимум 6 символов)').show();
        $('#isActiveContainer').hide();
        $('#userLogin').removeAttr('readonly');
        setPhoneInputValue('#userPhone', '');
        setPhoneInputValue('#userPayrollPhone', '');
        userModal.show();
    }

    function openEditModal(id) {
        currentEditId = id;
        $('#userModalTitle').text('Редактировать пользователя');
        $('#userPassword').removeAttr('required').val('');
        $('#passwordHint').text('(оставьте пустым, чтобы не менять)').show();
        $('#isActiveContainer').show();

        $.ajax({
            url: apiUrl(`api/users.php?action=get&id=${id}`),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    const user = response.data;
                    $('#userId').val(user.id);
                    $('#userLogin').val(user.login).attr('readonly', true);
                    $('#userType').val(user.user_type);
                    $('#userFullName').val(user.full_name || '');
                    $('#userEmail').val(user.email || '');
                    setPhoneInputValue('#userPhone', user.phone || '');
                    setPhoneInputValue('#userPayrollPhone', user.payroll_phone || '');
                    $('#userPayrollBank').val(user.payroll_bank || '');
                    $('#userSalary').val(user.salary !== null && user.salary !== undefined ? user.salary : '');
                    $('#userIsActive').prop('checked', !!user.is_active);
                    userModal.show();
                } else {
                    showAlert('danger', response.message || 'Не удалось получить данные пользователя');
                }
            },
            error: function (xhr, status, error) {
                console.error('openEditModal error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке данных пользователя');
            },
        });
    }

    function saveUser() {
        const form = $('#userForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const payload = {
            login: $('#userLogin').val(),
            password: $('#userPassword').val(),
            user_type: $('#userType').val(),
            full_name: $('#userFullName').val(),
            email: $('#userEmail').val(),
            phone: $('#userPhone').val(),
            payroll_phone: $('#userPayrollPhone').val(),
            payroll_bank: $('#userPayrollBank').val(),
            salary: $('#userSalary').val(),
        };

        if (currentEditId) {
            payload.id = currentEditId;
            if (!payload.password) {
                delete payload.password;
            }
            payload.is_active = $('#userIsActive').is(':checked') ? 1 : 0;
        }

        const action = currentEditId ? 'update' : 'create';

        $.ajax({
            url: apiUrl(`api/users.php?action=${action}`),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Изменения сохранены');
                    userModal.hide();
                    loadUsers();
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить пользователя');
                }
            },
            error: function (xhr, status, error) {
                console.error('saveUser error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при сохранении пользователя');
            },
        });
    }

    function deleteUser(id) {
        if (!confirm('Вы уверены, что хотите удалить этого пользователя?')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/users.php?action=delete'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id }),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Пользователь удален');
                    loadUsers();
                } else {
                    showAlert('danger', response.message || 'Не удалось удалить пользователя');
                }
            },
            error: function (xhr, status, error) {
                console.error('deleteUser error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при удалении пользователя');
            },
        });
    }

    function toggleActive(id, currentStatus) {
        const action = currentStatus ? 'заблокировать' : 'разблокировать';
        if (!confirm(`Вы уверены, что хотите ${action} этого пользователя?`)) {
            return;
        }

        $.ajax({
            url: apiUrl('api/users.php?action=toggle_active'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id }),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Статус обновлен');
                    loadUsers();
                } else {
                    showAlert('danger', response.message || 'Не удалось изменить статус');
                }
            },
            error: function (xhr, status, error) {
                console.error('toggleActive error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при изменении статуса пользователя');
            },
        });
    }

    function formatSalary(value) {
        const number = parseFloat(value);
        if (Number.isNaN(number)) {
            return '-';
        }
        return number.toLocaleString('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }) + ' ₽';
    }

    function formatPhone(value) {
        if (!value) {
            return '-';
        }
        const digits = value.replace(/\D/g, '');
        if (digits.length !== 11 || digits[0] !== '7') {
            return escapeHtml(value);
        }
        return escapeHtml(`+7 ${digits.slice(1, 4)} ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9)}`);
    }

    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('#alert-container').html(alertHtml);
        setTimeout(function () {
            $('.alert').alert('close');
        }, 5000);
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };
        return String(text).replace(/[&<>"']/g, function (m) {
            return map[m];
        });
    }

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
        input.value = formatted || '';
    }

    function handlePhoneBlur(event) {
        const input = event.target;
        const digits = normalizePhoneDigits(input.value);
        input.value = digits ? formatPhoneMaskValue(digits) : '';
    }

    function initializePhoneMasks() {
        phoneInputSelectors.forEach(function (selector) {
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
        input.value = formatPhoneMaskValue(value);
    }

    window.openAddModal = openAddModal;
    window.openEditModal = openEditModal;
    window.saveUser = saveUser;
    window.deleteUser = deleteUser;
    window.toggleActive = toggleActive;
})();


