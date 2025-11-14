(function () {
    'use strict';

    const templatesList = document.getElementById('shiftTemplatesList');
    const checklistContainer = document.getElementById('shiftTasksChecklist');
    if (!templatesList && !checklistContainer) {
        return;
    }

    const apiBase = window.BASE_URL + 'api/shift_tasks.php';
    const state = {
        templates: [],
        tasks: [],
        isAdmin: true,
    };

    const templateModalEl = document.getElementById('templateModal');
    const templateModal = templateModalEl ? new bootstrap.Modal(templateModalEl) : null;
    const deleteModalEl = document.getElementById('deleteTemplateModal');
    const deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;
    const templateForm = document.getElementById('templateForm');
    const deleteConfirmBtn = document.getElementById('confirmDeleteTemplate');
    const deleteTitleEl = document.getElementById('deleteTemplateTitle');
    const createBtn = document.getElementById('createTemplateBtn');
    const refreshBtn = document.getElementById('refreshShiftTasks');
    const dateLabel = document.getElementById('shiftTasksDateLabel');
    const alertsContainer = document.getElementById('shiftTasksAlerts');
    const frequencySelect = document.getElementById('frequencySelect');

    let deleteTemplateId = null;

    function showAlert(type, message) {
        if (!alertsContainer) return;
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.role = 'alert';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        alertsContainer.appendChild(alert);
        setTimeout(() => {
            alert.classList.remove('show');
            alert.addEventListener('transitionend', () => alert.remove(), { once: true });
        }, 4000);
    }

    function fetchJson(url, options = {}) {
        return fetch(url, options)
            .then(response => response.json());
    }

    function formatFrequency(template) {
        switch (template.frequency) {
            case 'daily':
                return 'Каждый день';
            case 'weekly':
                return 'Раз в неделю';
            case 'biweekly':
                return 'Раз в две недели';
            case 'monthly':
                return 'Раз в месяц';
            default:
                return template.frequency;
        }
    }

    function renderTemplates() {
        if (!templatesList) return;
        if (!state.templates.length) {
            templatesList.innerHTML = `
                <div class="text-center text-muted py-4">
                    Шаблоны не настроены
                </div>
            `;
            return;
        }

        templatesList.innerHTML = state.templates.map(template => `
            <div class="list-group-item shift-template-item d-flex justify-content-between align-items-center" data-template-id="${template.id}">
                <div class="d-flex align-items-start gap-3">
                    <button class="btn btn-link p-0 shift-template-handle" type="button" title="Переместить">
                        <i class="bi bi-grip-vertical"></i>
                    </button>
                    <div>
                        <div class="fw-semibold">${escapeHtml(template.title)}</div>
                        <div class="text-muted small mb-1">${escapeHtml(template.description || '')}</div>
                        <div class="d-flex flex-wrap gap-2 small text-muted">
                            <span><i class="bi bi-arrow-repeat me-1"></i>${formatFrequency(template)}</span>
                            <span><i class="bi bi-clock me-1"></i>${template.due_time?.slice(0, 5) || '—'}</span>
                            ${template.is_active ? '<span class="badge bg-success-subtle text-success-emphasis">Активно</span>' : '<span class="badge bg-secondary-subtle text-secondary-emphasis">Отключено</span>'}
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-primary" data-action="edit-template" title="Редактировать">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" data-action="delete-template" title="Удалить">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
        initTemplatesSortable();
    }
    function initTemplatesSortable() {
        if (!templatesList) return;
        if (window.shiftTemplatesSortable) {
            window.shiftTemplatesSortable.destroy();
        }

        window.shiftTemplatesSortable = new Sortable(templatesList, {
            animation: 150,
            handle: '.shift-template-handle',
            ghostClass: 'shift-template-ghost',
            onEnd: saveTemplatesOrder,
        });
    }

    function saveTemplatesOrder() {
        const rows = Array.from(templatesList.querySelectorAll('.shift-template-item'));
        const order = rows.map(row => Number(row.dataset.templateId));

        fetchJson(`${apiBase}?action=reorder_templates`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order }),
        })
            .then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Не удалось сохранить порядок');
                }
                showAlert('success', 'Порядок сохранен');
            })
            .catch(error => showAlert('danger', error.message));
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function loadTemplates() {
        fetchJson(`${apiBase}?action=templates`)
            .then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Не удалось загрузить шаблоны');
                }
                const payload = response.data || {};
                state.templates = payload.templates || [];
                renderTemplates();
            })
            .catch(error => showAlert('danger', error.message || 'Ошибка загрузки шаблонов'));
    }

    function fillTemplateForm(template) {
        templateForm.reset();
        templateForm.querySelector('[name="id"]').value = template?.id || '';
        templateForm.querySelector('[name="title"]').value = template?.title || '';
        templateForm.querySelector('[name="description"]').value = template?.description || '';
        templateForm.querySelector('[name="due_time"]').value = (template?.due_time || '12:00:00').slice(0, 5);
        templateForm.querySelector('[name="frequency"]').value = template?.frequency || 'daily';
        templateForm.querySelector('[name="start_date"]').value = template?.start_date || getDutyDate();
        templateForm.querySelector('[name="week_day"]').value = template?.week_day ?? 1;
        templateForm.querySelector('[name="day_of_month"]').value = template?.day_of_month ?? 1;
        templateForm.querySelector('[name="is_active"]').checked = template ? Boolean(Number(template.is_active ?? template.is_active)) : true;

        updateFrequencyVisibility(template?.frequency || 'daily');
    }

    function updateFrequencyVisibility(frequency) {
        document.querySelectorAll('.frequency-dependent').forEach(block => {
            const allowed = block.dataset.frequency.split(' ');
            block.classList.toggle('active', allowed.includes(frequency));
        });
    }

    function getDutyDate() {
        const now = new Date();
        if (now.getHours() < 8) {
            now.setDate(now.getDate() - 1);
        }
        return now.toISOString().split('T')[0];
    }

    function loadShiftTasks() {
        if (!checklistContainer) return;
        checklistContainer.innerHTML = `
            <div class="text-center text-muted py-4">
                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                Загрузка заданий смены...
            </div>
        `;

        fetchJson(`${apiBase}?action=list`)
            .then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Не удалось загрузить задания');
                }
                const payload = response.data || {};
                state.tasks = payload.tasks || [];
                state.isAdmin = !!payload.is_admin;
                if (dateLabel && payload.date) {
                    const date = new Date(payload.date);
                    dateLabel.textContent = date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
                }
                renderShiftTasks();
            })
            .catch(error => {
                checklistContainer.innerHTML = `
                    <p class="text-danger mb-0"><i class="bi bi-exclamation-triangle"></i> ${escapeHtml(error.message)}</p>
                `;
            });
    }

    function renderShiftTasks() {
        if (!checklistContainer) return;
        if (!state.tasks.length) {
            checklistContainer.innerHTML = `
                <div class="text-center text-muted py-4">
                    Нет заданий для текущей смены
                </div>
            `;
            return;
        }

        checklistContainer.innerHTML = state.tasks.map(task => {
            const isCompleted = task.status === 'completed';
            const statusClass = isCompleted ? 'completed' : (task.is_overdue ? 'overdue' : 'pending');
            const timeClass = task.is_overdue ? 'overdue' : 'upcoming';

            return `
                <div class="shift-task-item ${isCompleted ? 'completed' : ''}" data-task-id="${task.id}">
                    <div class="shift-task-checkbox form-check">
                        <input class="form-check-input" type="checkbox" ${isCompleted ? 'checked' : ''} ${state.isAdmin ? '' : ''}>
                    </div>
                    <div class="shift-task-content">
                        <div class="shift-task-title">${escapeHtml(task.title || '')}</div>
                        <div class="text-muted mb-2 small">${escapeHtml(task.description || '')}</div>
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                            <div class="shift-task-time ${timeClass}">
                                ${escapeHtml(task.time_diff_label || '')}
                            </div>
                            <div class="shift-task-status-label ${statusClass}">
                                ${isCompleted ? '<i class="bi bi-check-circle-fill"></i> Выполнено' : (task.is_overdue ? '<i class="bi bi-exclamation-triangle-fill"></i> Просрочено' : '<i class="bi bi-hourglass-split"></i> Ожидает')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function handleTemplateSubmission(event) {
        event.preventDefault();
        const formData = new FormData(templateForm);
        const payload = Object.fromEntries(formData.entries());
        payload.is_active = formData.get('is_active') === 'on';

        fetchJson(`${apiBase}?action=save_template`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Не удалось сохранить шаблон');
                }
                templateModal?.hide();
                showAlert('success', 'Шаблон сохранен');
                loadTemplates();
                loadShiftTasks();
            })
            .catch(error => showAlert('danger', error.message));
    }

    function handleTemplateAction(event) {
        const target = event.target.closest('button[data-action]');
        if (!target) {
            return;
        }
        const action = target.dataset.action;
        const row = target.closest('.shift-template-item');
        const templateId = Number(row?.dataset.templateId);
        const template = state.templates.find(item => item.id == templateId);

        if (action === 'edit-template') {
            document.getElementById('templateModalTitle').textContent = 'Редактирование шаблона';
            fillTemplateForm(template);
            templateModal?.show();
        } else if (action === 'delete-template') {
            deleteTemplateId = templateId;
            deleteTitleEl.textContent = template?.title || '';
            deleteModal?.show();
        }
    }

    function handleDeleteTemplate() {
        if (!deleteTemplateId) return;
        fetchJson(`${apiBase}?action=delete_template`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: deleteTemplateId }),
        })
            .then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Не удалось удалить шаблон');
                }
                deleteModal?.hide();
                showAlert('success', 'Шаблон удален');
                loadTemplates();
                loadShiftTasks();
            })
            .catch(error => showAlert('danger', error.message))
            .finally(() => {
                deleteTemplateId = null;
            });
    }

    function toggleTask(taskId, completed, checkbox) {
        if (!taskId) return;
        const confirmMessage = completed ? 'Отметить задание как выполненное?' : 'Вернуть задание в работу?';
        if (!confirm(confirmMessage)) {
            checkbox.checked = !completed;
            return;
        }

        fetchJson(`${apiBase}?action=toggle`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskId, completed }),
        })
            .then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Не удалось обновить задание');
                }
                const payload = response.data || {};
                const updatedTask = payload.task;
                const idx = state.tasks.findIndex(task => task.id == taskId);
                if (idx >= 0 && updatedTask) {
                    state.tasks[idx] = updatedTask;
                }
                renderShiftTasks();
            })
            .catch(error => {
                showAlert('danger', error.message);
                checkbox.checked = !completed;
            });
    }

    function handleChecklistClick(event) {
        const checkbox = event.target.closest('.form-check-input');
        if (!checkbox) return;
        const item = checkbox.closest('.shift-task-item');
        const taskId = Number(item?.dataset.taskId);
        toggleTask(taskId, checkbox.checked, checkbox);
    }

    // Event listeners
    templatesList?.addEventListener('click', handleTemplateAction);
    checklistContainer?.addEventListener('click', handleChecklistClick);
    templateForm?.addEventListener('submit', handleTemplateSubmission);
    deleteConfirmBtn?.addEventListener('click', handleDeleteTemplate);
    createBtn?.addEventListener('click', () => {
        document.getElementById('templateModalTitle').textContent = 'Новый шаблон';
        fillTemplateForm(null);
        templateModal?.show();
    });
    refreshBtn?.addEventListener('click', loadShiftTasks);
    frequencySelect?.addEventListener('change', event => updateFrequencyVisibility(event.target.value));

    // Init
    loadTemplates();
    loadShiftTasks();
})();


