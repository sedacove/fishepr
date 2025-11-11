(function () {
    'use strict';

    if (window.__poolsPageInitialized) {
        return;
    }
    window.__poolsPageInitialized = true;

    const config = window.poolsConfig || {};
    const baseUrl = config.baseUrl || '';
    const apiBase = new URL('.', baseUrl || window.location.href).toString();

    let currentEditId = null;
    let sortableInstance = null;
    let poolModal = null;

    function apiUrl(path) {
        return new URL(path, apiBase).toString();
    }

    document.addEventListener('DOMContentLoaded', function () {
        poolModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('poolModal'));
        loadPools();
    });

    function loadPools() {
        $.ajax({
            url: apiUrl('api/pools.php?action=list'),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderPoolsList(response.data || []);
                    initSortable();
                } else {
                    showAlert('danger', response.message || 'Ошибка при загрузке бассейнов');
                }
            },
            error: function (xhr, status, error) {
                console.error('loadPools error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке бассейнов');
            },
        });
    }

    function renderPoolsList(pools) {
        const list = $('#poolsList');
        list.empty();

        if (!pools.length) {
            list.html('<div class="list-group-item text-center text-muted">Бассейны не найдены</div>');
            return;
        }

        pools.forEach(function (pool) {
            const statusBadge = pool.is_active
                ? '<span class="badge bg-success">Активен</span>'
                : '<span class="badge bg-secondary">Неактивен</span>';

            const createdBy = pool.created_by_name || pool.created_by_login || 'Неизвестно';

            const item = `
                <div class="list-group-item pool-item d-flex justify-content-between align-items-center" data-id="${pool.id}">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-grip-vertical me-3 text-muted" style="font-size: 1.2rem;"></i>
                        <div>
                            <h6 class="mb-1">${escapeHtml(pool.name)}</h6>
                            <small class="text-muted">
                                Создан: ${escapeHtml(pool.created_at || '-')} | Автор: ${escapeHtml(createdBy)}
                            </small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        ${statusBadge}
                        <button class="btn btn-sm btn-primary" onclick="openEditModal(${pool.id})" title="Редактировать">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deletePool(${pool.id})" title="Удалить">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            list.append(item);
        });
    }

    function initSortable() {
        if (sortableInstance) {
            sortableInstance.destroy();
        }

        const list = document.getElementById('poolsList');
        if (!list) {
            return;
        }

        sortableInstance = new Sortable(list, {
            animation: 150,
            handle: '.bi-grip-vertical',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: function () {
                updatePoolsOrder();
            },
        });
    }

    function updatePoolsOrder() {
        const items = $('#poolsList .pool-item');
        const order = [];
        items.each(function () {
            order.push($(this).data('id'));
        });

        $.ajax({
            url: apiUrl('api/pools.php?action=update_order'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ order }),
            dataType: 'json',
            success: function (response) {
                if (!response.success) {
                    showAlert('danger', response.message || 'Ошибка при обновлении порядка');
                    loadPools();
                }
            },
            error: function (xhr, status, error) {
                console.error('updatePoolsOrder error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при обновлении порядка');
                loadPools();
            },
        });
    }

    function openAddModal() {
        currentEditId = null;
        $('#poolModalTitle').text('Добавить бассейн');
        $('#poolForm')[0].reset();
        $('#poolId').val('');
        $('#isActiveContainer').hide();
        poolModal.show();
    }

    function openEditModal(id) {
        currentEditId = id;
        $('#poolModalTitle').text('Редактировать бассейн');
        $('#isActiveContainer').show();

        $.ajax({
            url: apiUrl(`api/pools.php?action=get&id=${id}`),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    const pool = response.data;
                    $('#poolId').val(pool.id);
                    $('#poolName').val(pool.name);
                    $('#poolIsActive').prop('checked', !!pool.is_active);
                    poolModal.show();
                } else {
                    showAlert('danger', response.message || 'Не удалось получить данные бассейна');
                }
            },
            error: function (xhr, status, error) {
                console.error('openEditModal error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке данных бассейна');
            },
        });
    }

    function savePool() {
        const form = $('#poolForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const payload = {
            name: $('#poolName').val().trim(),
        };

        if (currentEditId) {
            payload.id = currentEditId;
            payload.is_active = $('#poolIsActive').is(':checked') ? 1 : 0;
        }

        const action = currentEditId ? 'update' : 'create';

        $.ajax({
            url: apiUrl(`api/pools.php?action=${action}`),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Изменения сохранены');
                    poolModal.hide();
                    loadPools();
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить бассейн');
                }
            },
            error: function (xhr, status, error) {
                console.error('savePool error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при сохранении бассейна');
            },
        });
    }

    function deletePool(id) {
        if (!confirm('Вы уверены, что хотите удалить этот бассейн?')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/pools.php?action=delete'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id }),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Бассейн удалён');
                    loadPools();
                } else {
                    showAlert('danger', response.message || 'Не удалось удалить бассейн');
                }
            },
            error: function (xhr, status, error) {
                console.error('deletePool error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при удалении бассейна');
            },
        });
    }

    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('#alert-container').html(alertHtml);
        setTimeout(function () {
            $('.alert').alert('close');
        }, 5000);
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

    window.openAddModal = openAddModal;
    window.openEditModal = openEditModal;
    window.savePool = savePool;
    window.deletePool = deletePool;
})();


