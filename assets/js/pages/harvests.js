(function() {
    'use strict';

    if (window.__harvestsPageInitialized) {
        return;
    }
    window.__harvestsPageInitialized = true;

    const config = window.harvestsConfig || {};
    const baseUrl = normalizeBaseUrl(config.baseUrl);
    const apiBase = new URL('.', baseUrl || window.location.href).toString();
    const isAdmin = Boolean(config.isAdmin);

    let currentEditId = null;
    let currentSessionId = null;
    let sessionsList = [];
    let counterpartiesList = [];

    function normalizeBaseUrl(value) {
        if (!value) {
            return '/';
        }
        return value.endsWith('/') ? value : `${value}/`;
    }

    function apiUrl(path) {
        return new URL(path, apiBase).toString();
    }

    function init() {
        loadSessions();
        if (isAdmin) {
            loadCounterpartiesList();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function clearFormErrors() {
        $('#recordForm .is-invalid').removeClass('is-invalid');
        $('#recordForm .invalid-feedback').remove();
    }

    function showFieldError(selector, message) {
        const input = $(selector);
        if (!input.length) {
            showAlert('danger', message);
            return;
        }
        if (!input.hasClass('is-invalid')) {
            input.addClass('is-invalid');
            input.after(`<div class="invalid-feedback">${escapeHtml(message)}</div>`);
        }
        if (typeof input.focus === 'function') {
            input.focus();
        }
    }

    function loadSessions() {
        $.ajax({
            url: apiUrl('api/harvests.php?action=get_active_sessions'),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    sessionsList = response.data || [];
                    createTabs(sessionsList);
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить сессии');
                }
            },
            error: function(xhr, status, error) {
                console.error('loadSessions error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке сессий');
            }
        });
    }

    function loadCounterpartiesList() {
        $.ajax({
            url: apiUrl('api/counterparties.php?action=list'),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    counterpartiesList = response.data || [];
                } else if (response.message) {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('loadCounterpartiesList error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке контрагентов');
            }
        });
    }

    function populateCounterpartySelect(selector, selectedId) {
        const select = typeof selector === 'string' ? $(selector) : selector;
        if (!select || !select.length) {
            return;
        }
        select.empty().append('<option value="">Не указан</option>');
        counterpartiesList.forEach(function(counterparty) {
            const label = counterparty.name ? escapeHtml(counterparty.name) : '—';
            const isSelected = selectedId !== null && selectedId !== undefined && counterparty.id == selectedId ? 'selected' : '';
            select.append(`<option value="${counterparty.id}" ${isSelected}>${label}</option>`);
        });
        select.prop('disabled', !isAdmin);
    }

    function createTabs(sessions) {
        const tabsNav = $('#poolsTabs');
        const tabsContent = $('#poolsTabContent');
        tabsNav.empty();
        tabsContent.empty();

        // Создаем табы для активных сессий
        if (sessions.length > 0) {
            let firstActiveIndex = 0;

            sessions.forEach(function(session, index) {
                const tabId = `session-${session.id}`;
                const isActive = index === firstActiveIndex;

                const tabHtml = `
                    <li class="nav-item" role="presentation">
                        <button class="nav-link ${isActive ? 'active' : ''}"
                                id="${tabId}-tab"
                                data-bs-toggle="tab" data-bs-target="#${tabId}" onclick="switchSession(${session.id})"
                                type="button"
                                role="tab"
                                title="${escapeHtml(session.pool_name || 'Бассейн')}: ${escapeHtml(session.session_name || session.name)}">
                            ${escapeHtml(session.pool_name || 'Бассейн')}: ${escapeHtml(session.session_name || session.name)}
                        </button>
                    </li>
                `;
                tabsNav.append(tabHtml);

                const colSpan = isAdmin ? 8 : 7;
                const contentHtml = `
                    <div class="tab-pane fade ${isActive ? 'show active' : ''}" id="${tabId}" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                    <h5 class="mb-0">
                                        Отборы для сессии "${escapeHtml(session.session_name || session.name)}"
                                        <small class="text-muted">(Бассейн: ${escapeHtml(session.pool_name || '')})</small>
                                    </h5>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openAddModal(${session.id})">
                                        <i class="bi bi-plus-circle"></i> Добавить отбор
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Дата и время</th>
                                                <th>Вес (кг)</th>
                                                <th>Количество рыб (шт)</th>
                                                <th>Контрагент</th>
                                                <th>Кто делал</th>
                                                ${isAdmin ? '<th class="text-center">Действия</th>' : ''}
                                            </tr>
                                        </thead>
                                        <tbody id="recordsBody-${session.id}">
                                            <tr>
                                                <td colspan="${colSpan}" class="text-center">
                                                    <div class="spinner-border spinner-border-sm" role="status">
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
                `;
                tabsContent.append(contentHtml);
            });

            // Добавляем таб "Прошлые сессии"
            const completedTabHtml = `
                <li class="nav-item" role="presentation">
                    <button class="nav-link"
                            id="completed-sessions-tab"
                            data-bs-toggle="tab" data-bs-target="#completed-sessions" onclick="loadCompletedSessions()"
                            type="button"
                            role="tab">
                        Прошлые сессии
                    </button>
                </li>
            `;
            tabsNav.append(completedTabHtml);

            const completedColSpan = isAdmin ? 8 : 7;
            const completedContentHtml = `
                <div class="tab-pane fade" id="completed-sessions" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3">Отборы из завершенных сессий</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Дата и время</th>
                                            <th>Сессия</th>
                                            <th>Бассейн</th>
                                            <th>Вес (кг)</th>
                                            <th>Количество рыб (шт)</th>
                                            <th>Контрагент</th>
                                            <th>Кто делал</th>
                                            ${isAdmin ? '<th class="text-center">Действия</th>' : ''}
                                        </tr>
                                    </thead>
                                    <tbody id="completedRecordsBody">
                                        <tr>
                                            <td colspan="${completedColSpan}" class="text-center text-muted">
                                                Нажмите на вкладку для загрузки данных
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            tabsContent.append(completedContentHtml);

            if (sessions.length > 0) {
                currentSessionId = sessions[firstActiveIndex].id;
                loadRecords(currentSessionId);
            }
        } else {
            tabsContent.html('<div class="alert alert-info">Нет активных сессий</div>');
        }
    }

    function switchSession(sessionId) {
        currentSessionId = sessionId;
        loadRecords(sessionId);
    }

    function loadRecords(sessionId) {
        const tbody = $(`#recordsBody-${sessionId}`);
        const colSpan = isAdmin ? 6 : 5;
        tbody.html(`
            <tr>
                <td colspan="${colSpan}" class="text-center">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </td>
            </tr>
        `);

        $.ajax({
            url: apiUrl(`api/harvests.php?action=list&session_id=${sessionId}`),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderRecords(response.data || [], sessionId);
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить записи');
                    tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-muted">Не удалось загрузить данные</td></tr>`);
                }
            },
            error: function(xhr, status, error) {
                console.error('loadRecords error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке записей');
                tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-danger">Ошибка при загрузке данных</td></tr>`);
            }
        });
    }

    function loadCompletedSessions() {
        const tbody = $('#completedRecordsBody');
        const colSpan = isAdmin ? 8 : 7;
        tbody.html(`
            <tr>
                <td colspan="${colSpan}" class="text-center">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </td>
            </tr>
        `);

        $.ajax({
            url: apiUrl('api/harvests.php?action=list_completed'),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderCompletedRecords(response.data || []);
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить записи');
                    tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-muted">Не удалось загрузить данные</td></tr>`);
                }
            },
            error: function(xhr, status, error) {
                console.error('loadCompletedSessions error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке записей');
                tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-danger">Ошибка при загрузке данных</td></tr>`);
            }
        });
    }

    function renderRecords(records, sessionId) {
        const tbody = $(`#recordsBody-${sessionId}`);
        tbody.empty();
        const colSpan = isAdmin ? 6 : 5;

        if (!records.length) {
            tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-muted">Записи не найдены</td></tr>`);
            return;
        }

        records.forEach(function(record) {
            const counterpartyName = record.counterparty_name ? escapeHtml(record.counterparty_name) : '—';
            const userInfo = record.created_by_full_name
                ? `${escapeHtml(record.created_by_full_name)} (${escapeHtml(record.created_by_login)})`
                : escapeHtml(record.created_by_login || 'Неизвестно');

            const canEdit = isAdmin || Boolean(record.can_edit);
            const canDelete = isAdmin;
            let actionsHtml = '';
            if (isAdmin) {
                actionsHtml = '<td class="text-center">';
                actionsHtml += `
                    <button class="btn btn-sm btn-primary me-2" onclick="openEditModal(${record.id})" title="Редактировать">
                        <i class="bi bi-pencil"></i>
                    </button>
                `;
                actionsHtml += `
                    <button class="btn btn-sm btn-danger" onclick="deleteRecord(${record.id})" title="Удалить">
                        <i class="bi bi-trash"></i>
                    </button>
                `;
                actionsHtml += '</td>';
            } else if (canEdit) {
                actionsHtml = '<td class="text-center">' +
                    `<button class="btn btn-sm btn-primary" onclick="openEditModal(${record.id})" title="Редактировать">` +
                    '<i class="bi bi-pencil"></i>' +
                    '</button>' +
                    '</td>';
            }

            const row = `
                <tr>
                    <td>${escapeHtml(record.recorded_at_display || '')}</td>
                    <td>${formatNumber(record.weight, 2)}</td>
                    <td>${formatInteger(record.fish_count)}</td>
                    <td>${counterpartyName}</td>
                    <td>${userInfo}</td>
                    ${actionsHtml || ''}
                </tr>
            `;
            tbody.append(row);
        });
    }

    function renderCompletedRecords(records) {
        const tbody = $('#completedRecordsBody');
        tbody.empty();
        const colSpan = isAdmin ? 8 : 7;

        if (!records.length) {
            tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-muted">Записи не найдены</td></tr>`);
            return;
        }

        records.forEach(function(record) {
            const counterpartyName = record.counterparty_name ? escapeHtml(record.counterparty_name) : '—';
            const userInfo = record.created_by_full_name
                ? `${escapeHtml(record.created_by_full_name)} (${escapeHtml(record.created_by_login)})`
                : escapeHtml(record.created_by_login || 'Неизвестно');
            const sessionName = record.session_name ? escapeHtml(record.session_name) : '—';
            const poolName = record.pool_name ? escapeHtml(record.pool_name) : '—';

            const canEdit = isAdmin || Boolean(record.can_edit);
            let actionsHtml = '';
            if (isAdmin) {
                actionsHtml = '<td class="text-center">';
                actionsHtml += `
                    <button class="btn btn-sm btn-primary me-2" onclick="openEditModal(${record.id})" title="Редактировать">
                        <i class="bi bi-pencil"></i>
                    </button>
                `;
                actionsHtml += `
                    <button class="btn btn-sm btn-danger" onclick="deleteRecord(${record.id})" title="Удалить">
                        <i class="bi bi-trash"></i>
                    </button>
                `;
                actionsHtml += '</td>';
            } else if (canEdit) {
                actionsHtml = '<td class="text-center">' +
                    `<button class="btn btn-sm btn-primary" onclick="openEditModal(${record.id})" title="Редактировать">` +
                    '<i class="bi bi-pencil"></i>' +
                    '</button>' +
                    '</td>';
            }

            const row = `
                <tr>
                    <td>${escapeHtml(record.recorded_at_display || '')}</td>
                    <td>${sessionName}</td>
                    <td>${poolName}</td>
                    <td>${formatNumber(record.weight, 2)}</td>
                    <td>${formatInteger(record.fish_count)}</td>
                    <td>${counterpartyName}</td>
                    <td>${userInfo}</td>
                    ${actionsHtml || ''}
                </tr>
            `;
            tbody.append(row);
        });
    }

    function openAddModal(sessionId) {
        currentEditId = null;
        clearFormErrors();
        $('#recordModalTitle').text('Добавить отбор');
        $('#recordForm')[0].reset();
        $('#recordId').val('');
        $('#recordPool').prop('disabled', false);

        const select = $('#recordPool');
        select.empty().append('<option value="">Выберите сессию</option>');
        sessionsList.forEach(function(session) {
            const selected = sessionId && session.id === sessionId ? 'selected' : '';
            const label = `${escapeHtml(session.pool_name || 'Бассейн')}: ${escapeHtml(session.session_name || session.name)}`;
            select.append(`<option value="${session.id}" ${selected}>${label}</option>`);
        });

        if (isAdmin) {
            $('#datetimeField').show();
            $('#recordDateTime').prop('required', true);
            $('#recordDateTime').val(toDateTimeLocalValue(new Date()));
        } else {
            $('#datetimeField').hide();
            $('#recordDateTime').prop('required', false);
        }

        populateCounterpartySelect('#recordCounterparty', null);

        if (sessionId) {
            $('#currentPoolId').val(sessionId);
            $('#recordPool').val(sessionId);
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('recordModal'));
        modal.show();
    }

    function openEditModal(id) {
        currentEditId = id;
        clearFormErrors();
        $('#recordModalTitle').text('Редактировать отбор');

        $.ajax({
            url: apiUrl(`api/harvests.php?action=get&id=${id}`),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const record = response.data || {};
                    $('#recordId').val(record.id || '');
                    $('#currentPoolId').val(record.session_id || '');

                    const select = $('#recordPool');
                    select.empty().append('<option value="">Выберите сессию</option>');
                    sessionsList.forEach(function(session) {
                        const selected = session.id === record.session_id ? 'selected' : '';
                        const label = `${escapeHtml(session.pool_name || 'Бассейн')}: ${escapeHtml(session.session_name || session.name)}`;
                        select.append(`<option value="${session.id}" ${selected}>${label}</option>`);
                    });

                    $('#recordPool').val(record.session_id || '');
                    $('#recordWeight').val(record.weight != null ? record.weight : '');
                    $('#recordFishCount').val(record.fish_count != null ? record.fish_count : '');
                    populateCounterpartySelect('#recordCounterparty', record.counterparty_id || null);

                    if (isAdmin) {
                        $('#datetimeField').show();
                        $('#recordDateTime').prop('required', true);
                        $('#recordPool').prop('disabled', false);
                        const recordedAt = record.recorded_at ? toDateTimeLocalValue(new Date(record.recorded_at.replace(' ', 'T'))) : '';
                        $('#recordDateTime').val(recordedAt);
                        $('#recordDateTime').prop('disabled', false);
                    } else {
                        $('#datetimeField').hide();
                        $('#recordDateTime').prop('required', false);
                        $('#recordPool').prop('disabled', true);
                    }

                    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('recordModal'));
                    modal.show();
                } else {
                    showAlert('danger', response.message || 'Не удалось получить запись');
                }
            },
            error: function(xhr, status, error) {
                console.error('openEditModal error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке записи');
            }
        });
    }

    function saveRecord() {
        const form = $('#recordForm')[0];
        clearFormErrors();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const sessionIdValue = $('#currentPoolId').val() || $('#recordPool').val();
        const payload = {
            session_id: parseInt(sessionIdValue, 10),
            weight: parseFloat($('#recordWeight').val()),
            fish_count: parseInt($('#recordFishCount').val(), 10)
        };

        if (currentEditId) {
            payload.id = currentEditId;
        }

        const counterpartyValue = $('#recordCounterparty').val();
        if (counterpartyValue) {
            payload.counterparty_id = parseInt(counterpartyValue, 10);
        }

        if (isAdmin && $('#recordDateTime').val()) {
            payload.recorded_at = $('#recordDateTime').val().replace('T', ' ');
        }

        const action = currentEditId ? 'update' : 'create';

        $.ajax({
            url: apiUrl(`api/harvests.php?action=${action}`),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Запись сохранена');
                    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('recordModal'));
                    modal.hide();
                    if (payload.session_id) {
                        loadRecords(payload.session_id);
                    }
                } else {
                    handleFormError(response.message || 'Не удалось сохранить запись', response.field || null);
                }
            },
            error: function(xhr, status, error) {
                console.error('saveRecord error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                handleFormError(response.message || 'Ошибка при сохранении записи', response.field || null);
            }
        });
    }

    function handleFormError(message, field) {
        const fieldMap = {
            session_id: '#recordPool',
            pool_id: '#recordPool', // для обратной совместимости
            weight: '#recordWeight',
            fish_count: '#recordFishCount',
            counterparty_id: '#recordCounterparty',
            recorded_at: '#recordDateTime'
        };

        if (field && fieldMap[field]) {
            showFieldError(fieldMap[field], message);
            return;
        }

        const lower = message ? message.toLowerCase() : '';
        if (lower.includes('сессия') || lower.includes('бассейн')) {
            showFieldError('#recordPool', message);
        } else if (lower.includes('вес')) {
            showFieldError('#recordWeight', message);
        } else if (lower.includes('количество')) {
            showFieldError('#recordFishCount', message);
        } else if (lower.includes('контрагент')) {
            showFieldError('#recordCounterparty', message);
        } else if (lower.includes('дата')) {
            showFieldError('#recordDateTime', message);
        } else {
            showAlert('danger', message);
        }
    }

    function deleteRecord(id) {
        if (!confirm('Удалить запись?')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/harvests.php?action=delete'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Запись удалена');
                    if (currentSessionId) {
                        loadRecords(currentSessionId);
                    } else {
                        loadSessions();
                    }
                } else {
                    showAlert('danger', response.message || 'Не удалось удалить запись');
                }
            },
            error: function(xhr, status, error) {
                console.error('deleteRecord error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при удалении записи');
            }
        });
    }

    function showAlert(type, message) {
        $('#alert-container').html(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
    }

    function formatNumber(value, fractionDigits) {
        if (value === null || value === undefined || isNaN(value)) {
            return '<span class="text-muted">—</span>';
        }
        return Number(value).toLocaleString('ru-RU', {
            minimumFractionDigits: fractionDigits,
            maximumFractionDigits: fractionDigits
        });
    }

    function formatInteger(value) {
        if (value === null || value === undefined || isNaN(value)) {
            return '<span class="text-muted">—</span>';
        }
        return Number(value).toLocaleString('ru-RU');
    }

    function toDateTimeLocalValue(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }
        const pad = (value) => String(value).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        return String(text).replace(/[&<>"']/g, function(m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[m];
        });
    }

    window.switchSession = switchSession;
    window.loadCompletedSessions = loadCompletedSessions;
    window.openAddModal = openAddModal;
    window.openEditModal = openEditModal;
    window.saveRecord = saveRecord;
    window.deleteRecord = deleteRecord;
})();
