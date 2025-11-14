(function() {
    'use strict';

    if (window.__dashboardPageInitialized) {
        return;
    }
    window.__dashboardPageInitialized = true;

    const dashboardState = window.dashboardConfig || {};
    const dashboardWidgetsMap = dashboardState.widgets || {};
    const dutyRange = dashboardState.dutyRange || {};
    function parseDateString(dateStr) {
        if (typeof dateStr !== 'string') {
            return new Date();
        }
        const parts = dateStr.split('-').map(Number);
        if (parts.length !== 3 || parts.some(Number.isNaN)) {
            return new Date(dateStr);
        }
        const [year, month, day] = parts;
        return new Date(year, month - 1, day);
    }

    function formatDateKey(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    const baseUrl = dashboardState.baseUrl || '';
    const DASHBOARD_COLUMNS = 2;

    let dashboardLayout = normalizeClientLayout(dashboardState.layout);
    let dashboardSortable = [];
    const dashboardCharts = {};
    const mortalityModes = {
        count: {
            key: 'total_count',
            label: 'Падеж, шт',
            unit: 'шт',
            format: {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }
        },
        weight: {
            key: 'total_weight',
            label: 'Падеж, кг',
            unit: 'кг',
            format: {
                minimumFractionDigits: 0,
                maximumFractionDigits: 1
            }
        }
    };
    const mortalityChartState = {
        data: [],
        mode: 'count'
    };
    const mortalityByPoolChartState = {
        data: {
            labels: [],
            pools: []
        },
        mode: 'count'
    };
    const metersChartState = {
        meters: [],
        currentMeterIndex: 0,
        data: []
    };
    const shiftTasksWidgetState = {
        container: null,
        tasks: [],
        isAdmin: false,
    };
    let tasksContainerEl = null;
    let latestNewsContainerEl = null;
    let latestNewsTitleEl = null;
    let dutyWeekContainerEl = null;
    let toggleEditBtn = null;
    let isEditMode = false;

document.addEventListener('DOMContentLoaded', function() {
    toggleEditBtn = document.getElementById('toggleEditBtn');
    if (toggleEditBtn) {
        toggleEditBtn.addEventListener('click', toggleEditMode);
    }
    renderDashboard();
    initAddWidgetModal();
});

function renderDashboard() {
    const container = document.getElementById('dashboardWidgets');
    if (!container) return;

    const columns = prepareDashboardColumns(container);
    columns.forEach(column => column.innerHTML = '');

    document.body.classList.toggle('dashboard-edit-mode', isEditMode);
    if (toggleEditBtn) {
        toggleEditBtn.innerHTML = isEditMode
            ? '<i class="bi bi-check-lg"></i> Готово'
            : '<i class="bi bi-pencil"></i> Редактировать';
        toggleEditBtn.classList.toggle('btn-primary', isEditMode);
        toggleEditBtn.classList.toggle('btn-outline-secondary', !isEditMode);
    }

    const layoutColumns = sanitizeLayoutColumnsClient(dashboardLayout.columns || []);
    dashboardLayout = { columns: layoutColumns };

    layoutColumns.forEach((widgets, index) => {
        const column = columns[index];
        if (!column) return;
        widgets.forEach(widgetKey => appendWidgetToColumn(column, widgetKey));
    });

    if (isEditMode && getAvailableWidgetKeys().length > 0) {
        appendAddWidgetCard(columns, layoutColumns);
    }

    initDashboardSortable(columns);
}

function createWidgetElement(widgetKey) {
    const definition = dashboardWidgetsMap[widgetKey];
    if (!definition) {
        return null;
    }

    const col = document.createElement('div');
    col.className = 'dashboard-widget-col';
    col.dataset.widgetKey = widgetKey;

    const titleId = `widget-title-${widgetKey}`;
    const bodyId = `widget-body-${widgetKey}`;
    const subtitle = widgetKey === 'duty_week'
        ? (dutyRange.label || '')
        : (definition.subtitle || '');

    const canRemove = !definition.default;
    const baseTitle = definition.title || widgetKey;

    col.innerHTML = `
        <div class="card dashboard-widget-card" data-widget="${widgetKey}">
            <div class="card-header">
                <div class="flex-grow-1">
                    <h5 class="card-title" id="${titleId}" data-base-title="${escapeHtml(baseTitle)}">${escapeHtml(baseTitle)}</h5>
                    ${subtitle ? `<div class="widget-subtitle text-muted">${escapeHtml(subtitle)}</div>` : ''}
                </div>
                <div class="widget-actions">
                    <button type="button" class="btn btn-light btn-sm widget-drag-handle" title="Переместить">
                        <i class="bi bi-grip-vertical"></i>
                    </button>
                    ${canRemove ? `
                        <button type="button" class="btn btn-sm btn-danger" data-action="remove-widget" data-widget="${widgetKey}" title="Удалить">
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

    if (widgetKey === 'mortality_by_pool_chart') {
        return `
            <div class="text-center py-3 text-muted">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </div>
        `;
    }

    if (widgetKey === 'meters_chart') {
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
        case 'mortality_by_pool_chart': {
            loadMortalityByPoolChart(body);
            break;
        }
        case 'temperature_chart': {
            loadTemperatureChart(body);
            break;
        }
        case 'oxygen_chart': {
            loadOxygenChart(body);
            break;
        }
        case 'meters_chart': {
            loadMetersChart(body);
            break;
        }
        case 'shift_tasks': {
            loadShiftTasksWidget(body);
            break;
        }
        default:
            body.innerHTML = '<p class="text-muted mb-0">Виджет пока не реализован</p>';
            break;
    }
}

function createAddWidgetCard() {
    const wrapper = document.createElement('div');
    wrapper.className = 'dashboard-widget-col dashboard-widget-add';
    wrapper.innerHTML = `
        <div class="card add-widget-card">
            <div class="card-body">
                <div class="add-widget-icon text-primary">
                    <i class="bi bi-plus-lg"></i>
                </div>
                <div class="text-muted">Добавить виджет</div>
            </div>
        </div>
    `;
    wrapper.querySelector('.card').addEventListener('click', function() {
        openAddWidgetModal();
    });
    return wrapper;
}

function prepareDashboardColumns(container) {
    let columns = Array.from(container.querySelectorAll('.dashboard-column'));
    if (columns.length === DASHBOARD_COLUMNS) {
        return columns;
    }

    container.innerHTML = '';
    for (let i = 0; i < DASHBOARD_COLUMNS; i++) {
        const column = document.createElement('div');
        column.className = 'dashboard-column';
        column.dataset.columnIndex = String(i);
        container.appendChild(column);
    }
    return Array.from(container.querySelectorAll('.dashboard-column'));
}

function appendWidgetToColumn(columnElement, widgetKey) {
    if (!columnElement) return;
    const widgetEl = createWidgetElement(widgetKey);
    if (!widgetEl) return;
    columnElement.appendChild(widgetEl);
    initializeWidgetContent(widgetKey);
}

function appendAddWidgetCard(columns, layouts) {
    const addCard = createAddWidgetCard();
    const lengths = layouts.map(list => (Array.isArray(list) ? list.length : 0));
    const minLength = Math.min(...lengths);
    const targetIndex = Math.max(0, lengths.indexOf(minLength));
    const targetColumn = columns[targetIndex] || columns[0];
    if (targetColumn) {
        targetColumn.appendChild(addCard);
    }
}

function initDashboardSortable(columns) {
    if (Array.isArray(dashboardSortable) && dashboardSortable.length) {
        dashboardSortable.forEach(sortable => sortable.destroy());
    }
    dashboardSortable = [];

    if (!Array.isArray(columns) || !columns.length) {
        return;
    }

    columns.forEach(column => {
        const sortable = new Sortable(column, {
            animation: 160,
            handle: '.widget-drag-handle',
            draggable: '.dashboard-widget-col',
            filter: '.dashboard-widget-add',
            group: 'dashboard-widgets',
            disabled: !isEditMode,
            onEnd: function(evt) {
                if (evt.item && evt.item.classList.contains('dashboard-widget-add')) {
                    return;
                }
                const newColumns = columns.map(col => {
                    const widgets = [];
                    col.querySelectorAll('.dashboard-widget-col').forEach(widget => {
                        if (widget.classList.contains('dashboard-widget-add')) return;
                        const key = widget.dataset.widgetKey;
                        if (key) {
                            widgets.push(key);
                        }
                    });
                    return widgets;
                });
                dashboardLayout = {
                    columns: sanitizeLayoutColumnsClient(newColumns),
                };
                saveDashboardLayout();
            }
        });
        dashboardSortable.push(sortable);
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
    if (!isEditMode) {
        return;
    }
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
    const used = new Set(flattenLayoutColumns());
    return Object.keys(dashboardWidgetsMap).filter(function(widgetKey) {
        return !used.has(widgetKey);
    });
}

function addWidgetToLayout(widgetKey) {
    if (layoutContainsWidget(widgetKey)) {
        showDashboardAlert('warning', 'Этот виджет уже добавлен.');
        return;
    }

    ensureLayoutColumns();
    const targetIndex = getShortestColumnIndexClient(dashboardLayout.columns);
    dashboardLayout.columns[targetIndex].push(widgetKey);

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
    ensureLayoutColumns();
    let removed = false;
    dashboardLayout.columns = dashboardLayout.columns.map(column => {
        if (!Array.isArray(column)) {
            return [];
        }
        const filtered = column.filter(key => {
            if (key === widgetKey) {
                removed = true;
                return false;
            }
            return true;
        });
        return filtered;
    });

    if (!removed) {
        return;
    }

    dashboardLayout.columns = sanitizeLayoutColumnsClient(dashboardLayout.columns);
    saveDashboardLayout();
    renderDashboard();
}

function saveDashboardLayout(skipRender) {
    dashboardLayout = {
        columns: sanitizeLayoutColumnsClient(dashboardLayout.columns || []),
    };

    fetch(`${baseUrl}api/dashboard.php?action=save_layout`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            layout: dashboardLayout
        })
    })
        .then(async function(response) {
            const raw = await response.text();
            let payload = {};
            if (raw) {
                try {
                    payload = JSON.parse(raw);
                } catch (parseError) {
                    console.error('Failed to parse dashboard save response:', raw);
                }
            }
            if (!response.ok) {
                const message = payload && typeof payload.message === 'string'
                    ? payload.message
                    : `Не удалось сохранить макет (код ${response.status})`;
                throw new Error(message);
            }
            return payload;
        })
        .then(function(data) {
            if (!data.success) {
                throw new Error(data.message || 'Не удалось сохранить макет');
            }
            if (!skipRender) {
                showDashboardAlert('success', 'Изменения сохранены');
            }
        })
        .catch(function(error) {
            const message = error && error.message ? error.message : 'Не удалось сохранить макет';
            showDashboardAlert('danger', message);
            console.error('Dashboard layout save failed:', error);
        });
}

function toggleEditMode() {
    isEditMode = !isEditMode;
    if (!isEditMode) {
        const modalEl = document.getElementById('addWidgetModal');
        if (modalEl) {
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
        }
    }
    renderDashboard();
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

function normalizeClientLayout(rawLayout) {
    let columns = [];
    if (rawLayout && typeof rawLayout === 'object' && Array.isArray(rawLayout.columns)) {
        columns = rawLayout.columns;
    } else if (Array.isArray(rawLayout)) {
        columns = distributeWidgetsClient(rawLayout, DASHBOARD_COLUMNS);
    }
    return { columns: sanitizeLayoutColumnsClient(columns) };
}

function sanitizeLayoutColumnsClient(columns) {
    const sanitized = [];
    const used = new Set();
    if (Array.isArray(columns)) {
        columns.forEach(column => {
            const list = [];
            if (Array.isArray(column)) {
                column.forEach(widgetKey => {
                    if (typeof widgetKey === 'string' && dashboardWidgetsMap[widgetKey] && !used.has(widgetKey)) {
                        list.push(widgetKey);
                        used.add(widgetKey);
                    }
                });
            }
            sanitized.push(list);
        });
    }
    while (sanitized.length < DASHBOARD_COLUMNS) {
        sanitized.push([]);
    }
    if (sanitized.length > DASHBOARD_COLUMNS) {
        sanitized.length = DASHBOARD_COLUMNS;
    }
    return sanitized;
}

function distributeWidgetsClient(widgetKeys, columnsCount) {
    const keys = Array.isArray(widgetKeys)
        ? widgetKeys.filter(key => typeof key === 'string' && dashboardWidgetsMap[key])
        : [];
    const columns = Array.from({ length: Math.max(columnsCount, 1) }, () => []);
    keys.forEach((key, index) => {
        columns[index % columns.length].push(key);
    });
    return columns;
}

function flattenLayoutColumns() {
    const flat = [];
    if (!dashboardLayout || !Array.isArray(dashboardLayout.columns)) {
        return flat;
    }
    dashboardLayout.columns.forEach(column => {
        if (Array.isArray(column)) {
            column.forEach(widgetKey => {
                if (typeof widgetKey === 'string') {
                    flat.push(widgetKey);
                }
            });
        }
    });
    return flat;
}

function layoutContainsWidget(widgetKey) {
    return flattenLayoutColumns().includes(widgetKey);
}

function ensureLayoutColumns() {
    if (!dashboardLayout || !Array.isArray(dashboardLayout.columns)) {
        dashboardLayout = { columns: Array.from({ length: DASHBOARD_COLUMNS }, () => []) };
    } else {
        dashboardLayout.columns = sanitizeLayoutColumnsClient(dashboardLayout.columns);
    }
}

function getShortestColumnIndexClient(columns) {
    let minIndex = 0;
    let minValue = Number.POSITIVE_INFINITY;
    columns.forEach((column, index) => {
        const length = Array.isArray(column) ? column.length : 0;
        if (length < minValue) {
            minValue = length;
            minIndex = index;
        }
    });
    return minIndex;
}

    function buildApiDateKey(date) {
        return new Date(date).toISOString().split('T')[0];
    }

    function fetchDutyByDate(apiDate) {
        const params = new URLSearchParams({
            action: 'get',
            date: apiDate
        });
        return fetch(`${baseUrl}api/duty.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => (data && data.success) ? data.data : null)
            .catch(() => null);
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

        const startDate = dutyRange.start ? parseDateString(dutyRange.start) : new Date();
        const days = [];
        for (let i = 0; i < 7; i++) {
            const displayDate = new Date(startDate);
            displayDate.setDate(startDate.getDate() + i);
            days.push({
                displayKey: formatDateKey(displayDate),
                apiKey: buildApiDateKey(displayDate)
            });
        }

        Promise.all(days.map(day => fetchDutyByDate(day.apiKey)))
            .then(results => {
                const entries = results.map((duty, index) => {
                    const day = days[index];
                    if (!duty) {
                        return { date: day.displayKey, user_full_name: null, user_login: null, is_fasting: false };
                    }
                    return {
                        date: day.displayKey,
                        user_full_name: duty.user_full_name,
                        user_login: duty.user_login,
                        is_fasting: !!duty.is_fasting
                    };
                });
                renderDutyWeek(entries, container);
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

    const startDate = dutyRange.start ? parseDateString(dutyRange.start) : new Date();
    const todayIso = new Date().toISOString().split('T')[0];
    const weekDayNames = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

    let html = '<div class="duty-week-grid">';
    for (let i = 0; i < 7; i++) {
        const current = new Date(startDate);
        current.setDate(startDate.getDate() + i);
        const iso = formatDateKey(current);
        const entry = map[iso] || null;
        let dutyName = entry ? (entry.user_full_name || entry.user_login || '—') : '—';
        // Ограничиваем имя первыми двумя словами
        if (dutyName && dutyName !== '—') {
            const words = dutyName.trim().split(/\s+/);
            dutyName = words.slice(0, 2).join(' ');
        }
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

function loadShiftTasksWidget(container) {
    shiftTasksWidgetState.container = container;
    container.innerHTML = `
        <div class="text-center py-3 text-muted">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;

    fetch(`${baseUrl}api/shift_tasks.php?action=list`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Не удалось загрузить задания смены');
            }
            const payload = data.data || {};
            shiftTasksWidgetState.tasks = payload.tasks || [];
            shiftTasksWidgetState.isAdmin = !!payload.is_admin;
            renderShiftTasksWidget();
        })
        .catch(error => {
            container.innerHTML = `<p class="text-danger mb-0"><i class="bi bi-exclamation-triangle"></i> ${escapeHtml(error.message)}</p>`;
        });
}

function renderShiftTasksWidget() {
    const container = shiftTasksWidgetState.container;
    if (!container) return;

    const tasks = shiftTasksWidgetState.tasks.slice(0, 5);
    if (!tasks.length) {
        container.innerHTML = '<p class="text-muted mb-0">Нет заданий для текущей смены</p>';
        return;
    }

    const items = tasks.map(task => {
        const isCompleted = task.status === 'completed';
        const checkbox = `
            <div class="form-check shift-widget-check me-2">
                <input class="form-check-input" type="checkbox" data-shift-task="${task.id}" ${isCompleted ? 'checked' : ''}>
            </div>
        `;
        const title = `<div class="fw-semibold">${escapeHtml(task.title || '')}</div>`;
        const desc = task.description ? `<div class="small text-muted">${escapeHtml(task.description)}</div>` : '';
        const statusClass = isCompleted ? 'text-success' : (task.is_overdue ? 'text-danger' : 'text-muted');
        const statusLabel = escapeHtml(task.time_diff_label || '');

        return `
            <li class="list-group-item d-flex align-items-start">
                ${checkbox}
                <div class="flex-grow-1">
                    ${title}
                    ${desc}
                    <div class="small ${statusClass}">${statusLabel}</div>
                </div>
            </li>
        `;
    }).join('');

    container.innerHTML = `<ul class="list-group list-group-flush shift-widget-list">${items}</ul>`;
    container.querySelectorAll('input[data-shift-task]').forEach(input => {
        input.addEventListener('change', onShiftTaskWidgetToggle);
    });
}

function onShiftTaskWidgetToggle(event) {
    const checkbox = event.target;
    const taskId = Number(checkbox.dataset.shiftTask);
    const completed = checkbox.checked;

    const message = completed ? 'Отметить задание как выполненное?' : 'Вернуть задание в работу?';
    if (!confirm(message)) {
        checkbox.checked = !completed;
        return;
    }

    fetch(`${baseUrl}api/shift_tasks.php?action=toggle`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: taskId, completed }),
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Не удалось обновить задание');
            }
            const payload = data.data || {};
            const updatedTask = payload.task;
            const idx = shiftTasksWidgetState.tasks.findIndex(task => task.id === taskId);
            if (idx >= 0 && updatedTask) {
                shiftTasksWidgetState.tasks[idx] = updatedTask;
            }
            renderShiftTasksWidget();
        })
        .catch(error => {
            alert(error.message);
            checkbox.checked = !completed;
        });
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
            <div class="dashboard-task-item" onclick="window.location.href='${baseUrl}tasks'">
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
        html += `<div class="text-center mt-2"><small class="text-muted"> еще ${activeTasks.length - 5} задач...</small></div>`;
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
                const rawData = Array.isArray(data.data) ? data.data : [];
                const normalisedData = rawData.map(item => {
                    const totalCount = item && typeof item.total_count !== 'undefined' && item.total_count !== null
                        ? item.total_count
                        : 0;
                    const totalWeight = item && typeof item.total_weight !== 'undefined' && item.total_weight !== null
                        ? item.total_weight
                        : 0;
                    return {
                        ...item,
                        total_count: Number(totalCount) || 0,
                        total_weight: Number(totalWeight) || 0
                    };
                });
                mortalityChartState.data = normalisedData;
                mortalityChartState.mode = mortalityChartState.mode === 'weight' ? 'weight' : 'count';
                renderMortalityChart(container);
            } else {
                container.innerHTML = '<p class="text-danger mb-0">Не удалось загрузить данные по падежу</p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p class="text-danger mb-0">Ошибка при загрузке данных по падежу</p>';
        });
}

function loadMortalityByPoolChart(container) {
    if (!container) return;
    container.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;

    fetch(`${baseUrl}api/mortality.php?action=totals_last14_by_pool`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const rawData = data.data || {};
                const normalised = normaliseMortalityByPoolData(rawData);
                mortalityByPoolChartState.data = normalised;
                mortalityByPoolChartState.mode = mortalityByPoolChartState.mode === 'weight' ? 'weight' : 'count';
                renderMortalityByPoolChart(container);
            } else {
                container.innerHTML = '<p class="text-danger mb-0">Не удалось загрузить данные по падежу</p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p class="text-danger mb-0">Ошибка при загрузке данных по падежу</p>';
        });
}

function renderMortalityByPoolChart(container) {
    if (!container) return;
    if (dashboardCharts.mortality_by_pool_chart) {
        dashboardCharts.mortality_by_pool_chart.destroy();
        dashboardCharts.mortality_by_pool_chart = null;
    }

    const state = mortalityByPoolChartState.data || {};
    const labels = Array.isArray(state.labels) ? state.labels : [];
    const poolsRaw = Array.isArray(state.pools) ? state.pools : [];
    const pools = poolsRaw.filter(function(pool) {
        const series = Array.isArray(pool && pool.series) ? pool.series : [];
        return series.some(function(point) {
            const count = point && typeof point.total_count !== 'undefined' ? Number(point.total_count) : 0;
            const weight = point && typeof point.total_weight !== 'undefined' ? Number(point.total_weight) : 0;
            return (Number.isFinite(count) && count > 0) || (Number.isFinite(weight) && weight > 0);
        });
    });
    const currentModeKey = mortalityModes[mortalityByPoolChartState.mode] ? mortalityByPoolChartState.mode : 'count';
    mortalityByPoolChartState.mode = currentModeKey;
    const modeConfig = mortalityModes[currentModeKey];

    if (!labels.length || !pools.length) {
        container.innerHTML = '<p class="text-muted mb-0">Недостаточно данных для построения графика</p>';
        return;
    }

    const labelValues = labels.map(item => item.label);

    container.innerHTML = `
        <div class="d-flex justify-content-end mb-3">
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary" data-mode="count">шт</button>
                <button type="button" class="btn btn-outline-secondary" data-mode="weight">кг</button>
            </div>
        </div>
        <div class="position-relative" style="height: 220px;">
            <canvas id="mortalityByPoolChartCanvas"></canvas>
        </div>
    `;

    const toggleButtons = container.querySelectorAll('[data-mode]');
    toggleButtons.forEach(button => {
        const buttonMode = button.dataset.mode;
        const isActive = buttonMode === mortalityByPoolChartState.mode;
        button.classList.toggle('btn-primary', isActive);
        button.classList.toggle('btn-outline-secondary', !isActive);
        button.classList.toggle('active', isActive);
        button.disabled = isActive;
        button.setAttribute('aria-pressed', String(isActive));
        button.addEventListener('click', () => {
            if (buttonMode && buttonMode !== mortalityByPoolChartState.mode) {
                mortalityByPoolChartState.mode = buttonMode;
                renderMortalityByPoolChart(container);
            }
        });
    });

    const datasets = pools.map((pool, index) => {
        const series = Array.isArray(pool.series) ? pool.series : [];
        const values = labelValues.map(label => {
            const entry = series.find(item => item.label === label);
            if (!entry || typeof entry !== 'object') {
                return 0;
            }
            const rawValue = typeof entry[modeConfig.key] !== 'undefined' && entry[modeConfig.key] !== null
                ? entry[modeConfig.key]
                : 0;
            const value = Number(rawValue);
            return Number.isFinite(value) ? value : 0;
        });
        const color = getSeriesColor(index, '#dc3545');
        return {
            label: pool.pool_name || `Бассейн ${pool.pool_id || index + 1}`,
            data: values,
            borderColor: color,
            backgroundColor: `${color}33`,
            tension: 0.25,
            pointRadius: 3,
            pointHoverRadius: 5,
            fill: false,
            spanGaps: false
        };
    });

    const totalValue = pools.reduce((sum, pool) => {
        const value = modeConfig.key === 'total_weight'
            ? Number(pool.total_weight) || 0
            : Number(pool.total_count) || 0;
        return sum + value;
    }, 0);

    updateMortalityByPoolWidgetTitle(totalValue, modeConfig);

    const canvas = container.querySelector('#mortalityByPoolChartCanvas');
    if (!canvas) {
        return;
    }

    const ctx = canvas.getContext('2d');

    dashboardCharts.mortality_by_pool_chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labelValues,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return `${formatNumericValue(value, modeConfig.format)} ${modeConfig.unit}`;
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context && context.parsed && typeof context.parsed.y !== 'undefined'
                                ? context.parsed.y
                                : 0;
                            const poolLabel = context.dataset && context.dataset.label ? context.dataset.label : '';
                            return ` ${poolLabel}: ${formatNumericValue(value, modeConfig.format)} ${modeConfig.unit}`;
                        }
                    }
                }
            }
        }
    });
}

function renderMortalityChart(container) {
    if (!container) return;
    if (dashboardCharts.mortality_chart) {
        dashboardCharts.mortality_chart.destroy();
        dashboardCharts.mortality_chart = null;
    }

    const data = Array.isArray(mortalityChartState.data) ? mortalityChartState.data : [];
    const currentModeKey = mortalityModes[mortalityChartState.mode] ? mortalityChartState.mode : 'count';
    mortalityChartState.mode = currentModeKey;
    const modeConfig = mortalityModes[currentModeKey];
    const labels = data.map(item => item.date_label);
    const values = data.map(item => {
        if (!item || typeof item !== 'object') {
            return 0;
        }
        const rawValue = typeof item[modeConfig.key] !== 'undefined' && item[modeConfig.key] !== null
            ? item[modeConfig.key]
            : 0;
        const value = Number(rawValue);
        return Number.isFinite(value) ? value : 0;
    });
    const totalValue = values.reduce((sum, value) => sum + value, 0);

    container.innerHTML = `
        <div class="d-flex justify-content-end mb-3">
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary" data-mode="count">шт</button>
                <button type="button" class="btn btn-outline-secondary" data-mode="weight">кг</button>
            </div>
        </div>
        <div class="position-relative" style="height: 220px;">
            <canvas id="mortalityChartCanvas"></canvas>
        </div>
    `;

    const toggleButtons = container.querySelectorAll('[data-mode]');
    toggleButtons.forEach(button => {
        const buttonMode = button.dataset.mode;
        const isActive = buttonMode === mortalityChartState.mode;
        button.classList.toggle('btn-primary', isActive);
        button.classList.toggle('btn-outline-secondary', !isActive);
        button.classList.toggle('active', isActive);
        button.disabled = isActive;
        button.setAttribute('aria-pressed', String(isActive));
        button.addEventListener('click', () => {
            if (buttonMode && buttonMode !== mortalityChartState.mode) {
                mortalityChartState.mode = buttonMode;
                renderMortalityChart(container);
            }
        });
    });

    updateMortalityWidgetTitle(totalValue, modeConfig);

    const canvas = container.querySelector('#mortalityChartCanvas');
    if (!canvas) {
        return;
    }

    const ctx = canvas.getContext('2d');

    dashboardCharts.mortality_chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: modeConfig.label,
                data: values,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.2)',
                tension: 0.25,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 4,
                spanGaps: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return `${formatNumericValue(value, modeConfig.format)} ${modeConfig.unit}`;
                        }
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
                            const value = context && context.parsed && typeof context.parsed.y !== 'undefined'
                                ? context.parsed.y
                                : 0;
                            return ` ${formatNumericValue(value, modeConfig.format)} ${modeConfig.unit}`;
                        }
                    }
                }
            }
        }
    });
}

function formatNumericValue(value, options = {}) {
    const number = Number(value);
    const safeNumber = Number.isFinite(number) ? number : 0;
    const minimumFractionDigits = Number.isInteger(options.minimumFractionDigits) ? options.minimumFractionDigits : 0;
    const maximumFractionDigits = Number.isInteger(options.maximumFractionDigits)
        ? options.maximumFractionDigits
        : minimumFractionDigits;
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits,
        maximumFractionDigits
    }).format(safeNumber);
}

function normaliseMortalityByPoolData(rawData) {
    const result = {
        labels: [],
        pools: []
    };

    if (!rawData || typeof rawData !== 'object') {
        return result;
    }

    if (Array.isArray(rawData.labels)) {
        result.labels = rawData.labels
            .map(function(item) {
                const date = item && typeof item.date === 'string' ? item.date : '';
                const label = item && typeof item.label === 'string' ? item.label : '';
                return { date, label };
            })
            .filter(function(item) {
                return item.label;
            });
    }

    if (Array.isArray(rawData.pools)) {
        result.pools = rawData.pools.map(function(pool) {
            const poolId = typeof pool === 'object' && pool !== null && typeof pool.pool_id === 'number'
                ? pool.pool_id
                : (pool && typeof pool.pool_id !== 'undefined' ? Number(pool.pool_id) : null);
            const poolNameRaw = pool && typeof pool.pool_name === 'string' ? pool.pool_name : '';
            const poolName = poolNameRaw.trim().length
                ? pool.pool_name.trim()
                : (poolId !== null ? `Бассейн ${poolId}` : 'Бассейн');

            const series = Array.isArray(pool && pool.series)
                ? pool.series.map(function(point) {
                    const date = point && typeof point.date === 'string' ? point.date : '';
                    const label = point && typeof point.label === 'string' ? point.label : '';
                    const totalCount = point && typeof point.total_count !== 'undefined' ? Number(point.total_count) : 0;
                    const totalWeight = point && typeof point.total_weight !== 'undefined' ? Number(point.total_weight) : 0;
                    return {
                        date,
                        label,
                        total_count: isFinite(totalCount) ? totalCount : 0,
                        total_weight: isFinite(totalWeight) ? totalWeight : 0
                    };
                }).filter(function(point) {
                    return point.label;
                })
                : [];

            return {
                pool_id: poolId,
                pool_name: poolName,
                series,
                total_count: (pool && typeof pool.total_count !== 'undefined' && isFinite(Number(pool.total_count)))
                    ? Number(pool.total_count)
                    : series.reduce(function(acc, item) { return acc + item.total_count; }, 0),
                total_weight: (pool && typeof pool.total_weight !== 'undefined' && isFinite(Number(pool.total_weight)))
                    ? Number(pool.total_weight)
                    : series.reduce(function(acc, item) { return acc + item.total_weight; }, 0)
            };
        });
    }

    return result;
}

function updateMortalityWidgetTitle(totalValue, modeConfig) {
    const titleEl = document.getElementById('widget-title-mortality_chart');
    if (!titleEl || !modeConfig) {
        return;
    }
    const baseTitle = titleEl.dataset.baseTitle || titleEl.textContent.replace(/\s*\(.*\)\s*$/, '').trim();
    titleEl.dataset.baseTitle = baseTitle;
    const formattedTotal = formatNumericValue(totalValue, modeConfig.format);
    titleEl.textContent = `${baseTitle} (всего: ${formattedTotal} ${modeConfig.unit})`;
}

function updateMortalityByPoolWidgetTitle(totalValue, modeConfig) {
    const titleEl = document.getElementById('widget-title-mortality_by_pool_chart');
    if (!titleEl || !modeConfig) {
        return;
    }
    const baseTitle = titleEl.dataset.baseTitle || titleEl.textContent.replace(/\s*\(.*\)\s*$/, '').trim();
    titleEl.dataset.baseTitle = baseTitle;
    const formattedTotal = formatNumericValue(totalValue, modeConfig.format);
    titleEl.textContent = `${baseTitle} (всего: ${formattedTotal} ${modeConfig.unit})`;
}

function loadTemperatureChart(container) {
    loadMeasurementChart(container, 'latest_temperatures', 'temperature_chart', {
        datasetLabel: 'Температура, °C',
        defaultColor: '#0d6efd',
        unit: '°C',
    });
}

function loadOxygenChart(container) {
    loadMeasurementChart(container, 'latest_oxygen', 'oxygen_chart', {
        datasetLabel: 'Кислород, мг/л',
        defaultColor: '#198754',
        unit: 'мг/л',
    });
}

function loadMeasurementChart(container, endpoint, chartKey, options) {
    if (!container) return;
    container.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;

    fetch(`${baseUrl}api/measurements.php?action=${endpoint}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderMeasurementChart(container, data.data || [], chartKey, options);
            } else {
                container.innerHTML = '<p class="text-danger mb-0">Не удалось загрузить данные замеров</p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p class="text-danger mb-0">Ошибка при загрузке данных замеров</p>';
        });
}

function renderMeasurementChart(container, data, chartKey, options) {
    if (!container) return;
    if (!Array.isArray(data) || data.length === 0) {
        container.innerHTML = '<p class="text-muted mb-0">Недостаточно данных для построения графика</p>';
        return;
    }

    const canvasId = `${chartKey}Canvas`;
    container.innerHTML = `<canvas id="${canvasId}" height="220"></canvas>`;
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    if (dashboardCharts[chartKey]) {
        dashboardCharts[chartKey].destroy();
    }

    const grouped = groupMeasurementsByPool(data);
    const labels = getUnifiedLabels(grouped);

    const datasets = Object.keys(grouped).map((poolKey, index) => {
        const series = grouped[poolKey];
        const color = getSeriesColor(index, options.defaultColor);
        const values = labels.map(label => {
            const item = series.find(entry => entry.label === label);
            return item ? item.value : null;
        });

        return {
            label: poolKey,
            data: values,
            borderColor: color,
            backgroundColor: `${color}33`,
            tension: 0.25,
            spanGaps: true,
            fill: false,
            pointRadius: 3,
            pointHoverRadius: 5,
        };
    });

    dashboardCharts[chartKey] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: function(value) {
                            return `${value} ${options.unit}`;
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const poolLabel = context.dataset.label || '';
                            return ` ${poolLabel}: ${context.parsed.y} ${options.unit}`;
                        }
                    }
                }
            }
        }
    });
}

function groupMeasurementsByPool(entries) {
    const grouped = {};
    entries.forEach(entry => {
        const poolKey = entry.pool_name || `Бассейн ${entry.pool_id || '—'}`;
        if (!grouped[poolKey]) {
            grouped[poolKey] = [];
        }
        grouped[poolKey].push(entry);
    });
    return grouped;
}

function getUnifiedLabels(grouped) {
    const labelSet = new Set();
    Object.values(grouped).forEach(series => {
        series.forEach(entry => labelSet.add(entry.label));
    });
    return Array.from(labelSet).sort((a, b) => {
        const [aDay, aMonth, aHour, aMinute] = parseLabel(a);
        const [bDay, bMonth, bHour, bMinute] = parseLabel(b);
        const dateA = new Date(2000, aMonth - 1, aDay, aHour, aMinute);
        const dateB = new Date(2000, bMonth - 1, bDay, bHour, bMinute);
        return dateA - dateB;
    });
}

function parseLabel(label) {
    const [datePart, timePart] = label.split(' ');
    const [day, month] = datePart.split('.').map(Number);
    const [hour, minute] = timePart.split(':').map(Number);
    return [day, month, hour, minute];
}

function getSeriesColor(index, fallback) {
    const palette = [
        '#0d6efd',
        '#198754',
        '#dc3545',
        '#fd7e14',
        '#20c997',
        '#6f42c1',
        '#6610f2',
        '#17a2b8',
        '#ffc107',
        '#adb5bd'
    ];
    return palette[index % palette.length] || fallback;
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

function loadMetersChart(container) {
    if (!container) return;
    container.innerHTML = `
        <div class="text-center py-3 text-muted">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;

    // Сначала загружаем список приборов
    fetch(`${baseUrl}api/meter_readings.php?action=widget_meters`)
        .then(response => response.json())
        .then(data => {
            if (data.success && Array.isArray(data.data) && data.data.length > 0) {
                metersChartState.meters = data.data;
                metersChartState.currentMeterIndex = 0;
                loadMeterData(container);
            } else {
                container.innerHTML = '<p class="text-muted mb-0">Нет доступных приборов учета</p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p class="text-danger mb-0">Ошибка при загрузке приборов</p>';
        });
}

function loadMeterData(container) {
    if (!container) return;
    const meters = metersChartState.meters;
    const currentIndex = metersChartState.currentMeterIndex;
    
    if (meters.length === 0 || currentIndex < 0 || currentIndex >= meters.length) {
        container.innerHTML = '<p class="text-muted mb-0">Нет доступных приборов учета</p>';
        return;
    }

    const currentMeter = meters[currentIndex];
    
    container.innerHTML = `
        <div class="text-center py-3 text-muted">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;

    fetch(`${baseUrl}api/meter_readings.php?action=widget_data&meter_id=${currentMeter.id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                metersChartState.data = Array.isArray(data.data.data) ? data.data.data : [];
                renderMetersChart(container);
            } else {
                container.innerHTML = '<p class="text-danger mb-0">Не удалось загрузить данные</p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p class="text-danger mb-0">Ошибка при загрузке данных</p>';
        });
}

function renderMetersChart(container) {
    if (!container) return;
    if (dashboardCharts.meters_chart) {
        dashboardCharts.meters_chart.destroy();
        dashboardCharts.meters_chart = null;
    }

    const meters = metersChartState.meters;
    const currentIndex = metersChartState.currentMeterIndex;
    const data = Array.isArray(metersChartState.data) ? metersChartState.data : [];
    
    if (meters.length === 0) {
        container.innerHTML = '<p class="text-muted mb-0">Нет доступных приборов учета</p>';
        return;
    }

    const currentMeter = meters[currentIndex];
    const canGoPrev = currentIndex > 0;
    const canGoNext = currentIndex < meters.length - 1;

    // Фильтруем данные, оставляя только те, где есть расход
    const chartData = data.filter(item => item.consumption !== null && item.consumption !== undefined);
    const labels = chartData.map(item => item.date_label);
    const values = chartData.map(item => {
        const consumption = typeof item.consumption === 'number' ? item.consumption : 0;
        return Math.max(0, consumption);
    });

    if (labels.length === 0) {
        container.innerHTML = `
            <div class="position-relative">
                <div class="d-flex justify-content-end mb-2">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" ${!canGoPrev ? 'disabled' : ''} data-action="prev-meter" title="Предыдущий прибор">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" disabled style="min-width: 120px; pointer-events: none;">
                            <small>${escapeHtml(currentMeter.name || `Прибор ${currentMeter.id}`)}</small>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" ${!canGoNext ? 'disabled' : ''} data-action="next-meter" title="Следующий прибор">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <p class="text-muted mb-0">Недостаточно данных для построения графика</p>
            </div>
        `;
        
        attachMeterNavigationHandlers(container);
        return;
    }

    container.innerHTML = `
        <div class="position-relative">
            <div class="d-flex justify-content-end mb-2">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" ${!canGoPrev ? 'disabled' : ''} data-action="prev-meter" title="Предыдущий прибор">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" disabled style="min-width: 120px; pointer-events: none;">
                        <small>${escapeHtml(currentMeter.name || `Прибор ${currentMeter.id}`)}</small>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" ${!canGoNext ? 'disabled' : ''} data-action="next-meter" title="Следующий прибор">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
            <div class="position-relative" style="height: 220px;">
                <canvas id="metersChartCanvas"></canvas>
            </div>
        </div>
    `;

    attachMeterNavigationHandlers(container);

    const canvas = container.querySelector('#metersChartCanvas');
    if (!canvas) {
        return;
    }

    const ctx = canvas.getContext('2d');

    dashboardCharts.meters_chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Расход',
                data: values,
                backgroundColor: '#ffc107',
                borderColor: '#ffc107',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatNumericValue(value, {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 2
                            });
                        }
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
                            const value = context && context.parsed && typeof context.parsed.y !== 'undefined'
                                ? context.parsed.y
                                : 0;
                            return ` Расход: ${formatNumericValue(value, {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 2
                            })}`;
                        }
                    }
                }
            }
        }
    });
}

function attachMeterNavigationHandlers(container) {
    const prevBtn = container.querySelector('[data-action="prev-meter"]');
    const nextBtn = container.querySelector('[data-action="next-meter"]');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            if (metersChartState.currentMeterIndex > 0) {
                metersChartState.currentMeterIndex--;
                loadMeterData(container);
            }
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            if (metersChartState.currentMeterIndex < metersChartState.meters.length - 1) {
                metersChartState.currentMeterIndex++;
                loadMeterData(container);
            }
        });
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

window.toggleTaskComplete = toggleTaskComplete;
})();