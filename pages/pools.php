<?php
/**
 * Страница управления бассейнами
 * Доступна только администраторам
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';

$page_title = 'Управление бассейнами';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Управление бассейнами</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#poolModal" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Добавить бассейн
            </button>
        </div>
    </div>
    
    <?php renderSectionDescription('pools'); ?>
    
    <div id="alert-container"></div>
    
    <div class="card">
        <div class="card-body">
            <p class="text-muted mb-3">
                <i class="bi bi-info-circle"></i> Перетащите бассейны для изменения порядка сортировки
            </p>
            <div id="poolsList" class="list-group">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления/редактирования бассейна -->
<div class="modal fade" id="poolModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="poolModalTitle">Добавить бассейн</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="poolForm">
                    <input type="hidden" id="poolId" name="id">
                    
                    <div class="mb-3">
                        <label for="poolName" class="form-label">Название бассейна <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="poolName" name="name" required maxlength="255">
                    </div>
                    
                    <div class="mb-3" id="isActiveContainer" style="display: none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="poolIsActive" name="is_active" value="1">
                            <label class="form-check-label" for="poolIsActive">
                                Активен
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="savePool()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<!-- Подключение SortableJS для drag-n-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
let currentEditId = null;
let sortable = null;

// Загрузка списка бассейнов
function loadPools() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/pools.php?action=list',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderPoolsList(response.data);
                initSortable();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке бассейнов');
        }
    });
}

// Отображение списка бассейнов
function renderPoolsList(pools) {
    const list = $('#poolsList');
    list.empty();
    
    if (pools.length === 0) {
        list.html('<div class="list-group-item text-center text-muted">Бассейны не найдены</div>');
        return;
    }
    
    pools.forEach(function(pool) {
        const statusBadge = pool.is_active 
            ? '<span class="badge bg-success">Активен</span>'
            : '<span class="badge bg-secondary">Неактивен</span>';
        
        const createdBy = pool.created_by_name || pool.created_by_login || 'Неизвестно';
        
        const item = `
            <div class="list-group-item pool-item" data-id="${pool.id}" style="cursor: move;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-grip-vertical me-3 text-muted" style="font-size: 1.2rem;"></i>
                        <div>
                            <h6 class="mb-1">${escapeHtml(pool.name)}</h6>
                            <small class="text-muted">
                                Создан: ${pool.created_at} | Автор: ${escapeHtml(createdBy)}
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
            </div>
        `;
        list.append(item);
    });
}

// Инициализация drag-n-drop
function initSortable() {
    if (sortable) {
        sortable.destroy();
    }
    
    const list = document.getElementById('poolsList');
    if (!list) return;
    
    sortable = new Sortable(list, {
        animation: 150,
        handle: '.bi-grip-vertical',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        onEnd: function(evt) {
            updatePoolsOrder();
        }
    });
}

// Обновление порядка сортировки
function updatePoolsOrder() {
    const items = $('#poolsList .pool-item');
    const order = [];
    
    items.each(function() {
        order.push($(this).data('id'));
    });
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/pools.php?action=update_order',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({order: order}),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Можно показать уведомление, но не обязательно
                // showAlert('success', response.message);
            } else {
                showAlert('danger', response.message);
                loadPools(); // Перезагрузить при ошибке
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при обновлении порядка');
            loadPools(); // Перезагрузить при ошибке
        }
    });
}

// Открыть модальное окно для добавления
function openAddModal() {
    currentEditId = null;
    $('#poolModalTitle').text('Добавить бассейн');
    $('#poolForm')[0].reset();
    $('#poolId').val('');
    $('#isActiveContainer').hide();
}

// Открыть модальное окно для редактирования
function openEditModal(id) {
    currentEditId = id;
    $('#poolModalTitle').text('Редактировать бассейн');
    $('#isActiveContainer').show();
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/pools.php?action=get&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const pool = response.data;
                $('#poolId').val(pool.id);
                $('#poolName').val(pool.name);
                $('#poolIsActive').prop('checked', pool.is_active == 1);
                
                const modal = new bootstrap.Modal(document.getElementById('poolModal'));
                modal.show();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке данных бассейна');
        }
    });
}

// Сохранить бассейн
function savePool() {
    const form = $('#poolForm')[0];
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = {
        name: $('#poolName').val().trim()
    };
    
    if (currentEditId) {
        formData.id = currentEditId;
        formData.is_active = $('#poolIsActive').is(':checked') ? 1 : 0;
    }
    
    const action = currentEditId ? 'update' : 'create';
    const url = '<?php echo BASE_URL; ?>api/pools.php?action=' + action;
    
    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#poolModal').modal('hide');
                loadPools();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении бассейна');
        }
    });
}

// Удалить бассейн
function deletePool(id) {
    if (!confirm('Вы уверены, что хотите удалить этот бассейн?')) {
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/pools.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({id: id}),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadPools();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении бассейна');
        }
    });
}

// Показать уведомление
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('#alert-container').html(alertHtml);
    
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
}

// Экранирование HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Загрузка при открытии страницы
$(document).ready(function() {
    loadPools();
});
</script>

<style>
.pool-item {
    transition: background-color 0.2s;
}

.pool-item:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .pool-item:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

.sortable-ghost {
    opacity: 0.4;
    background-color: #f0f0f0;
}

[data-theme="dark"] .sortable-ghost {
    background-color: #404040;
}

.sortable-chosen {
    cursor: grabbing !important;
}

.sortable-drag {
    opacity: 0.8;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
