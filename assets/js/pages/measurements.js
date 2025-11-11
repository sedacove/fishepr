(function() {
    'use strict';

    if (window.__measurementsPageInitialized) {
        return;
    }
    window.__measurementsPageInitialized = true;

    const config = window.measurementsConfig || {};
    const baseUrl = normalizeBaseUrl(config.baseUrl);
    const apiBase = new URL('.', baseUrl || window.location.href).toString();
    function normalizeBaseUrl(value) {
        if (!value) {
            return '/';
        }
        return value.endsWith('/') ? value : `${value}/`;
    }
    const isAdmin = Boolean(config.isAdmin);

    let currentEditId = null;
    let currentPoolId = null;
    let poolsList = [];

    function init() {
        loadPools();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function apiUrl(path) {
        return new URL(path, apiBase).toString();
    }

    function loadPools() {
        $.ajax({
            url: apiUrl('api/measurements.php?action=get_pools'),
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
                                    Замеры для бассейна "${escapeHtml(pool.name)}"
                                    ${hasSession ? `<small class="text-muted">(Сессия: ${escapeHtml(pool.active_session.session_name)})</small>` : '<small class="text-muted">(Нет активной сессии)</small>'}
                                </h5>
                                <button type="button" class="btn btn-sm btn-primary" onclick="openAddModal(${pool.id})">
                                    <i class="bi bi-plus-circle"></i> Добавить замер
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Дата и время</th>
                                            <th>Температура (°C)</th>
                                            <th>Кислород (O₂)</th>
                                            <th>Кто делал</th>
                                            ${isAdmin ? '<th class="text-center">Действия</th>' : ''}
                                        </tr>
                                    </thead>
                                    <tbody id="measurementsBody-${pool.id}">
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
            loadMeasurements(currentPoolId);
        }
    }

    function switchPool(poolId) {
        currentPoolId = poolId;
        loadMeasurements(poolId);
    }

    function loadMeasurements(poolId) {
        const tbody = $(`#measurementsBody-${poolId}`);
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
            url: apiUrl(`api/measurements.php?action=list&pool_id=${poolId}`),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderMeasurements(response.data || [], poolId);
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить замеры');
                    tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-muted">Не удалось загрузить данные</td></tr>`);
                }
            },
            error: function(xhr, status, error) {
                console.error('loadMeasurements error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке замеров');
                tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-danger">Ошибка при загрузке данных</td></tr>`);
            }
        });
    }

    function renderMeasurements(measurements, poolId) {
        const tbody = $(`#measurementsBody-${poolId}`);
        tbody.empty();
        const colSpan = isAdmin ? 5 : 4;

        if (!measurements.length) {
            tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-muted">Замеры не найдены</td></tr>`);
            return;
        }

        measurements.forEach(function(measurement) {
            const userInfo = measurement.created_by_full_name
                ? `${escapeHtml(measurement.created_by_full_name)} (${escapeHtml(measurement.created_by_login)})`
                : escapeHtml(measurement.created_by_login || 'Неизвестно');

            const canEdit = isAdmin || Boolean(measurement.can_edit);
        const canDelete = isAdmin;

        let actionsHtml = '';
        if (isAdmin) {
            actionsHtml = '<td class="text-center">';
            actionsHtml += `
                <button class="btn btn-sm btn-primary me-2" onclick="openEditModal(${measurement.id})" title="Редактировать">
                    <i class="bi bi-pencil"></i>
                </button>
            `;
            actionsHtml += `
                <button class="btn btn-sm btn-danger" onclick="deleteMeasurement(${measurement.id})" title="Удалить">
                    <i class="bi bi-trash"></i>
                </button>
            `;
            actionsHtml += '</td>';
        } else if (canEdit) {
            actionsHtml = '<td class="text-center">' +
                `<button class="btn btn-sm btn-primary" onclick="openEditModal(${measurement.id})" title="Редактировать">` +
                '<i class="bi bi-pencil"></i>' +
                '</button>' +
                '</td>';
        }

            const rowHtml = `
                <tr>
                    <td>${escapeHtml(measurement.measured_at_display || '')}</td>
                    <td>${formatMeasurementCell(measurement.temperature, measurement.temperature_stratum)}</td>
                    <td>${formatMeasurementCell(measurement.oxygen, measurement.oxygen_stratum)}</td>
                    <td>${userInfo}</td>
                    ${actionsHtml || ''}
                </tr>
            `;
            tbody.append(rowHtml);
        });
    }

    function formatMeasurementCell(value, stratum) {
        if (value === null || value === undefined || value === '') {
            return '<span class="text-muted">—</span>';
        }
        const className = getStratumClass(stratum);
        const formatted = formatMeasurementValue(value);
        return `<span class="${className}">${formatted}</span>`;
    }

    function formatMeasurementValue(value) {
        const number = parseFloat(value);
        if (Number.isNaN(number)) {
            return escapeHtml(String(value));
        }
        return number.toLocaleString('ru-RU', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1
        });
    }

    function getStratumClass(stratum) {
        if (!stratum) {
            return 'text-muted';
        }
        switch (stratum) {
            case 'good':
                return 'text-success fw-semibold';
            case 'acceptable':
                return 'text-warning fw-semibold';
            case 'bad':
            case 'critical':
            default:
                return 'text-danger fw-semibold';
        }
    }

    function openAddModal(poolId) {
        currentEditId = null;
        $('#measurementModalTitle').text('Добавить замер');
        $('#measurementForm')[0].reset();
        $('#measurementId').val('');
        $('#measurementPool').prop('disabled', false);

        const select = $('#measurementPool');
        select.empty().append('<option value="">Выберите бассейн</option>');
        poolsList.forEach(function(pool) {
            const selected = poolId && pool.id === poolId ? 'selected' : '';
            select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
        });

        if (isAdmin) {
            $('#datetimeField').show();
            $('#measurementDateTime').prop('required', true);
            $('#measurementDateTime').val(toDateTimeLocalValue(new Date()));
        } else {
            $('#datetimeField').hide();
            $('#measurementDateTime').prop('required', false);
        }

        if (poolId) {
            $('#currentPoolId').val(poolId);
            $('#measurementPool').val(poolId);
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('measurementModal'));
        modal.show();
    }

    function openEditModal(id) {
        currentEditId = id;
        $('#measurementModalTitle').text('Редактировать замер');

        $.ajax({
            url: apiUrl(`api/measurements.php?action=get&id=${id}`),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const measurement = response.data || {};
                    $('#measurementId').val(measurement.id || '');
                    $('#currentPoolId').val(measurement.pool_id || '');

                    const select = $('#measurementPool');
                    select.empty().append('<option value="">Выберите бассейн</option>');
                    poolsList.forEach(function(pool) {
                        const selected = pool.id === measurement.pool_id ? 'selected' : '';
                        select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
                    });

                    $('#measurementPool').val(measurement.pool_id || '');
                    $('#measurementTemperature').val(measurement.temperature != null ? measurement.temperature : '');
                    $('#measurementOxygen').val(measurement.oxygen != null ? measurement.oxygen : '');

                    if (isAdmin) {
                        $('#datetimeField').show();
                        $('#measurementDateTime').prop('required', true);
                        $('#measurementPool').prop('disabled', false);
                        const measuredAt = measurement.measured_at ? toDateTimeLocalValue(new Date(measurement.measured_at.replace(' ', 'T'))) : '';
                        $('#measurementDateTime').val(measuredAt);
                        $('#measurementDateTime').prop('disabled', false);
                    } else {
                        $('#datetimeField').hide();
                        $('#measurementDateTime').prop('required', false);
                        $('#measurementPool').prop('disabled', true);
                    }

                    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('measurementModal'));
                    modal.show();
                } else {
                    showAlert('danger', response.message || 'Не удалось получить замер');
                }
            },
            error: function(xhr, status, error) {
                console.error('openEditModal error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке замера');
            }
        });
    }

    function saveMeasurement() {
        const form = $('#measurementForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const poolIdValue = $('#currentPoolId').val() || $('#measurementPool').val();
        const payload = {
            pool_id: parseInt(poolIdValue, 10),
            temperature: parseFloat($('#measurementTemperature').val()),
            oxygen: parseFloat($('#measurementOxygen').val())
        };

        if (currentEditId) {
            payload.id = currentEditId;
        }

        if (isAdmin && $('#measurementDateTime').val()) {
            payload.measured_at = $('#measurementDateTime').val().replace('T', ' ');
        }

        const action = currentEditId ? 'update' : 'create';

        $.ajax({
            url: apiUrl(`api/measurements.php?action=${action}`),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Замер сохранён');
                    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('measurementModal'));
                    modal.hide();
                    if (payload.pool_id) {
                        loadMeasurements(payload.pool_id);
                    }
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить замер');
                }
            },
            error: function(xhr, status, error) {
                console.error('saveMeasurement error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при сохранении замера');
            }
        });
    }

    function deleteMeasurement(id) {
        if (!confirm('Удалить замер?')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/measurements.php?action=delete'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Замер удалён');
                    if (currentPoolId) {
                        loadMeasurements(currentPoolId);
                    } else {
                        loadPools();
                    }
                } else {
                    showAlert('danger', response.message || 'Не удалось удалить замер');
                }
            },
            error: function(xhr, status, error) {
                console.error('deleteMeasurement error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при удалении замера');
            }
        });
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
    window.saveMeasurement = saveMeasurement;
    window.deleteMeasurement = deleteMeasurement;
})();
