<?php
/**
 * Страница замеров
 * Доступна всем авторизованным пользователям
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
// Устанавливаем заголовок страницы до вывода контента
$page_title = 'Замеры';

// Требуем авторизацию до вывода заголовков
requireAuth();

$isAdmin = isAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Замеры</h1>
            <?php renderSectionDescription('measurements'); ?>
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
// Подключаем шаблон модального окна для замеров
$modalId = 'measurementModal';
$formId = 'measurementForm';
$poolSelectId = 'measurementPool';
$datetimeFieldId = 'datetimeField';
$datetimeInputId = 'measurementDateTime';
$temperatureId = 'measurementTemperature';
$oxygenId = 'measurementOxygen';
$currentPoolId = 'currentPoolId';
$modalTitleId = 'measurementModalTitle';
$saveFunction = 'saveMeasurement';
require_once __DIR__ . '/../templates/measurement_modal.php';
?>

<script>
let currentEditId = null;
let currentPoolId = null;
let poolsList = [];
let isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

// Загрузка бассейнов и создание табов
function loadPools() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/measurements.php?action=get_pools',
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
            tabText = `${escapeHtml(pool.name)}: ${escapeHtml(pool.active_session.session_name)}`;
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
                        title="${hasSession ? `${escapeHtml(pool.name)}: ${escapeHtml(pool.active_session.session_name)}` : 'Нет активной сессии'}">
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
                                        <th>Кислород (O2)</th>
                                        <th>Кто делал</th>
                                        <?php if ($isAdmin): ?>
                                        <th>Действия</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody id="measurementsBody-${pool.id}">
                                    <tr>
                                        <td colspan="<?php echo $isAdmin ? '5' : '4'; ?>" class="text-center">
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
    
    // Загружаем замеры для первого активного бассейна
    if (firstActiveIndex !== -1) {
        currentPoolId = pools[firstActiveIndex].id;
        loadMeasurements(pools[firstActiveIndex].id);
    }
}

// Переключение бассейна
function switchPool(poolId) {
    currentPoolId = poolId;
    loadMeasurements(poolId);
}

// Загрузка замеров для бассейна
function loadMeasurements(poolId) {
    const tbody = $('#measurementsBody-' + poolId);
    
    tbody.html('<tr><td colspan="<?php echo $isAdmin ? '5' : '4'; ?>" class="text-center"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Загрузка...</span></div></td></tr>');
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/measurements.php?action=list&pool_id=' + poolId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderMeasurements(response.data, poolId);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке замеров');
        }
    });
}

// Отображение замеров
function renderMeasurements(measurements, poolId) {
    const tbody = $('#measurementsBody-' + poolId);
    tbody.empty();
    
    if (measurements.length === 0) {
        tbody.html(`<tr><td colspan="<?php echo $isAdmin ? '5' : '4'; ?>" class="text-center text-muted">Замеры не найдены</td></tr>`);
        return;
    }
    
        measurements.forEach(function(measurement) {
            const userInfo = measurement.created_by_full_name 
                ? `${escapeHtml(measurement.created_by_full_name)} (${escapeHtml(measurement.created_by_login)})`
                : escapeHtml(measurement.created_by_login || 'Неизвестно');
            
            // Проверяем, может ли текущий пользователь редактировать этот замер
            const canEdit = isAdmin || (measurement.can_edit === true);
            const canDelete = isAdmin;
            
            let actionsHtml = '';
            if (canEdit || canDelete) {
                actionsHtml = '<td>';
                
                if (canEdit) {
                    actionsHtml += `
                        <button class="btn btn-sm btn-primary" onclick="openEditModal(${measurement.id})" title="Редактировать">
                            <i class="bi bi-pencil"></i>
                        </button>
                    `;
                }
                
                if (canDelete) {
                    actionsHtml += `
                        <button class="btn btn-sm btn-danger" onclick="deleteMeasurement(${measurement.id})" title="Удалить">
                            <i class="bi bi-trash"></i>
                        </button>
                    `;
                }
                
                actionsHtml += '</td>';
            }
        
        const row = `
            <tr>
                <td>${measurement.measured_at_display}</td>
                <td>${measurement.temperature}</td>
                <td>${measurement.oxygen}</td>
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
    $('#measurementModalTitle').text('Добавить замер');
    $('#measurementForm')[0].reset();
    $('#measurementId').val('');
    $('#measurementPool').prop('disabled', false);
    
    // Заполняем список бассейнов
    const select = $('#measurementPool');
    select.empty().append('<option value="">Выберите бассейн</option>');
    poolsList.forEach(function(pool) {
        const selected = (poolId && pool.id == poolId) ? 'selected' : '';
        select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
    });
    
    // Показываем поле даты/времени только для администратора
    if (isAdmin) {
        $('#datetimeField').show();
        $('#measurementDateTime').prop('required', true);
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        $('#measurementDateTime').val(`${year}-${month}-${day}T${hours}:${minutes}`);
    } else {
        $('#datetimeField').hide();
        $('#measurementDateTime').prop('required', false);
    }
    
    // Устанавливаем текущий бассейн если указан
    if (poolId) {
        $('#currentPoolId').val(poolId);
        $('#measurementPool').val(poolId);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('measurementModal'));
    modal.show();
}

// Открыть модальное окно для редактирования
function openEditModal(id) {
    currentEditId = id;
    $('#measurementModalTitle').text('Редактировать замер');
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/measurements.php?action=get&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const measurement = response.data;
                $('#measurementId').val(measurement.id);
                $('#currentPoolId').val(measurement.pool_id);
                
                // Заполняем список бассейнов
                const select = $('#measurementPool');
                select.empty().append('<option value="">Выберите бассейн</option>');
                poolsList.forEach(function(pool) {
                    const selected = pool.id == measurement.pool_id ? 'selected' : '';
                    select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
                });
                
                // Заполняем поля
                $('#measurementPool').val(measurement.pool_id);
                $('#measurementTemperature').val(measurement.temperature);
                $('#measurementOxygen').val(measurement.oxygen);
                
                // Для администратора можно редактировать дату/время и бассейн, для пользователя - нет
                if (isAdmin) {
                    $('#datetimeField').show();
                    $('#measurementDateTime').prop('required', true);
                    $('#measurementPool').prop('disabled', false);
                    // Преобразуем дату/время для datetime-local
                    const measuredAt = measurement.measured_at.replace(' ', 'T');
                    $('#measurementDateTime').val(measuredAt);
                    $('#measurementDateTime').prop('disabled', false);
                } else {
                    $('#datetimeField').hide();
                    $('#measurementDateTime').prop('required', false);
                    // Для пользователя бассейн недоступен для редактирования
                    $('#measurementPool').prop('disabled', true);
                }
                
                const modal = new bootstrap.Modal(document.getElementById('measurementModal'));
                modal.show();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке данных замера');
        }
    });
}

// Сохранить замер
function saveMeasurement() {
    const form = $('#measurementForm')[0];
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = {
        pool_id: parseInt($('#measurementPool').val()),
        temperature: parseFloat($('#measurementTemperature').val()),
        oxygen: parseFloat($('#measurementOxygen').val())
    };
    
    // Для администратора можно изменить дату/время, для пользователя - нет
    if (isAdmin && $('#measurementDateTime').is(':visible')) {
        formData.measured_at = $('#measurementDateTime').val();
    }
    
    if (currentEditId) {
        formData.id = currentEditId;
    }
    
    const action = currentEditId ? 'update' : 'create';
    const url = '<?php echo BASE_URL; ?>api/measurements.php?action=' + action;
    
    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#measurementModal').modal('hide');
                
                // Перезагружаем замеры для текущего бассейна
                const poolId = formData.pool_id || currentPoolId;
                if (poolId) {
                    loadMeasurements(poolId);
                }
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении замера');
        }
    });
}

// Удалить замер
function deleteMeasurement(id) {
    if (!isAdmin) return;
    
    if (!confirm('Вы уверены, что хотите удалить этот замер?')) {
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/measurements.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({id: id}),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadMeasurements(currentPoolId);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении замера');
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
    loadPools();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>