let currentDate = new Date();
let usersList = [];
let isAdmin = false;

// Названия дней недели
const weekDays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const monthNames = [
    'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
];

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    // Получаем isAdmin из глобальной переменной или из data-атрибута
    if (typeof window.dutyCalendarIsAdmin !== 'undefined') {
        isAdmin = window.dutyCalendarIsAdmin;
    } else {
        const adminElement = document.querySelector('[data-is-admin]');
        if (adminElement) {
            isAdmin = adminElement.getAttribute('data-is-admin') === 'true';
        }
    }
    
    if (isAdmin) {
        loadUsers(function() {
            renderCalendar();
        });
    } else {
        renderCalendar();
    }
    
    // Обработчики навигации
    document.getElementById('prevMonth').addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });
    
    document.getElementById('nextMonth').addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });
});

// Загрузка списка пользователей
function loadUsers(callback) {
    $.ajax({
        url: BASE_URL + 'api/duty.php?action=get_users',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                usersList = response.data;
                if (callback) callback();
            } else {
                showAlert('danger', 'Не удалось загрузить список пользователей');
                if (callback) callback();
            }
        },
        error: function(xhr, status, error) {
            console.error('Ошибка загрузки пользователей:', error);
            showAlert('danger', 'Ошибка при загрузке пользователей: ' + error);
            if (callback) callback();
        }
    });
}

// Рендеринг календаря
function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    // Обновляем заголовок
    document.getElementById('calendarTitle').textContent = monthNames[month] + ' ' + year;
    
    // Получаем первый день месяца и количество дней
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    
    // Получаем день недели первого дня (0 = воскресенье, нужно преобразовать)
    let firstDayOfWeek = firstDay.getDay();
    firstDayOfWeek = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1; // Понедельник = 0
    
    // Создаем сетку календаря
    let calendarHTML = '<div class="calendar-grid">';
    
    // Заголовки дней недели
    weekDays.forEach(function(day) {
        calendarHTML += '<div class="calendar-header">' + escapeHtml(day) + '</div>';
    });
    
    // Пустые ячейки до первого дня месяца
    for (let i = 0; i < firstDayOfWeek; i++) {
        const prevMonthDate = new Date(year, month, -i);
        calendarHTML += createDayCell(prevMonthDate, true);
    }
    
    // Дни текущего месяца
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        calendarHTML += createDayCell(date, false);
    }
    
    // Пустые ячейки после последнего дня месяца
    const totalCells = firstDayOfWeek + daysInMonth;
    const remainingCells = 7 - (totalCells % 7);
    if (remainingCells < 7) {
        for (let i = 1; i <= remainingCells; i++) {
            const nextMonthDate = new Date(year, month + 1, i);
            calendarHTML += createDayCell(nextMonthDate, true);
        }
    }
    
    calendarHTML += '</div>';
    
    document.getElementById('calendar').innerHTML = calendarHTML;
    
    // Загружаем дежурных для всех дней месяца
    loadDutiesForMonth(year, month);
}

// Создание ячейки дня
function createDayCell(date, isOtherMonth) {
    const day = date.getDate();
    const dateStr = date.toISOString().split('T')[0];
    const today = new Date();
    const isToday = date.toDateString() === today.toDateString();
    
    let classes = 'calendar-day';
    if (isOtherMonth) classes += ' other-month';
    if (isToday) classes += ' today';
    
    let html = '<div class="' + classes + '" data-date="' + dateStr + '">';
    html += '<div class="calendar-day-number">' + day + '</div>';
    if (isAdmin) {
        html += '<div class="duty-select-wrapper">';
        html += '<select class="form-select form-select-sm duty-select" data-date="' + dateStr + '">';
        html += '<option value="">-</option>';
        usersList.forEach(function(user) {
            const displayName = user.full_name ? user.full_name : user.login;
            html += '<option value="' + user.id + '">' + escapeHtml(displayName) + '</option>';
        });
        html += '</select>';
        html += '</div>';
        html += `
            <div class="form-check mt-2">
                <input class="form-check-input fasting-checkbox" type="checkbox" id="f-${dateStr}" data-date="${dateStr}">
                <label class="form-check-label small" for="f-${dateStr}">Голодовка</label>
            </div>
        `;
    } else {
        html += '<div class="duty-select-wrapper">';
        html += '<div class="duty-display text-muted" data-date="' + dateStr + '">—</div>';
        html += '</div>';
    }
    html += '<div class="fasting-indicator badge bg-warning text-dark mt-2 d-none" data-date="' + dateStr + '"><i class="bi bi-exclamation-triangle-fill me-1"></i>Голодовка</div>';
    html += '</div>';
    
    return html;
}

// Загрузка дежурных за месяц
function loadDutiesForMonth(year, month) {
    const startDate = new Date(year, month, 1);
    const endDate = new Date(year, month + 1, 0);
    
    const startStr = startDate.toISOString().split('T')[0];
    const endStr = endDate.toISOString().split('T')[0];
    
    // Загружаем дежурных для каждого дня месяца
    const daysInMonth = endDate.getDate();
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        const dateStr = date.toISOString().split('T')[0];
        
        $.ajax({
            url: BASE_URL + 'api/duty.php?action=get&date=' + dateStr,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                const duty = (response.success && response.data) ? response.data : null;
                const dutyName = duty ? (duty.user_full_name || duty.user_login || '') : '';
                const isFasting = duty ? !!duty.is_fasting : false;
                updateFastingUI(dateStr, isFasting);

                if (isAdmin) {
                    const select = document.querySelector('.duty-select[data-date="' + dateStr + '"]');
                    if (select) {
                        const assignedValue = duty ? (duty.user_id || '') : '';
                        select.value = assignedValue;
                        select.dataset.assignedUserId = assignedValue;
                    }
                    const checkbox = document.querySelector('.fasting-checkbox[data-date="' + dateStr + '"]');
                    if (checkbox) {
                        checkbox.checked = isFasting;
                    }
                } else {
                    const display = document.querySelector('.duty-display[data-date="' + dateStr + '"]');
                    if (display) {
                        display.textContent = dutyName || '—';
                        display.classList.toggle('text-muted', !dutyName);
                    }
                }
            }
        });
    }
    
    if (isAdmin) {
        document.querySelectorAll('.duty-select').forEach(function(select) {
            select.addEventListener('change', function() {
                const userId = this.value ? parseInt(this.value, 10) : null;
                const date = this.getAttribute('data-date');
                const checkbox = document.querySelector('.fasting-checkbox[data-date="' + date + '"]');
                const isFasting = checkbox ? checkbox.checked : false;

                if (userId) {
                    saveDuty(date, userId, isFasting, this);
                } else {
                    if (checkbox) {
                        checkbox.checked = false;
                    }
                    updateFastingUI(date, false);
                    deleteDuty(date, this);
                }
            });
        });

        document.querySelectorAll('.fasting-checkbox').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const date = this.getAttribute('data-date');
                const select = document.querySelector('.duty-select[data-date="' + date + '"]');
                let userId = select && select.value ? parseInt(select.value, 10) : null;
                if (!userId && select && select.dataset.assignedUserId) {
                    const parsed = parseInt(select.dataset.assignedUserId, 10);
                    userId = Number.isNaN(parsed) ? null : parsed;
                }
                if (!userId) {
                    showAlert('warning', 'Сначала назначьте дежурного, затем отмечайте голодовку');
                    this.checked = false;
                    updateFastingUI(date, false);
                    return;
                }
                saveDuty(date, userId, this.checked, select);
            });
        });
    }
}

function updateFastingUI(date, isFasting) {
    const indicator = document.querySelector('.fasting-indicator[data-date="' + date + '"]');
    if (indicator) {
        indicator.classList.toggle('d-none', !isFasting);
    }
    const cell = document.querySelector('.calendar-day[data-date="' + date + '"]');
    if (cell) {
        cell.classList.toggle('fasting', !!isFasting);
    }
    const checkbox = document.querySelector('.fasting-checkbox[data-date="' + date + '"]');
    if (checkbox) {
        checkbox.checked = !!isFasting;
    }
}

// Сохранить дежурного
function saveDuty(date, userId, isFasting, selectElement) {
    if (!isAdmin) {
        return;
    }
    const formData = {
        date: date,
        user_id: userId,
        is_fasting: !!isFasting
    };
    
    $.ajax({
        url: BASE_URL + 'api/duty.php?action=set',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                updateFastingUI(date, !!isFasting);
                if (selectElement) {
                    const assigned = userId || '';
                    selectElement.dataset.assignedUserId = assigned;
                    selectElement.value = assigned;
                }
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении дежурного');
            loadDutiesForMonth(currentDate.getFullYear(), currentDate.getMonth());
        }
    });
}

// Удалить дежурство
function deleteDuty(date, selectElement) {
    if (!isAdmin) {
        return;
    }
    const formData = {
        date: date
    };
    
    $.ajax({
        url: BASE_URL + 'api/duty.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                updateFastingUI(date, false);
                if (selectElement) {
                    selectElement.dataset.assignedUserId = '';
                    selectElement.value = '';
                }
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении дежурства');
            loadDutiesForMonth(currentDate.getFullYear(), currentDate.getMonth());
        }
    });
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
    
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
}

// Экранирование HTML
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

