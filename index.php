<?php
/**
 * Главная страница
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/duty_helpers.php';
// Устанавливаем заголовок страницы до подключения header.php
$page_title = 'Главная страница';

// Требуем авторизацию до вывода каких-либо заголовков
requireAuth();

$todayDutyDate = getTodayDutyDate();
$dutyRangeStartObj = new DateTime($todayDutyDate);
$dutyRangeStartObj->modify('-1 day');
$dutyRangeEndObj = clone $dutyRangeStartObj;
$dutyRangeEndObj->modify('+6 day');

$dutyRangeStartIso = $dutyRangeStartObj->format('Y-m-d');
$dutyRangeEndIso = $dutyRangeEndObj->format('Y-m-d');
$dutyRangeLabel = $dutyRangeStartObj->format('d.m.Y') . ' — ' . $dutyRangeEndObj->format('d.m.Y');

require_once __DIR__ . '/includes/header.php';
?>
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Добро пожаловать, <?php echo htmlspecialchars($_SESSION['user_full_name'] ?? $_SESSION['user_login']); ?>!</h1>
            
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0" id="latestNewsTitle">Последняя новость</h5>
                                <a href="<?php echo BASE_URL; ?>pages/news.php" class="btn btn-sm btn-outline-primary <?php echo isAdmin() ? '' : 'd-none'; ?>">
                                    Управлять
                                </a>
                            </div>
                            <div id="latestNewsContainer">
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Загрузка...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">Расписание дежурств</h5>
                                <small class="text-muted" id="dutyWeekRangeLabel"><?php echo htmlspecialchars($dutyRangeLabel); ?></small>
                            </div>
                            <div id="dutyWeekContainer" class="duty-week-container">
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Загрузка...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">Мои задачи</h5>
                                <a href="<?php echo BASE_URL; ?>pages/tasks.php" class="btn btn-sm btn-outline-primary">
                                    Все задачи
                                </a>
                            </div>
                            <div id="myTasksList">
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Загрузка...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="alert-container"></div>
        </div>
    </div>
</div>

<style>
.duty-week-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.5rem;
}
.duty-week-cell {
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 0.5rem;
    padding: 0.5rem;
    min-height: 90px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.duty-week-cell.today {
    border-color: #0d6efd;
    box-shadow: 0 0 0 1px rgba(13, 110, 253, 0.25);
}
.duty-week-date {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 0.85rem;
    font-weight: 500;
}
.duty-week-number {
    font-family: 'Bitter', serif;
    font-size: 1.35rem;
    font-weight: 600;
}
.duty-week-name {
    font-size: 0.6rem;
    margin-top: 0.35rem;
    line-height: 1.3;
    min-height: 2.1em;
}
.duty-week-fasting {
    font-size: 0.6rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: #d49100;
}
.duty-week-fasting i {
    font-size: 0.8rem;
}
[data-theme="dark"] .dashboard-widget-card .widget-actions .btn {
    border-color: rgba(255, 255, 255, 0.2);
}
[data-theme="dark"] .duty-week-cell {
    border-color: rgba(255, 255, 255, 0.12);
}
[data-theme="dark"] .duty-week-cell.today {
    border-color: #66b2ff;
    box-shadow: 0 0 0 1px rgba(102, 178, 255, 0.35);
}
[data-theme="dark"] .duty-week-fasting {
    color: #ffce62;
}
@media (max-width: 768px) {
    .duty-week-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
@media (max-width: 576px) {
    .duty-week-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>

<script>
const dutyRangeStart = '<?php echo $dutyRangeStartIso; ?>';
const dutyRangeEnd = '<?php echo $dutyRangeEndIso; ?>';

// Загрузка расписания дежурств на неделю
function loadDutyWeek() {
    const container = $('#dutyWeekContainer');
    container.html(`
        <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `);

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/duty.php?action=range&start=' + dutyRangeStart + '&end=' + dutyRangeEnd,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderDutyWeek(response.data || []);
            } else {
                container.html('<p class="text-danger mb-0"><i class="bi bi-exclamation-triangle"></i> Не удалось загрузить расписание</p>');
            }
        },
        error: function() {
            container.html('<p class="text-danger mb-0"><i class="bi bi-exclamation-triangle"></i> Ошибка при загрузке расписания</p>');
        }
    });
}

function renderDutyWeek(entries) {
    const container = $('#dutyWeekContainer');
    const map = {};
    entries.forEach(function(entry) {
        map[entry.date] = entry;
    });

    const startDate = new Date(dutyRangeStart);
    const todayIso = new Date().toISOString().split('T')[0];
    const weekDayNames = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

    let html = '<div class="duty-week-grid">';
    for (let i = 0; i < 7; i++) {
        const current = new Date(startDate);
        current.setDate(startDate.getDate() + i);
        const iso = current.toISOString().split('T')[0];
        const entry = map[iso] || null;
        const dutyName = entry ? (entry.user_full_name || entry.user_login || '—') : '—';
        const fasting = entry ? !!entry.is_fasting : false;
        const weekday = weekDayNames[current.getDay()];
        const dateNumber = current.getDate();
        const isToday = iso === todayIso;

        html += `
            <div class="duty-week-cell ${isToday ? 'today' : ''}">
                <div class="duty-week-date">
                    <span>${escapeHtml(weekday)}</span>
                    <span class="duty-week-number">${escapeHtml(String(dateNumber))}</span>
                </div>
                ${fasting ? '<div class="duty-week-fasting"><i class="bi bi-exclamation-triangle-fill"></i>Голодовка</div>' : ''}
                <div class="duty-week-name">${escapeHtml(dutyName)}</div>
            </div>
        `;
    }
    html += '</div>';
    container.html(html);
}

function loadLatestNews() {
    const container = $('#latestNewsContainer');
    container.html(`
        <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `);

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/news.php?action=latest',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                const news = response.data;
                const title = escapeHtml(news.title || '');

                if (title) {
                    $('#latestNewsTitle').text(title);
                }
                const author = news.author_full_name
                    ? escapeHtml(news.author_full_name)
                    : escapeHtml(news.author_login || '');
                const publishedAt = formatNewsDate(news.published_at);
                const content = news.content || '';

                container.html(`
                    <div class="latest-news">
                        <div class="d-flex justify-content-between text-muted mb-3 flex-column flex-sm-row">
                            <small class="mb-1 mb-sm-0"><i class="bi bi-calendar-event"></i> ${publishedAt}</small>
                            ${author ? `<small><i class="bi bi-person"></i> ${author}</small>` : ''}
                        </div>
                        <div class="news-content">${content}</div>
                    </div>
                `);
            } else {
                $('#latestNewsTitle').text('Последняя новость');
                container.html(`
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle"></i> Новостей пока нет
                    </p>
                `);
            }
        },
        error: function() {
            $('#latestNewsTitle').text('Последняя новость');
            container.html(`
                <p class="text-danger mb-0">
                    <i class="bi bi-exclamation-triangle"></i> Не удалось загрузить новости
                </p>
            `);
        }
    });
}

// Показать ошибку загрузки дежурного
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

function formatNewsDate(value) {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const day = date.getDate();
    const monthNames = [
        'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
    ];
    const monthName = monthNames[date.getMonth()];
    const year = date.getFullYear();
    return `${day} ${monthName} ${year}`;
}

// Загрузка задач пользователя
function loadMyTasks() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks.php?action=list&tab=my',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderMyTasks(response.data);
            } else {
                $('#myTasksList').html('<p class="text-muted mb-0">Ошибка загрузки задач</p>');
            }
        },
        error: function() {
            $('#myTasksList').html('<p class="text-muted mb-0">Ошибка загрузки задач</p>');
        }
    });
}

// Отображение задач на главной странице
function renderMyTasks(tasks) {
    const container = $('#myTasksList');
    
    // Фильтруем только невыполненные задачи
    const activeTasks = tasks.filter(function(task) {
        return !task.is_completed;
    });
    
    if (activeTasks.length === 0) {
        container.html('<p class="text-muted mb-0"><i class="bi bi-check-circle text-success"></i> Нет активных задач</p>');
        return;
    }
    
    // Показываем максимум 5 задач
    const tasksToShow = activeTasks.slice(0, 5);
    
    let html = '<div class="dashboard-tasks-list">';
    
    tasksToShow.forEach(function(task) {
        const dueDateClass = task.due_date ? (new Date(task.due_date) < new Date() ? 'text-danger' : 'text-muted') : '';
        const dueDateText = task.due_date ? formatTaskDate(task.due_date) : '';
        const progress = task.items_count > 0 ? Math.round((task.items_completed_count / task.items_count) * 100) : 0;
        
        html += `
            <div class="dashboard-task-item" onclick="window.location.href='<?php echo BASE_URL; ?>pages/tasks.php'">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" ${task.is_completed ? 'checked' : ''} 
                           onclick="event.stopPropagation(); toggleTaskComplete(${task.id}, this.checked);">
                    <label class="form-check-label task-title-small">
                        ${escapeHtml(task.title)}
                    </label>
                </div>
                ${task.items_count > 0 ? `
                    <div class="task-progress-small mb-1">
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar" role="progressbar" style="width: ${progress}%"></div>
                        </div>
                    </div>
                ` : ''}
                ${dueDateText ? `<small class="${dueDateClass}"><i class="bi bi-calendar"></i> ${dueDateText}</small>` : ''}
            </div>
        `;
    });
    
    if (activeTasks.length > 5) {
        html += `<div class="text-center mt-2"><small class="text-muted">И еще ${activeTasks.length - 5} задач...</small></div>`;
    }
    
    html += '</div>';
    container.html(html);
}

// Переключить статус задачи
function toggleTaskComplete(taskId, isCompleted) {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks.php?action=complete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            id: taskId,
            is_completed: isCompleted
        }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadMyTasks();
            }
        },
        error: function() {
            // Перезагружаем задачи в случае ошибки
            loadMyTasks();
        }
    });
}

// Форматирование даты для задач
function formatTaskDate(dateString) {
    const date = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const taskDate = new Date(date);
    taskDate.setHours(0, 0, 0, 0);
    
    const diffTime = taskDate - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) {
        return 'Сегодня';
    } else if (diffDays === 1) {
        return 'Завтра';
    } else if (diffDays === -1) {
        return 'Вчера';
    } else if (diffDays < 0) {
        return `Просрочено на ${Math.abs(diffDays)} ${getDaysText(Math.abs(diffDays))}`;
    } else {
        return `Через ${diffDays} ${getDaysText(diffDays)}`;
    }
}

// Получить правильное склонение слова "день"
function getDaysText(days) {
    const lastDigit = days % 10;
    const lastTwoDigits = days % 100;
    if (lastTwoDigits >= 11 && lastTwoDigits <= 14) {
        return 'дней';
    } else if (lastDigit === 1) {
        return 'день';
    } else if (lastDigit >= 2 && lastDigit <= 4) {
        return 'дня';
    } else {
        return 'дней';
    }
}

// Загрузка при открытии страницы
document.addEventListener('DOMContentLoaded', function() {
    loadDutyWeek();
    loadMyTasks();
    loadLatestNews();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
