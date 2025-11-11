(function () {
    'use strict';

    if (window.__sessionsPageInitialized) {
        return;
    }
    window.__sessionsPageInitialized = true;

    const config = window.sessionsConfig || {};
    const baseUrl = config.baseUrl || '';
    const apiBase = new URL('.', baseUrl || window.location.href).toString();

    let currentEditId = null;
    let currentTab = 0;
    let poolsCache = [];
    let plantingsCache = [];

    function apiUrl(path) {
        return new URL(path, apiBase).toString();
    }

    document.addEventListener('DOMContentLoaded', function () {
        preloadSelectOptions();
        loadSessions(0);
    });

    function preloadSelectOptions() {
        $.ajax({
            url: apiUrl('api/sessions.php?action=get_pools'),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    poolsCache = response.data || [];
                }
            },
        });

        $.ajax({
            url: apiUrl('api/sessions.php?action=get_plantings'),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    plantingsCache = response.data || [];
                }
            },
        });
    }

    function loadSessions(completed) {
        currentTab = completed;
        const bodyId = completed ? '#completedSessionsBody' : '#activeSessionsBody';
        const colspan = completed ? 11 : 8;
        const tbody = $(bodyId);
        tbody.html(`
            <tr>
                <td colspan="${colspan}" class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </td>
            </tr>
        `);

        $.ajax({
            url: apiUrl(`api/sessions.php?action=list&completed=${completed ? 1 : 0}`),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderSessionsTable(response.data || [], tbody, completed);
                } else {
                    showAlert('danger', response.message || 'Ошибка при загрузке сессий');
                }
            },
            error: function (xhr, status, error) {
                console.error('loadSessions error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке сессий');
            },
        });
    }

    function renderSessionsTable(sessions, tbody, isCompleted) {
        tbody.empty();

        if (!sessions.length) {
            const colspan = isCompleted ? 11 : 8;
            tbody.html(`<tr><td colspan="${colspan}" class="text-center text-muted">Сессии не найдены</td></tr>`);
            return;
        }

        sessions.forEach(function (session) {
            if (isCompleted) {
                const fcrDisplay = session.fcr !== undefined && session.fcr !== null ? Number(session.fcr).toFixed(4) : '-';
                tbody.append(`
                    <tr>
                        <td>${session.id}</td>
                        <td>${escapeHtml(session.name)}</td>
                        <td>${escapeHtml(session.pool_name || '-')}</td>
                        <td>${escapeHtml(session.planting_name || '-')}</td>
                        <td>${escapeHtml(session.start_date || '-')}</td>
                        <td>${escapeHtml(session.end_date || '-')}</td>
                        <td>${session.start_mass ?? '-'}</td>
                        <td>${session.end_mass ?? '-'}</td>
                        <td>${session.feed_amount ?? '-'}</td>
                        <td>${fcrDisplay}</td>
                        <td>
                            <button class="btn btn-sm btn-primary me-1" onclick="openEditModal(${session.id})" title="Редактировать">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteSession(${session.id})" title="Удалить">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            } else {
                tbody.append(`
                    <tr>
                        <td>${session.id}</td>
                        <td>${escapeHtml(session.name)}</td>
                        <td>${escapeHtml(session.pool_name || '-')}</td>
                        <td>${escapeHtml(session.planting_name || '-')}</td>
                        <td>${escapeHtml(session.start_date || '-')}</td>
                        <td>${session.start_mass ?? '-'}</td>
                        <td>${session.start_fish_count ?? '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-primary me-1" onclick="openEditModal(${session.id})" title="Редактировать">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-success me-1" onclick="openCompleteModal(${session.id})" title="Завершить">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteSession(${session.id})" title="Удалить">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            }
        });
    }

    function openAddModal() {
        currentEditId = null;
        $('#sessionModalTitle').text('Добавить сессию');
        $('#sessionForm')[0].reset();
        $('#sessionId').val('');
        setCurrentDate('#sessionStartDate');
        populateSelect('#sessionPool', poolsCache, 'Выберите бассейн');
        populateSelect('#sessionPlanting', plantingsCache, 'Выберите посадку');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('sessionModal')).show();
    }

    function openEditModal(id) {
        currentEditId = id;
        $('#sessionModalTitle').text('Редактировать сессию');
        populateSelect('#sessionPool', poolsCache, 'Выберите бассейн');
        populateSelect('#sessionPlanting', plantingsCache, 'Выберите посадку');

        $.ajax({
            url: apiUrl(`api/sessions.php?action=get&id=${id}`),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    const session = response.data;
                    $('#sessionId').val(session.id);
                    $('#sessionName').val(session.name);
                    $('#sessionPool').val(session.pool_id);
                    $('#sessionPlanting').val(session.planting_id);
                    $('#sessionStartDate').val(session.start_date);
                    $('#sessionStartMass').val(session.start_mass);
                    $('#sessionStartFishCount').val(session.start_fish_count);
                    $('#sessionPreviousFcr').val(session.previous_fcr || '');

                    if (session.is_completed) {
                        $('#completeSessionId').val(session.id);
                        $('#completeEndDate').val(session.end_date || getToday());
                        $('#completeEndMass').val(session.end_mass || '');
                        $('#completeFeedAmount').val(session.feed_amount || '');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('completeModal')).show();
                    } else {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('sessionModal')).show();
                    }
                } else {
                    showAlert('danger', response.message || 'Не удалось получить данные сессии');
                }
            },
            error: function (xhr, status, error) {
                console.error('openEditModal error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке данных сессии');
            },
        });
    }

    function saveSession() {
        const form = $('#sessionForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const payload = {
            name: $('#sessionName').val().trim(),
            pool_id: parseInt($('#sessionPool').val(), 10),
            planting_id: parseInt($('#sessionPlanting').val(), 10),
            start_date: $('#sessionStartDate').val(),
            start_mass: parseFloat($('#sessionStartMass').val()),
            start_fish_count: parseInt($('#sessionStartFishCount').val(), 10),
            previous_fcr: valueOrNull('#sessionPreviousFcr'),
        };

        if (currentEditId) {
            payload.id = currentEditId;
        }

        const action = currentEditId ? 'update' : 'create';

        $.ajax({
            url: apiUrl(`api/sessions.php?action=${action}`),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Изменения сохранены');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('sessionModal')).hide();
                    loadSessions(currentTab);
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить сессию');
                }
            },
            error: function (xhr, status, error) {
                console.error('saveSession error:', status, error, xhr.responseText);
                const resp = xhr.responseJSON || {};
                showAlert('danger', resp.message || 'Ошибка при сохранении сессии');
            },
        });
    }

    function openCompleteModal(id) {
        $.ajax({
            url: apiUrl(`api/sessions.php?action=get&id=${id}`),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    const session = response.data;
                    $('#completeSessionId').val(session.id);
                    $('#completeEndDate').val(getToday());
                    $('#completeEndMass').val('');
                    $('#completeFeedAmount').val('');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('completeModal')).show();
                } else {
                    showAlert('danger', response.message || 'Не удалось получить данные сессии');
                }
            },
            error: function (xhr, status, error) {
                console.error('openCompleteModal error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке данных сессии');
            },
        });
    }

    function completeSession() {
        const form = $('#completeForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const payload = {
            id: parseInt($('#completeSessionId').val(), 10),
            end_date: $('#completeEndDate').val(),
            end_mass: parseFloat($('#completeEndMass').val()),
            feed_amount: parseFloat($('#completeFeedAmount').val()),
        };

        $.ajax({
            url: apiUrl('api/sessions.php?action=complete'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('completeModal')).hide();
                    showAlert('success', response.message || 'Сессия завершена');
                    loadSessions(0);
                    setTimeout(function () {
                        document.getElementById('completed-sessions-tab').click();
                        loadSessions(1);
                    }, 300);
                } else {
                    showAlert('danger', response.message || 'Не удалось завершить сессию');
                }
            },
            error: function (xhr, status, error) {
                console.error('completeSession error:', status, error, xhr.responseText);
                const resp = xhr.responseJSON || {};
                showAlert('danger', resp.message || 'Ошибка при завершении сессии');
            },
        });
    }

    function deleteSession(id) {
        if (!confirm('Вы уверены, что хотите удалить эту сессию?')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/sessions.php?action=delete'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id }),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Сессия удалена');
                    loadSessions(currentTab);
                } else {
                    showAlert('danger', response.message || 'Не удалось удалить сессию');
                }
            },
            error: function (xhr, status, error) {
                console.error('deleteSession error:', status, error, xhr.responseText);
                const resp = xhr.responseJSON || {};
                showAlert('danger', resp.message || 'Ошибка при удалении сессии');
            },
        });
    }

    function populateSelect(selector, items, placeholder) {
        const select = $(selector);
        select.empty().append(`<option value="">${placeholder}</option>`);
        items.forEach(function (item) {
            let label = item.name;
            if (item.fish_breed) {
                label += ` (${item.fish_breed})`;
            }
            select.append(`<option value="${item.id}">${escapeHtml(label)}</option>`);
        });
    }

    function showAlert(type, message) {
        const html = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('#alert-container').html(html);
        setTimeout(() => $('.alert').alert('close'), 5000);
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
            "'": '&#039;',
        };
        return String(text).replace(/[&<>"']/g, function (m) {
            return map[m];
        });
    }

    function setCurrentDate(selector) {
        const input = document.querySelector(selector);
        if (!input) {
            return;
        }
        input.value = getToday();
    }

    function getToday() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function valueOrNull(selector) {
        const value = $(selector).val();
        if (value === null || value === undefined || value === '') {
            return null;
        }
        return parseFloat(value);
    }

    window.loadSessions = loadSessions;
    window.openAddModal = openAddModal;
    window.openEditModal = openEditModal;
    window.saveSession = saveSession;
    window.openCompleteModal = openCompleteModal;
    window.completeSession = completeSession;
    window.deleteSession = deleteSession;
})();


