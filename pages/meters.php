<?php
/**
 * Управление приборами учета (только для администраторов)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

$page_title = 'Приборы учета';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="mb-0">Приборы учета</h1>
                    <?php renderSectionDescription('meters'); ?>
                </div>
                <button class="btn btn-primary" onclick="openMeterModal()">
                    <i class="bi bi-plus-circle"></i> Добавить прибор
                </button>
            </div>
            <div id="alert-container"></div>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Описание</th>
                                    <th>Создан</th>
                                    <th class="text-end">Действия</th>
                                </tr>
                            </thead>
                            <tbody id="metersTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm" role="status"></div>
                                        <div>Загрузка...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="meterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="meterModalTitle">Добавить прибор</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="meterForm">
                    <input type="hidden" id="meterId">
                    <div class="mb-3">
                        <label for="meterName" class="form-label">Название</label>
                        <input type="text" class="form-control" id="meterName" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="meterDescription" class="form-label">Описание</label>
                        <textarea class="form-control" id="meterDescription" rows="4"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveMeter()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
let meterModal;
let isEditingMeter = false;

document.addEventListener('DOMContentLoaded', function() {
    meterModal = new bootstrap.Modal(document.getElementById('meterModal'));
    loadMetersAdmin();
});

function loadMetersAdmin() {
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
        url: '<?php echo BASE_URL; ?>api/meters.php?action=list_admin',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderMetersTable(response.data || []);
            } else {
                showAlert('danger', response.message || 'Не удалось загрузить приборы');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке приборов');
        }
    });
}

function renderMetersTable(meters) {
    const tbody = $('#metersTableBody');
    tbody.empty();

    if (!meters.length) {
        tbody.html('<tr><td colspan="4" class="text-center text-muted py-4">Приборы учета не добавлены</td></tr>');
        return;
    }

    meters.forEach(function(meter) {
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
    isEditingMeter = Boolean(id);
    $('#meterModalTitle').text(isEditingMeter ? 'Редактировать прибор' : 'Добавить прибор');

    if (id) {
        $.ajax({
            url: '<?php echo BASE_URL; ?>api/meters.php?action=get&id=' + id,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    $('#meterName').val(response.data.name);
                    $('#meterDescription').val(response.data.description || '');
                    meterModal.show();
                } else {
                    showAlert('danger', response.message || 'Не удалось получить данные прибора');
                }
            },
            error: function() {
                showAlert('danger', 'Ошибка при загрузке прибора');
            }
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
        description: description
    };
    if (id) {
        payload.id = parseInt(id, 10);
    }

    const action = id ? 'update' : 'create';

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/meters.php?action=' + action,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message || 'Изменения сохранены');
                meterModal.hide();
                loadMetersAdmin();
            } else {
                showAlert('danger', response.message || 'Не удалось сохранить данные');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при сохранении данных');
        }
    });
}

function confirmDeleteMeter(id) {
    if (!confirm('Удалить прибор учета? Все показания также будут удалены.')) {
        return;
    }

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/meters.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id: id }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message || 'Прибор удален');
                loadMetersAdmin();
            } else {
                showAlert('danger', response.message || 'Не удалось удалить прибор');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при удалении прибора');
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

