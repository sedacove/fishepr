(function() {
    'use strict';

    if (window.__mortalityPageInitialized) {
        return;
    }
    window.__mortalityPageInitialized = true;

    const config = window.mortalityConfig || {};
    const baseUrl = normalizeBaseUrl(config.baseUrl);
    const apiBase = new URL('.', baseUrl || window.location.href).toString();
    const isAdmin = Boolean(config.isAdmin);

    let currentEditId = null;
    let currentPoolId = null;
    let poolsList = [];

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
        loadPools();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function loadPools() {
        $.ajax({
            url: apiUrl('api/mortality.php?action=get_pools'),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    poolsList = response.data || [];
                    createTabs(poolsList);
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить бассейны');
                }
            },
            error: function(xhr, status, error) {
                console.error('loadPools error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке бассейнов');
            }
        });
    }

    function createTabs(pools) {
        const tabsNav = $('#poolsTabs');
        const tabsContent = $('#poolsTabContent');
        tabsNav.empty();
        tabsContent.empty();

        if (!pools.length) {
            tabsContent.html('<div class="alert alert-info">Нет активных бассейнов</div>');
            return;
        }

        let firstActiveIndex = -1;
        pools.forEach(function(pool, index) {
            if (pool.active_session && firstActiveIndex === -1) {
                firstActiveIndex = index;
            }
        });

        pools.forEach(function(pool, index) {
            const tabId = `pool-${pool.id}`;
            const hasSession = Boolean(pool.active_session);
            const isActive = firstActiveIndex !== -1 && index === firstActiveIndex;

            const tabHtml = `
                <li class="nav-item" role="presentation">
                    <button class="nav-link ${isActive ? 'active' : ''} ${hasSession ? '' : 'disabled'}"
                            id="${tabId}-tab"
                            ${hasSession ? `data-bs-toggle="tab" data-bs-target="#${tabId}" onclick="switchPool(${pool.id})"` : ''}
                            type="button"
                            role="tab"
                            ${hasSession ? '' : 'disabled'}
                            title="${hasSession ? `${escapeHtml(pool.name)}: ${escapeHtml(pool.active_session.session_name)}` : 'Нет активной сессии'}">
                        ${hasSession ? `${escapeHtml(pool.name)}: ${escapeHtml(pool.active_session.session_name)}` : '<i class="bi bi-x-circle text-muted"></i>'}
                    </button>
                </li>
            `;
            tabsNav.append(tabHtml);

            const colSpan = isAdmin ? 5 : 4;
            const contentHtml = `
                <div class="tab-pane fade ${isActive ? 'show active' : ''}" id="${tabId}" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <h5 class="mb-0">
                                    Падеж для бассейна "${escapeHtml(pool.name)}"
                                    ${hasSession ? `<small class="text-muted">(Сессия: ${escapeHtml(pool.active_session.session_name)})</small>` : '<small class="text-muted">(Нет активной сессии)</small>'}
                                </h5>
                                <button type="button" class="btn btn-sm btn-primary" onclick="openAddModal(${pool.id})">
                                    <i class="bi bi-plus-circle"></i> Зарегистрировать падеж
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Дата и время</th>
                                            <th>Вес (кг)</th>
                                            <th>Количество рыб (шт)</th>
                                            <th>Кто делал</th>
                                            ${isAdmin ? '<th class="text-center">Действия</th>' : ''}
                                        </tr>
                                    </thead>
                                    <tbody id="recordsBody-${pool.id}">
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

        if (firstActiveIndex !== -1) {
            currentPoolId = pools[firstActiveIndex].id;
            loadRecords(currentPoolId);
        }
    }

    function switchPool(poolId) {
        currentPoolId = poolId;
        loadRecords(poolId);
    }

    function loadRecords(poolId) {
        const tbody = $(`#recordsBody-${poolId}`);
        const colSpan = isAdmin ? 5 : 4;
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
            url: apiUrl(`api/mortality.php?action=list&pool_id=${poolId}`),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderRecords(response.data || [], poolId);
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

    function renderRecords(records, poolId) {
        const tbody = $(`#recordsBody-${poolId}`);
        tbody.empty();
        const colSpan = isAdmin ? 5 : 4;

        if (!records.length) {
            tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-muted">Записи не найдены</td></tr>`);
            return;
        }

        records.forEach(function(record) {
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
                    <td>${userInfo}</td>
                    ${actionsHtml || ''}
                </tr>
            `;
            tbody.append(row);
        });
    }

    function openAddModal(poolId) {
        currentEditId = null;
        $('#recordModalTitle').text('Добавить падеж');
        $('#recordForm')[0].reset();
        $('#recordId').val('');
        $('#recordPool').prop('disabled', false);

        const select = $('#recordPool');
        select.empty().append('<option value="">Выберите бассейн</option>');
        poolsList.forEach(function(pool) {
            const selected = poolId && pool.id === poolId ? 'selected' : '';
            select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
        });

        if (isAdmin) {
            $('#datetimeField').show();
            $('#recordDateTime').prop('required', true);
            $('#recordDateTime').val(toDateTimeLocalValue(new Date()));
        } else {
            $('#datetimeField').hide();
            $('#recordDateTime').prop('required', false);
        }

        if (poolId) {
            $('#currentPoolId').val(poolId);
            $('#recordPool').val(poolId);
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('recordModal'));
        modal.show();
    }

    function openEditModal(id) {
        currentEditId = id;
        $('#recordModalTitle').text('Редактировать падеж');

        $.ajax({
            url: apiUrl(`api/mortality.php?action=get&id=${id}`),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const record = response.data || {};
                    $('#recordId').val(record.id || '');
                    $('#currentPoolId').val(record.pool_id || '');

                    const select = $('#recordPool');
                    select.empty().append('<option value="">Выберите бассейн</option>');
                    poolsList.forEach(function(pool) {
                        const selected = pool.id === record.pool_id ? 'selected' : '';
                        select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
                    });

                    $('#recordPool').val(record.pool_id || '');
                    $('#recordWeight').val(record.weight != null ? record.weight : '');
                    $('#recordFishCount').val(record.fish_count != null ? record.fish_count : '');

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
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const poolIdValue = $('#currentPoolId').val() || $('#recordPool').val();
        const payload = {
            pool_id: parseInt(poolIdValue, 10),
            weight: parseFloat($('#recordWeight').val()),
            fish_count: parseInt($('#recordFishCount').val(), 10)
        };

        if (currentEditId) {
            payload.id = currentEditId;
        }

        if (isAdmin && $('#recordDateTime').val()) {
            payload.recorded_at = $('#recordDateTime').val().replace('T', ' ');
        }

        toggleRecordSavingState(true);

        const action = currentEditId ? 'update' : 'create';

        $.ajax({
            url: apiUrl(`api/mortality.php?action=${action}`),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Запись сохранена');
                    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('recordModal'));
                    modal.hide();
                    if (payload.pool_id) {
                        loadRecords(payload.pool_id);
                    }
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить запись');
                }
            },
            error: function(xhr, status, error) {
                console.error('saveRecord error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при сохранении записи');
            }
        }).always(function() {
            toggleRecordSavingState(false);
        });
    }

    function deleteRecord(id) {
        if (!confirm('Удалить запись?')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/mortality.php?action=delete'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Запись удалена');
                    if (currentPoolId) {
                        loadRecords(currentPoolId);
                    } else {
                        loadPools();
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

    function toggleRecordSavingState(isSaving) {
        const btn = $('#recordModal .modal-footer .btn-primary');
        if (!btn.length) {
            return;
        }

        if (isSaving) {
            if (!btn.data('original-html')) {
                btn.data('original-html', btn.html());
            }
            btn.prop('disabled', true);
            btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
        } else {
            const original = btn.data('original-html');
            btn.html(original || 'Сохранить');
            btn.prop('disabled', false);
            btn.removeData('original-html');
        }
    }

    function showAlert(type, message) {
        $('#alert-container').html(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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

    window.switchPool = switchPool;
    window.openAddModal = openAddModal;
    window.openEditModal = openEditModal;
    window.saveRecord = saveRecord;
    window.deleteRecord = deleteRecord;
})();
