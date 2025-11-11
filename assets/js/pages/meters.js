(function () {
    'use strict';

    if (window.__metersPageInitialized) {
        return;
    }
    window.__metersPageInitialized = true;

    const config = window.metersConfig || {};
    const baseUrl = config.baseUrl || '';
    const apiBase = new URL('.', baseUrl || window.location.href).toString();

    let meterModal = null;
    let isEditing = false;

    function apiUrl(path) {
        return new URL(path, apiBase).toString();
    }

    document.addEventListener('DOMContentLoaded', function () {
        meterModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('meterModal'));
        loadMeters();
    });

    function loadMeters() {
        const tbody = $('#metersTableBody');
        tbody.html(`
            <tr>
                <td colspan="4" class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    <div>Загрузка...</div>
                </td>
            </tr>
        `);

        $.ajax({
            url: apiUrl('api/meters.php?action=list_admin'),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderMetersTable(response.data || []);
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить приборы');
                }
            },
            error: function (xhr, status, error) {
                console.error('loadMeters error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке приборов');
            },
        });
    }

    function renderMetersTable(meters) {
        const tbody = $('#metersTableBody');
        tbody.empty();

        if (!meters.length) {
            tbody.html('<tr><td colspan="4" class="text-center text-muted py-4">Приборы учета не добавлены</td></tr>');
            return;
        }

        meters.forEach(function (meter) {
            const createdAt = meter.created_at ? new Date(meter.created_at).toLocaleString('ru-RU') : '';
            const createdBy = meter.created_by_name
                ? `${escapeHtml(meter.created_by_name)} (${escapeHtml(meter.created_by_login || '')})`
                : escapeHtml(meter.created_by_login || '');

            const rowHtml = `
                <tr>
                    <td class="fw-semibold">${escapeHtml(meter.name)}</td>
                    <td>${meter.description ? escapeHtml(meter.description) : '<span class="text-muted">—</span>'}</td>
                    <td>
                        ${createdAt ? `<div>${createdAt}</div>` : ''}
                        ${createdBy ? `<small class="text-muted">${createdBy}</small>` : ''}
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-primary me-2" onclick="openMeterModal(${meter.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteMeter(${meter.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(rowHtml);
        });
    }

    function openMeterModal(id = null) {
        $('#meterForm')[0].reset();
        $('#meterId').val(id || '');
        isEditing = Boolean(id);
        $('#meterModalTitle').text(isEditing ? 'Редактировать прибор' : 'Добавить прибор');

        if (id) {
            $.ajax({
                url: apiUrl(`api/meters.php?action=get&id=${id}`),
                method: 'GET',
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.data) {
                        $('#meterName').val(response.data.name);
                        $('#meterDescription').val(response.data.description || '');
                        meterModal.show();
                    } else {
                        showAlert('danger', response.message || 'Не удалось получить данные прибора');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('openMeterModal error:', status, error, xhr.responseText);
                    showAlert('danger', 'Ошибка при загрузке прибора');
                },
            });
        } else {
            meterModal.show();
        }
    }

    function saveMeter() {
        const id = $('#meterId').val();
        const name = $('#meterName').val().trim();
        const description = $('#meterDescription').val().trim();

        if (!name) {
            showAlert('warning', 'Введите название прибора');
            return;
        }

        const payload = {
            name: name,
            description: description,
        };
        if (id) {
            payload.id = parseInt(id, 10);
        }

        const action = id ? 'update' : 'create';

        $.ajax({
            url: apiUrl(`api/meters.php?action=${action}`),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Изменения сохранены');
                    meterModal.hide();
                    loadMeters();
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить данные');
                }
            },
            error: function (xhr, status, error) {
                console.error('saveMeter error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при сохранении данных');
            },
        });
    }

    function confirmDeleteMeter(id) {
        if (!confirm('Удалить прибор учета? Все показания также будут удалены.')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/meters.php?action=delete'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: id }),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Прибор удален');
                    loadMeters();
                } else {
                    showAlert('danger', response.message || 'Не удалось удалить прибор');
                }
            },
            error: function (xhr, status, error) {
                console.error('confirmDeleteMeter error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при удалении прибора');
            },
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

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.openMeterModal = openMeterModal;
    window.saveMeter = saveMeter;
    window.confirmDeleteMeter = confirmDeleteMeter;
})();


