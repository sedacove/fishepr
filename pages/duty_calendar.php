<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
$isAdmin = isAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';

$page_title = 'Календарь дежурств';
?>

<div class="container mt-4 mb-4">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Календарь дежурств</h1>
        </div>
    </div>
    
    <?php renderSectionDescription('duty_calendar'); ?>
    
    <div id="alert-container"></div>
    
    <!-- Навигация календаря -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-primary" id="prevMonth">
                    <i class="bi bi-chevron-left"></i> Предыдущий
                </button>
                <h3 class="mb-0" id="calendarTitle"></h3>
                <button type="button" class="btn btn-outline-primary" id="nextMonth">
                    Следующий <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Календарь -->
    <div class="card">
        <div class="card-body p-3">
            <div id="calendar"></div>
        </div>
    </div>
</div>

<style>
#calendar {
    min-height: 600px;
}

.duty-display {
    font-size: 0.9rem;
    padding: 0.25rem 0.5rem;
    border: 1px dashed transparent;
    border-radius: 0.25rem;
    min-height: 2rem;
    display: flex;
    align-items: center;
}

.duty-display:not(.text-muted) {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.08);
    color: #0d6efd;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background-color: #dee2e6;
    border: 1px solid #dee2e6;
}

.calendar-header {
    background-color: #f8f9fa;
    padding: 0.75rem;
    text-align: center;
    font-weight: 600;
    font-family: 'Bitter', serif;
    border: 1px solid #dee2e6;
}

.calendar-day {
    background-color: white;
    min-height: 120px;
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    position: relative;
    display: flex;
    flex-direction: column;
}

.calendar-day.other-month {
    background-color: #f8f9fa;
    opacity: 0.6;
}

.calendar-day.today {
    background-color: #e7f3ff;
    border: 2px solid #0d6efd;
}

.calendar-day-number {
    font-family: 'Bitter', serif;
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #212529;
}

.calendar-day.other-month .calendar-day-number {
    color: #6c757d;
}

.calendar-day.today .calendar-day-number {
    color: #0d6efd;
}

.duty-select-wrapper {
    margin-top: auto;
    padding-top: 0.5rem;
}

.duty-select-wrapper select {
    width: 100%;
    font-size: 0.75rem;
    padding: 0.25rem;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    background-color: white;
    cursor: pointer;
}

.duty-select-wrapper select:focus {
    outline: 2px solid #0d6efd;
    outline-offset: -2px;
}

[data-theme="dark"] .calendar-header {
    background-color: #1e1e1e;
    color: #e0e0e0;
    border-color: #404040;
}

[data-theme="dark"] .calendar-day {
    background-color: #2d2d2d;
    border-color: #404040;
    color: #e0e0e0;
}

[data-theme="dark"] .duty-display {
    border-color: transparent;
    color: #b0b0b0;
}

[data-theme="dark"] .duty-display:not(.text-muted) {
    border-color: #66b2ff;
    background-color: rgba(102, 178, 255, 0.15);
    color: #66b2ff;
}

[data-theme="dark"] .calendar-day.other-month {
    background-color: #1e1e1e;
    opacity: 0.5;
}

[data-theme="dark"] .calendar-day.today {
    background-color: #1a4d80;
    border-color: #0d6efd;
}

[data-theme="dark"] .calendar-day-number {
    color: #e0e0e0;
}

[data-theme="dark"] .calendar-day.other-month .calendar-day-number {
    color: #6c757d;
}

[data-theme="dark"] .calendar-day.today .calendar-day-number {
    color: #0d6efd;
}

[data-theme="dark"] .duty-select-wrapper select {
    background-color: #2d2d2d;
    border-color: #404040;
    color: #e0e0e0;
}

[data-theme="dark"] .calendar-grid {
    background-color: #404040;
    border-color: #404040;
}
</style>

<script>
let currentDate = new Date();
let usersList = [];
const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

// Названия дней недели
const weekDays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const monthNames = [
    'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
];

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
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
        url: '<?php echo BASE_URL; ?>api/duty.php?action=get_users',
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
    html += '<div class="duty-select-wrapper">';
    if (isAdmin) {
        html += '<select class="form-select form-select-sm duty-select" data-date="' + dateStr + '">';
        html += '<option value="">-</option>';
        
        // Заполняем опции пользователями
        usersList.forEach(function(user) {
            const displayName = user.full_name ? (user.full_name + ' (' + user.login + ')') : user.login;
            html += '<option value="' + user.id + '">' + escapeHtml(displayName) + '</option>';
        });
        
        html += '</select>';
    } else {
        html += '<div class="duty-display text-muted" data-date="' + dateStr + '">—</div>';
    }
    html += '</div>';
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
            url: '<?php echo BASE_URL; ?>api/duty.php?action=get&date=' + dateStr,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    const duty = response.data;
                    const dutyName = duty.user_full_name || duty.user_login || '';
                    if (isAdmin) {
                        const select = document.querySelector('.duty-select[data-date="' + dateStr + '"]');
                        if (select) {
                            select.value = duty.user_id || '';
                        }
                    } else {
                        const display = document.querySelector('.duty-display[data-date="' + dateStr + '"]');
                        if (display) {
                            display.textContent = dutyName || '—';
                            display.classList.toggle('text-muted', !dutyName);
                        }
                    }
                } else if (!isAdmin) {
                    const display = document.querySelector('.duty-display[data-date="' + dateStr + '"]');
                    if (display) {
                        display.textContent = '—';
                        display.classList.add('text-muted');
                    }
                }
            }
        });
    }
    
    if (isAdmin) {
        // Устанавливаем обработчики для всех select'ов
        document.querySelectorAll('.duty-select').forEach(function(select) {
            select.addEventListener('change', function() {
                const userId = this.value ? parseInt(this.value) : null;
                const date = this.getAttribute('data-date');
                
                if (userId) {
                    saveDuty(date, userId);
                } else {
                    deleteDuty(date);
                }
            });
        });
    }
}

// Сохранить дежурного
function saveDuty(date, userId) {
    if (!isAdmin) {
        return;
    }
    const formData = {
        date: date,
        user_id: userId
    };
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/duty.php?action=set',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении дежурного');
        }
    });
}

// Удалить дежурство
function deleteDuty(date) {
    if (!isAdmin) {
        return;
    }
    const formData = {
        date: date
    };
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/duty.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении дежурства');
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
