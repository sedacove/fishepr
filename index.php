<?php
/**
 * Главная страница
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
// Устанавливаем заголовок страницы до подключения header.php
$page_title = 'Главная страница';

// Требуем авторизацию до вывода каких-либо заголовков
requireAuth();

require_once __DIR__ . '/includes/header.php';
?>
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Добро пожаловать, <?php echo htmlspecialchars($_SESSION['user_full_name'] ?? $_SESSION['user_login']); ?>!</h1>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Сегодня дежурит</h5>
                            <div id="todayDutyInfo">
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Загрузка...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Завтра дежурит</h5>
                            <div id="tomorrowDutyInfo">
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
                <div class="col-md-6 offset-md-6 mb-4">
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
        </div>
    </div>
</div>

<script>
// Загрузка информации о дежурных (сегодня и завтра)
function loadDutyInfo() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/duty.php?action=get_current',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Сегодняшний дежурный
                const todayContainer = $('#todayDutyInfo');
                if (response.data.today.duty) {
                    const duty = response.data.today.duty;
                    const userName = duty.user_full_name ? duty.user_full_name : duty.user_login;
                    todayContainer.html(`
                        <p class="card-text mb-0">
                            <strong>${escapeHtml(userName)}</strong><br>
                            <small class="text-muted">${escapeHtml(duty.user_login)}</small>
                        </p>
                    `);
                } else {
                    todayContainer.html(`
                        <p class="card-text text-muted mb-0">
                            <i class="bi bi-info-circle"></i> Дежурный не назначен
                        </p>
                    `);
                }
                
                // Завтрашний дежурный
                const tomorrowContainer = $('#tomorrowDutyInfo');
                if (response.data.tomorrow.duty) {
                    const duty = response.data.tomorrow.duty;
                    const userName = duty.user_full_name ? duty.user_full_name : duty.user_login;
                    tomorrowContainer.html(`
                        <p class="card-text mb-0">
                            <strong>${escapeHtml(userName)}</strong><br>
                            <small class="text-muted">${escapeHtml(duty.user_login)}</small>
                        </p>
                    `);
                } else {
                    tomorrowContainer.html(`
                        <p class="card-text text-muted mb-0">
                            <i class="bi bi-info-circle"></i> Дежурный не назначен
                        </p>
                    `);
                }
            } else {
                showDutyError('todayDutyInfo');
                showDutyError('tomorrowDutyInfo');
            }
        },
        error: function() {
            showDutyError('todayDutyInfo');
            showDutyError('tomorrowDutyInfo');
        }
    });
}

// Показать ошибку загрузки дежурного
function showDutyError(containerId) {
    $(`#${containerId}`).html(`
        <p class="card-text text-danger mb-0">
            <i class="bi bi-exclamation-triangle"></i> Ошибка загрузки данных
        </p>
    `);
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
    loadDutyInfo();
    loadMyTasks();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
