<?php
/**
 * Главная страница
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/duty_helpers.php';
require_once __DIR__ . '/includes/dashboard_layout.php';
// Устанавливаем заголовок страницы до подключения header.php
$page_title = 'Главная страница';

// Требуем авторизацию до вывода каких-либо заголовков
requireAuth();

$pdo = getDBConnection();
$currentUserId = getCurrentUserId();
$dashboardWidgetDefinitions = getAllDashboardWidgets();
$dashboardLayout = getUserDashboardLayout($pdo, $currentUserId);
$dashboardAvailableWidgets = getAvailableWidgetsForUser($dashboardLayout);

$todayDutyDate = getTodayDutyDate();
$dutyRangeStartObj = new DateTime($todayDutyDate);
$dutyRangeStartObj->modify('-1 day');
$dutyRangeEndObj = clone $dutyRangeStartObj;
$dutyRangeEndObj->modify('+6 day');

$dutyRangeStartIso = $dutyRangeStartObj->format('Y-m-d');
$dutyRangeEndIso = $dutyRangeEndObj->format('Y-m-d');
$dutyRangeLabel = $dutyRangeStartObj->format('d.m.Y') . ' — ' . $dutyRangeEndObj->format('d.m.Y');

require_once __DIR__ . '/includes/header.php';

$widgetsPayload = [];
foreach ($dashboardWidgetDefinitions as $key => $widgetDefinition) {
    $widgetsPayload[$key] = [
        'title' => $widgetDefinition['title'] ?? $key,
        'description' => $widgetDefinition['description'] ?? '',
        'default' => !empty($widgetDefinition['default']),
        'subtitle' => $key === 'duty_week' ? $dutyRangeLabel : ($widgetDefinition['subtitle'] ?? ''),
    ];
}

$dashboardConfigPayload = [
    'layout' => array_values($dashboardLayout),
    'widgets' => $widgetsPayload,
    'dutyRange' => [
        'start' => $dutyRangeStartIso,
        'end' => $dutyRangeEndIso,
        'label' => $dutyRangeLabel,
    ],
    'available' => $dashboardAvailableWidgets,
    'isAdmin' => isAdmin(),
    'baseUrl' => BASE_URL,
];
?>
<script>
window.dashboardConfig = <?php echo json_encode($dashboardConfigPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                <h1 class="mb-0">Добро пожаловать, <?php echo htmlspecialchars($_SESSION['user_full_name'] ?? $_SESSION['user_login']); ?>!</h1>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="addWidgetBtn">
                    <i class="bi bi-plus-lg"></i> Добавить виджет
                </button>
            </div>

            <div id="alert-container"></div>

            <div id="dashboardWidgets" class="row row-cols-1 row-cols-lg-2 g-4"></div>

            <div class="modal fade" id="addWidgetModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Добавить виджет</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                        </div>
                        <div class="modal-body">
                            <div id="availableWidgetsList" class="list-group"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-widget-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
}
.dashboard-widget-card .card-header h5 {
    margin-bottom: 0;
}
.dashboard-widget-card .widget-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.dashboard-widget-card .widget-actions .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.dashboard-widget-card .widget-drag-handle {
    cursor: grab;
}
.dashboard-widget-card .widget-subtitle {
    font-size: 0.8rem;
    margin-top: 0.15rem;
}
.add-widget-card {
    border: 1px dashed rgba(0, 0, 0, 0.25);
    min-height: 210px;
}
.add-widget-card .card-body {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    text-align: center;
}
.add-widget-card .add-widget-icon {
    font-size: 4rem;
    line-height: 1;
}
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
[data-theme="dark"] .add-widget-card {
    border-color: rgba(255, 255, 255, 0.3);
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

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
const dashboardState = window.dashboardConfig || {};
let dashboardLayout = Array.isArray(dashboardState.layout) ? [...dashboardState.layout] : [];
const dashboardWidgetsMap = dashboardState.widgets || {};
const dutyRange = dashboardState.dutyRange || {};
const baseUrl = dashboardState.baseUrl || '';
let dashboardSortable = null;
const dashboardCharts = {};
let tasksContainerEl = null;
let latestNewsContainerEl = null;
let latestNewsTitleEl = null;
let dutyWeekContainerEl = null;

document.addEventListener('DOMContentLoaded', function() {
    renderDashboard();
    initAddWidgetModal();
    document.getElementById('addWidgetBtn').addEventListener('click', function() {
        openAddWidgetModal();
    });
});

function renderDashboard() {
    const container = document.getElementById('dashboardWidgets');
    if (!container) return;
    container.innerHTML = '';

    dashboardLayout.forEach(function(widgetKey) {
        const widgetCol = createWidgetElement(widgetKey);
        if (widgetCol) {
            container.appendChild(widgetCol);
            initializeWidgetContent(widgetKey);
        }
    });

    if (getAvailableWidgetKeys().length > 0) {
        container.appendChild(createAddWidgetCard());
    }

    initDashboardSortable();
}

function createWidgetElement(widgetKey) {
    const definition = dashboardWidgetsMap[widgetKey];
    if (!definition) {
        return null;
    }

    const col = document.createElement('div');
    col.className = 'col dashboard-widget-col';
    col.dataset.widgetKey = widgetKey;

    const titleId = `widget-title-${widgetKey}`;
    const bodyId = `widget-body-${widgetKey}`;
    const subtitle = widgetKey === 'duty_week'
        ? (dutyRange.label || '')
        : (definition.subtitle || '');

    const canRemove = !definition.default;

    col.innerHTML = `
        <div class="card h-100 dashboard-widget-card" data-widget="${widgetKey}">
            <div class="card-header">
                <div class="flex-grow-1">
                    <h5 class="card-title" id="${titleId}">${escapeHtml(definition.title || widgetKey)}</h5>
                    ${subtitle ? `<div class="widget-subtitle text-muted">${escapeHtml(subtitle)}</div>` : ''}
                </div>
                <div class="widget-actions">
                    <button type="button" class="btn btn-light btn-sm widget-drag-handle" title="Переместить">
                        <i class="bi bi-grip-vertical"></i>
                    </button>
                    ${canRemove ? `
                        <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-widget" data-widget="${widgetKey}" title="Удалить">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
            <div class="card-body widget-body" id="${bodyId}">
                ${getWidgetPlaceholder(widgetKey)}
            </div>
        </div>
    `;

    if (canRemove) {
        const removeBtn = col.querySelector('[data-action="remove-widget"]');
        removeBtn.addEventListener('click', function(event) {
            event.preventDefault();
            removeWidget(widgetKey);
        });
    }

    return col;
}

function getWidgetPlaceholder(widgetKey) {
    if (widgetKey === 'my_tasks') {
        return `
            <div class="text-center py-3 text-muted">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </div>
        `;
    }

    if (widgetKey === 'mortality_chart') {
        return `
            <div class="text-center py-3 text-muted">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </div>
        `;
    }

    return `
        <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;
}

function initializeWidgetContent(widgetKey) {
    const body = document.getElementById(`widget-body-${widgetKey}`);
    if (!body) return;

    switch (widgetKey) {
        case 'news': {
            latestNewsContainerEl = body;
            latestNewsTitleEl = document.getElementById('widget-title-news');
            loadLatestNews(latestNewsContainerEl, latestNewsTitleEl);
            break;
        }
        case 'duty_week': {
            dutyWeekContainerEl = body;
            loadDutyWeek(dutyWeekContainerEl);
            break;
        }
        case 'my_tasks': {
            tasksContainerEl = body;
            loadMyTasks(tasksContainerEl);
            break;
        }
        case 'mortality_chart': {
            loadMortalityChart(body);
            break;
        }
        default:
            body.innerHTML = '<p class="text-muted mb-0">Виджет пока не реализован</p>';
            break;
    }
}

function createAddWidgetCard() {
    const col = document.createElement('div');
    col.className = 'col dashboard-widget-add';
    col.innerHTML = `
        <div class="card h-100 add-widget-card">
            <div class="card-body">
                <div class="add-widget-icon text-primary">
                    <i class="bi bi-plus-lg"></i>
                </div>
                <div class="text-muted">Добавить виджет</div>
            </div>
        </div>
    `;
    col.querySelector('.card').addEventListener('click', function() {
        openAddWidgetModal();
    });
    return col;
}

function initDashboardSortable() {
    const container = document.getElementById('dashboardWidgets');
    if (!container) return;
    if (dashboardSortable) {
        dashboardSortable.destroy();
        dashboardSortable = null;
    }

    dashboardSortable = new Sortable(container, {
        animation: 160,
        handle: '.widget-drag-handle',
        draggable: '.dashboard-widget-col',
        filter: '.dashboard-widget-add',
        onEnd: function(evt) {
            if (!evt.item || evt.item.classList.contains('dashboard-widget-add')) {
                return;
            }
            const newLayout = [];
            container.querySelectorAll('.dashboard-widget-col').forEach(function(col) {
                const key = col.dataset.widgetKey;
                if (key) {
                    newLayout.push(key);
                }
            });
            dashboardLayout = newLayout;
            saveDashboardLayout();
        }
    });
}

function initAddWidgetModal() {
    const modalEl = document.getElementById('addWidgetModal');
    if (!modalEl) {
        return;
    }
    modalEl.addEventListener('show.bs.modal', function() {
        populateAvailableWidgetsList();
    });
}

function openAddWidgetModal() {
    const availableKeys = getAvailableWidgetKeys();
    if (availableKeys.length === 0) {
        showDashboardAlert('info', 'Все доступные виджеты уже добавлены.');
        return;
    }
    const modalEl = document.getElementById('addWidgetModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    populateAvailableWidgetsList();
    modal.show();
}

function populateAvailableWidgetsList() {
    const list = document.getElementById('availableWidgetsList');
    if (!list) return;
    list.innerHTML = '';

    const availableKeys = getAvailableWidgetKeys();
    availableKeys.forEach(function(widgetKey) {
        const definition = dashboardWidgetsMap[widgetKey];
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'list-group-item list-group-item-action';
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold">${escapeHtml(definition.title || widgetKey)}</div>
                    ${definition.description ? `<div class="small text-muted">${escapeHtml(definition.description)}</div>` : ''}
                </div>
                <i class="bi bi-plus-lg"></i>
            </div>
        `;
        item.addEventListener('click', function() {
            addWidgetToLayout(widgetKey);
        });
        list.appendChild(item);
    });

    if (availableKeys.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'text-muted';
        empty.textContent = 'Нет доступных виджетов для добавления.';
        list.appendChild(empty);
    }
}

function getAvailableWidgetKeys() {
    return Object.keys(dashboardWidgetsMap).filter(function(widgetKey) {
        return !dashboardLayout.includes(widgetKey);
    });
}

function addWidgetToLayout(widgetKey) {
    if (dashboardLayout.includes(widgetKey)) {
        showDashboardAlert('warning', 'Этот виджет уже добавлен.');
        return;
    }
    dashboardLayout.push(widgetKey);
    saveDashboardLayout(true);
    const modalEl = document.getElementById('addWidgetModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) {
            modal.hide();
        }
    }
    renderDashboard();
}

function removeWidget(widgetKey) {
    if (!dashboardLayout.includes(widgetKey)) {
        return;
    }
    dashboardLayout = dashboardLayout.filter(key => key !== widgetKey);
    saveDashboardLayout();
    renderDashboard();
}

function saveDashboardLayout(skipRender) {
    fetch(`${baseUrl}api/dashboard.php?action=save_layout`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            layout: dashboardLayout
        })
    }).then(function(response) {
        if (!response.ok) {
            throw new Error('Не удалось сохранить макет');
        }
        return response.json();
    }).then(function(data) {
        if (!data.success) {
            throw new Error(data.message || 'Не удалось сохранить макет');
        }
        if (!skipRender) {
            showDashboardAlert('success', 'Изменения сохранены');
        }
    }).catch(function(error) {
        showDashboardAlert('danger', error.message);
    });
}

function showDashboardAlert(type, message) {
    const container = document.getElementById('alert-container');
    if (!container) return;

    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.role = 'alert';
    alert.innerHTML = `
        ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    container.appendChild(alert);
    setTimeout(() => {
        alert.classList.remove('show');
        alert.addEventListener('transitionend', () => alert.remove(), { once: true });
    }, 3000);
}

function loadDutyWeek(container) {
    if (!container) return;
    container.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;

    const params = new URLSearchParams({
        action: 'range',
        start: dutyRange.start || '',
        end: dutyRange.end || ''
    });

    fetch(`${baseUrl}api/duty.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderDutyWeek(data.data || [], container);
            } else {
                container.innerHTML = '<p class="text-danger mb-0"><i class="bi bi-exclamation-triangle"></i> Не удалось загрузить расписание</p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p class="text-danger mb-0"><i class="bi bi-exclamation-triangle"></i> Ошибка при загрузке расписания</p>';
        });
}

function renderDutyWeek(entries, container) {
    if (!container) return;
    const map = {};
    entries.forEach(function(entry) {
        map[entry.date] = entry;
    });

    const startDate = dutyRange.start ? new Date(dutyRange.start) : new Date();
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
    container.innerHTML = html;
}

function loadLatestNews(container, titleElement) {
    if (!container) return;
    container.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;

    fetch(`${baseUrl}api/news.php?action=latest`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const news = data.data;
                const title = escapeHtml(news.title || '');
                if (title && titleElement) {
                    titleElement.textContent = title;
                }
                const author = news.author_full_name
                    ? escapeHtml(news.author_full_name)
                    : escapeHtml(news.author_login || '');
                const publishedAt = formatNewsDate(news.published_at);
                const content = news.content || '';

                container.innerHTML = `
                    <div class="latest-news">
                        <div class="d-flex justify-content-between text-muted mb-3 flex-column flex-sm-row">
                            <small class="mb-1 mb-sm-0"><i class="bi bi-calendar-event"></i> ${publishedAt}</small>
                            ${author ? `<small><i class="bi bi-person"></i> ${author}</small>` : ''}
                        </div>
                        <div class="news-content">${content}</div>
                    </div>
                `;
            } else {
                if (titleElement) {
                    titleElement.textContent = 'Последняя новость';
                }
                container.innerHTML = `
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle"></i> Новостей пока нет
                    </p>
                `;
            }
        })
        .catch(() => {
            if (titleElement) {
                titleElement.textContent = 'Последняя новость';
            }
            container.innerHTML = `
                <p class="text-danger mb-0">
                    <i class="bi bi-exclamation-triangle"></i> Не удалось загрузить новости
                </p>
            `;
        });
}

function loadMyTasks(container) {
    if (!container) return;
    container.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;

    fetch(`${baseUrl}api/tasks.php?action=list&tab=my`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderMyTasks(data.data, container);
            } else {
                container.innerHTML = '<p class="text-muted mb-0">Ошибка загрузки задач</p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p class="text-muted mb-0">Ошибка загрузки задач</p>';
        });
}

function renderMyTasks(tasks, container) {
    if (!container) return;
    const activeTasks = (tasks || []).filter(function(task) {
        return !task.is_completed;
    });

    if (activeTasks.length === 0) {
        container.innerHTML = '<p class="text-muted mb-0"><i class="bi bi-check-circle text-success"></i> Нет активных задач</p>';
        return;
    }

    const tasksToShow = activeTasks.slice(0, 5);
    let html = '<div class="dashboard-tasks-list">';

    tasksToShow.forEach(function(task) {
        const dueDateClass = task.due_date ? (new Date(task.due_date) < new Date() ? 'text-danger' : 'text-muted') : '';
        const dueDateText = task.due_date ? formatTaskDate(task.due_date) : '';
        const progress = task.items_count > 0 ? Math.round((task.items_completed_count / task.items_count) * 100) : 0;

        html += `
            <div class="dashboard-task-item" onclick="window.location.href='${baseUrl}pages/tasks.php'">
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
    container.innerHTML = html;
}

function toggleTaskComplete(taskId, isCompleted) {
    fetch(`${baseUrl}api/tasks.php?action=complete`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: taskId,
            is_completed: isCompleted
        })
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              loadMyTasks(tasksContainerEl);
          } else {
              loadMyTasks(tasksContainerEl);
          }
      })
      .catch(() => {
          loadMyTasks(tasksContainerEl);
      });
}

function loadMortalityChart(container) {
    if (!container) return;
    container.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;

    fetch(`${baseUrl}api/mortality.php?action=totals_last30`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderMortalityChart(container, data.data || []);
            } else {
                container.innerHTML = '<p class="text-danger mb-0">Не удалось загрузить данные по падежу</p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p class="text-danger mb-0">Ошибка при загрузке данных по падежу</p>';
        });
}

function renderMortalityChart(container, data) {
    if (!container) return;
    const canvasId = 'mortalityChartCanvas';
    container.innerHTML = `<canvas id="${canvasId}" height="220"></canvas>`;
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    if (dashboardCharts.mortality_chart) {
        dashboardCharts.mortality_chart.destroy();
    }

    const labels = data.map(item => item.date_label);
    const values = data.map(item => item.total_count);

    dashboardCharts.mortality_chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Падеж, шт',
                data: values,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.2)',
                tension: 0.25,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ` ${context.parsed.y} шт`;
                        }
                    }
                }
            }
        }
    });
}

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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
