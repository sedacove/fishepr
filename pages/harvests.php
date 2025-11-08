<?php
/**
 * Страница отборов
 * Доступна всем авторизованным пользователям
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
// Устанавливаем заголовок страницы до вывода контента
$page_title = 'Отборы';

// Требуем авторизацию до вывода заголовков
requireAuth();

$isAdmin = isAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Отборы</h1>
            <?php renderSectionDescription('harvests'); ?>
        </div>
    </div>
    
    <div id="alert-container"></div>
    
    <!-- Табы по бассейнам -->
    <ul class="nav nav-tabs mb-3" id="poolsTabs" role="tablist">
        <!-- Табы будут загружены динамически -->
    </ul>
    
    <!-- Содержимое табов -->
    <div class="tab-content" id="poolsTabContent">
        <!-- Содержимое будет загружено динамически -->
    </div>
</div>

<?php
// Подключаем шаблон модального окна для отборов
$modalId = 'recordModal';
$formId = 'recordForm';
$poolSelectId = 'recordPool';
$datetimeFieldId = 'datetimeField';
$datetimeInputId = 'recordDateTime';
$weightId = 'recordWeight';
$fishCountId = 'recordFishCount';
$currentPoolId = 'currentPoolId';
$modalTitleId = 'recordModalTitle';
$saveFunction = 'saveRecord';
$counterpartySelectId = 'recordCounterparty';
require_once __DIR__ . '/../templates/harvest_modal.php';
?>

<script>
let currentEditId = null;
let currentPoolId = null;
let poolsList = [];
let counterpartiesList = [];
let isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

// Загрузка бассейнов и создание табов
function loadPools() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/harvests.php?action=get_pools',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                poolsList = response.data;
                createTabs(response.data);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке бассейнов');
        }
    });
}

function loadCounterpartiesList() {
    if (!isAdmin) {
        counterpartiesList = [];
        return;
    }
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/counterparties.php?action=list',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                counterpartiesList = response.data;
            } else if (response.message) {
                showAlert('danger', response.message);
            }
        },
        error: function() {
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

// Создание табов
function createTabs(pools) {
    const tabsNav = $('#poolsTabs');
    const tabsContent = $('#poolsTabContent');
    
    tabsNav.empty();
    tabsContent.empty();
    
    if (pools.length === 0) {
        tabsContent.html('<div class="alert alert-info">Нет активных бассейнов</div>');
        return;
    }
    
    // Находим первый бассейн с активной сессией для активации
    let firstActiveIndex = -1;
    pools.forEach(function(pool, index) {
        if (pool.active_session && firstActiveIndex === -1) {
            firstActiveIndex = index;
        }
    });
    
    pools.forEach(function(pool, index) {
        const tabId = 'pool-' + pool.id;
        const hasSession = pool.active_session !== null;
        const isActive = (firstActiveIndex !== -1 && index === firstActiveIndex) ? 'active' : '';
        const isDisabled = !hasSession ? 'disabled' : '';
        
        // Определяем текст таба
        let tabText = '';
        if (hasSession) {
            tabText = escapeHtml(pool.active_session.session_name);
        } else {
            tabText = '<i class="bi bi-x-circle text-muted"></i>';
        }
        
        // Таб
        const tabHtml = `
            <li class="nav-item" role="presentation">
                <button class="nav-link ${isActive} ${isDisabled}" 
                        id="${tabId}-tab" 
                        data-bs-toggle="${hasSession ? 'tab' : ''}" 
                        data-bs-target="#${tabId}" 
                        type="button" 
                        role="tab"
                        ${hasSession ? `onclick="switchPool(${pool.id})"` : ''}
                        ${!hasSession ? 'disabled' : ''}
                        title="${hasSession ? escapeHtml(pool.active_session.session_name) : 'Нет активной сессии'}">
                    ${tabText}
                </button>
            </li>
        `;
        tabsNav.append(tabHtml);
        
        // Содержимое таба
        const contentHtml = `
            <div class="tab-pane fade ${isActive ? 'show active' : ''}" id="${tabId}" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                Отборы для бассейна "${escapeHtml(pool.name)}"
                                ${hasSession ? `<small class="text-muted">(Сессия: ${escapeHtml(pool.active_session.session_name)})</small>` : '<small class="text-muted">(Нет активной сессии)</small>'}
                            </h5>
                            <button type="button" class="btn btn-sm btn-primary" onclick="openAddModal(${pool.id})">
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
                                        <?php if ($isAdmin): ?>
                                        <th>Действия</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody id="recordsBody-${pool.id}">
                                    <tr>
                                        <td colspan="<?php echo $isAdmin ? '6' : '5'; ?>" class="text-center">
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
    
    // Загружаем записи для первого активного бассейна
    if (firstActiveIndex !== -1) {
        currentPoolId = pools[firstActiveIndex].id;
        loadRecords(pools[firstActiveIndex].id);
    }
}

// Переключение бассейна
function switchPool(poolId) {
    currentPoolId = poolId;
    loadRecords(poolId);
}

// Загрузка записей для бассейна
function loadRecords(poolId) {
    const tbody = $('#recordsBody-' + poolId);
    
    tbody.html('<tr><td colspan="<?php echo $isAdmin ? '6' : '5'; ?>" class="text-center"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Загрузка...</span></div></td></tr>');
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/harvests.php?action=list&pool_id=' + poolId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderRecords(response.data, poolId);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке записей');
        }
    });
}

// Отображение записей
function renderRecords(records, poolId) {
    const tbody = $('#recordsBody-' + poolId);
    tbody.empty();
    
    if (records.length === 0) {
        tbody.html(`<tr><td colspan="<?php echo $isAdmin ? '6' : '5'; ?>" class="text-center text-muted">Записи не найдены</td></tr>`);
        return;
    }
    
    records.forEach(function(record) {
        const badgeColor = record.counterparty_color || '#6c757d';
        const counterpartyBadge = record.counterparty_name 
            ? `<span class="badge ${getContrastTextClass(badgeColor)}" style="background-color: ${badgeColor};">${escapeHtml(record.counterparty_name)}</span>`
            : '<span class="badge bg-light text-muted">—</span>';
        const userInfo = record.created_by_full_name 
            ? `${escapeHtml(record.created_by_full_name)} (${escapeHtml(record.created_by_login)})`
            : escapeHtml(record.created_by_login || 'Неизвестно');
        
        // Проверяем, может ли текущий пользователь редактировать эту запись
        const canEdit = isAdmin || (record.can_edit === true);
        const canDelete = isAdmin;
        
        let actionsHtml = '';
        if (canEdit || canDelete) {
            actionsHtml = '<td>';
            
            if (canEdit) {
                actionsHtml += `
                    <button class="btn btn-sm btn-primary" onclick="openEditModal(${record.id})" title="Редактировать">
                        <i class="bi bi-pencil"></i>
                    </button>
                `;
            }
            
            if (canDelete) {
                actionsHtml += `
                    <button class="btn btn-sm btn-danger" onclick="deleteRecord(${record.id})" title="Удалить">
                        <i class="bi bi-trash"></i>
                    </button>
                `;
            }
            
            actionsHtml += '</td>';
        }
        
        const row = `
            <tr>
                <td>${record.recorded_at_display}</td>
                <td>${record.weight}</td>
                <td>${record.fish_count}</td>
                <td>${counterpartyBadge}</td>
                <td>${userInfo}</td>
                ${actionsHtml}
            </tr>
        `;
        tbody.append(row);
    });
}

// Открыть модальное окно для добавления
function openAddModal(poolId = null) {
    currentEditId = null;
    $('#recordModalTitle').text('Добавить отбор');
    $('#recordForm')[0].reset();
    $('#recordId').val('');
    $('#recordPool').prop('disabled', false);
    
    // Заполняем список бассейнов
    const select = $('#recordPool');
    select.empty().append('<option value="">Выберите бассейн</option>');
    poolsList.forEach(function(pool) {
        const selected = (poolId && pool.id == poolId) ? 'selected' : '';
        select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
    });
    
    populateCounterpartySelect('#recordCounterparty', null);
    
    // Показываем поле даты/времени только для администратора
    if (isAdmin) {
        $('#datetimeField').show();
        $('#recordDateTime').prop('required', true);
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        $('#recordDateTime').val(`${year}-${month}-${day}T${hours}:${minutes}`);
    } else {
        $('#datetimeField').hide();
        $('#recordDateTime').prop('required', false);
    }
    
    // Устанавливаем текущий бассейн если указан
    if (poolId) {
        $('#currentPoolId').val(poolId);
        $('#recordPool').val(poolId);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('recordModal'));
    modal.show();
}

// Открыть модальное окно для редактирования
function openEditModal(id) {
    currentEditId = id;
    $('#recordModalTitle').text('Редактировать отбор');
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/harvests.php?action=get&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const record = response.data;
                $('#recordId').val(record.id);
                $('#currentPoolId').val(record.pool_id);
                
                // Заполняем список бассейнов
                const select = $('#recordPool');
                select.empty().append('<option value="">Выберите бассейн</option>');
                poolsList.forEach(function(pool) {
                    const selected = pool.id == record.pool_id ? 'selected' : '';
                    select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
                });
                
                // Заполняем поля
                $('#recordPool').val(record.pool_id);
                $('#recordWeight').val(record.weight);
                $('#recordFishCount').val(record.fish_count);
                populateCounterpartySelect('#recordCounterparty', record.counterparty_id);
                
                // Для администратора можно редактировать дату/время и бассейн, для пользователя - нет
                if (isAdmin) {
                    $('#datetimeField').show();
                    $('#recordDateTime').prop('required', true);
                    $('#recordPool').prop('disabled', false);
                    // Преобразуем дату/время для datetime-local
                    const recordedAt = record.recorded_at.replace(' ', 'T');
                    $('#recordDateTime').val(recordedAt);
                    $('#recordDateTime').prop('disabled', false);
                } else {
                    $('#datetimeField').hide();
                    $('#recordDateTime').prop('required', false);
                    // Для пользователя бассейн недоступен для редактирования
                    $('#recordPool').prop('disabled', true);
                }
                
                const modal = new bootstrap.Modal(document.getElementById('recordModal'));
                modal.show();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке данных отбора');
        }
    });
}

// Сохранить запись
function saveRecord() {
    const form = $('#recordForm')[0];
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = {
        pool_id: parseInt($('#recordPool').val()),
        weight: parseFloat($('#recordWeight').val()),
        fish_count: parseInt($('#recordFishCount').val())
    };
    const counterpartyValue = $('#recordCounterparty').val();
    if (counterpartyValue) {
        formData.counterparty_id = parseInt(counterpartyValue);
    } else {
        formData.counterparty_id = null;
    }
    
    // Для администратора можно изменить дату/время, для пользователя - нет
    if (isAdmin && $('#recordDateTime').is(':visible')) {
        formData.recorded_at = $('#recordDateTime').val();
    }
    
    if (currentEditId) {
        formData.id = currentEditId;
    }
    
    const action = currentEditId ? 'update' : 'create';
    const url = '<?php echo BASE_URL; ?>api/harvests.php?action=' + action;
    
    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#recordModal').modal('hide');
                
                // Перезагружаем записи для текущего бассейна
                const poolId = formData.pool_id || currentPoolId;
                if (poolId) {
                    loadRecords(poolId);
                }
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении отбора');
        }
    });
}

// Удалить запись
function deleteRecord(id) {
    if (!isAdmin) return;
    
    if (!confirm('Вы уверены, что хотите удалить этот отбор?')) {
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/harvests.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({id: id}),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadRecords(currentPoolId);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении отбора');
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

function getContrastTextClass(color) {
    if (!color) {
        return 'text-white';
    }
    let normalized = color.trim();
    if (normalized.startsWith('#')) {
        normalized = normalized.substring(1);
    }
    if (normalized.length === 3) {
        normalized = normalized.split('').map(char => char + char).join('');
    }
    const bigint = parseInt(normalized, 16);
    if (isNaN(bigint)) {
        return 'text-white';
    }
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance > 0.6 ? 'text-dark' : 'text-white';
}

// Экранирование HTML
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

// Загрузка при открытии страницы
$(document).ready(function() {
    if (isAdmin) {
        loadCounterpartiesList();
    }
    loadPools();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>