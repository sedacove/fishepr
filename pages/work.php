<?php
/**
 * Страница рабочая
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
// Требуем авторизацию до вывода заголовков
requireAuth();

$page_title = 'Рабочая';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/settings.php';

$isAdmin = isAdmin();
$maxPoolCapacityKg = (float)getSetting('max_pool_capacity_kg', 5000);
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/pool_blocks.css">

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1>Рабочая</h1>
        </div>
    </div>
    
    <div id="alert-container"></div>
    
    <div id="poolsGrid" class="pools-grid">
        <div class="text-center w-100">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    </div>
</div>

<script>
const maxPoolCapacityKg = <?php echo $maxPoolCapacityKg; ?>;

// Загрузка бассейнов
function loadPools() {
    const grid = $('#poolsGrid');
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/work.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderPools(response.data);
            } else {
                showAlert('danger', response.message);
                grid.html('<div class="alert alert-warning">Ошибка загрузки данных</div>');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке бассейнов');
            grid.html('<div class="alert alert-danger">Ошибка при загрузке данных</div>');
        }
    });
}

// Отображение бассейнов
function renderPools(pools) {
    const grid = $('#poolsGrid');
    grid.empty();
    
    if (pools.length === 0) {
        grid.html('<div class="alert alert-info w-100">Нет активных бассейнов</div>');
        return;
    }
    
    pools.forEach(function(pool) {
        const session = pool.active_session || null;
        const isEmpty = !session;
        
        // Проверяем, нужно ли показывать предупреждение о просроченном замере
        let measurementWarningHtml = '';
        if (session) {
            const diffMinutes = typeof session.last_measurement_diff_minutes === 'number'
                ? session.last_measurement_diff_minutes
                : (session.last_measurement_diff_minutes === null ? null : undefined);
            const diffLabel = session.last_measurement_diff_label || '';
            
            if (diffMinutes === null) {
                // Замеры ещё не проводились
                measurementWarningHtml = `<i class="bi bi-exclamation-triangle-fill text-danger me-2" title="${escapeHtml(diffLabel || 'Замер не проводился')}" style="font-size: 1.2rem;"></i>`;
            } else if (diffMinutes !== undefined && diffMinutes > measurementWarningTimeoutMinutes) {
                const label = diffLabel ? `${diffLabel} назад` : 'достаточно давно';
                measurementWarningHtml = `<i class="bi bi-exclamation-triangle-fill text-danger me-2" title="Замер не проводился ${escapeHtml(label)}" style="font-size: 1.2rem;"></i>`;
            }
        }
        
        let avgWeightHtml = '';
        if (session && session.avg_fish_weight !== undefined && session.avg_fish_weight !== null) {
            const avgWeight = parseFloat(session.avg_fish_weight).toFixed(3).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            avgWeightHtml = ` <span class="badge bg-primary pool-block-avg-weight-badge">${avgWeight} кг</span>`;
        }
        let sessionInfo = '';
        if (session && session.start_mass !== undefined && session.start_fish_count !== undefined) {
            const mass = parseFloat(session.start_mass).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            const count = parseInt(session.start_fish_count).toLocaleString('ru-RU');
            
            let durationInfo = '';
            if (session.start_date) {
                const startDate = new Date(session.start_date);
                const now = new Date();
                const daysDiff = Math.floor((now - startDate) / (1000 * 60 * 60 * 24));
                // Правильное склонение слова "день"
                let daysText = 'дней';
                const lastDigit = daysDiff % 10;
                const lastTwoDigits = daysDiff % 100;
                if (lastTwoDigits >= 11 && lastTwoDigits <= 14) {
                    daysText = 'дней';
                } else if (lastDigit === 1) {
                    daysText = 'день';
                } else if (lastDigit >= 2 && lastDigit <= 4) {
                    daysText = 'дня';
                }
                durationInfo = `<div class="pool-block-session-duration">Сессия длится ${daysDiff} ${daysText}</div>`;
            }
            
            sessionInfo = `
                <div class="pool-block-session-info">
                    <div>${mass} кг</div>
                    <div>${count} шт</div>
                    ${durationInfo}
                </div>
            `;
        }
        const emptyClass = isEmpty ? 'pool-block-empty' : '';
        const actionsHtml = session ? `
                <div class="pool-block-divider"></div>
                <div class="pool-block-actions">
                    <button type="button" class="pool-action-btn" onclick="openMeasurementModal(${pool.id})" title="Выполнить замер">
                        <i class="bi bi-thermometer-half"></i>
                    </button>
                    <button type="button" class="pool-action-btn" onclick="openMortalityModal(${pool.id})" title="Зарегистрировать падеж">
                        <i class="bi bi-exclamation-triangle"></i>
                    </button>
                    <button type="button" class="pool-action-btn" onclick="openHarvestModal(${pool.id})" title="Добавить отбор">
                        <i class="bi bi-box-arrow-up"></i>
                    </button>
                </div>
        ` : '';
        const fillInfoHtml = renderFillInfo(session);
        const measurementsHtml = renderMeasurements(session);
        const contentRowHtml = measurementsHtml
            ? `<div class="pool-content-row">${measurementsHtml}</div>`
            : '';
        const blockHtml = `
            <div class="pool-block ${emptyClass}" data-pool-id="${pool.id}">
                <div class="pool-block-header">
                    <div class="pool-block-title">
                        ${measurementWarningHtml}${session && session.id ? `<a href="<?php echo BASE_URL; ?>pages/session_details.php?id=${session.id}" class="text-decoration-none">${escapeHtml(session.name)}</a>` : escapeHtml('ПУСТО')}${avgWeightHtml}
                        ${sessionInfo}
                    </div>
                    <div class="pool-block-name">
                        ${escapeHtml(pool.name)}
                    </div>
                </div>
                <div class="pool-block-content">
                    ${fillInfoHtml}
                    ${contentRowHtml}
                </div>
                ${actionsHtml}
            </div>
        `;
        grid.append(blockHtml);
    });
}

function renderFillInfo(session) {
    const maxCapacity = maxPoolCapacityKg > 0 ? maxPoolCapacityKg : null;
    const currentLoad = session && session.current_load ? session.current_load : null;
    let fillPercent = null;

    if (maxCapacity && currentLoad && currentLoad.weight !== null && currentLoad.weight !== undefined && !isNaN(currentLoad.weight)) {
        fillPercent = Math.max(0, (parseFloat(currentLoad.weight) / maxCapacity) * 100);
    }

    if (fillPercent === null) {
        return '';
    }

    const clamped = Math.min(fillPercent, 100);
    const progressClass = getFillProgressClass(fillPercent);
    return `
        <div class="pool-fill-info text-center w-100 mb-2">
            <div class="pool-fill-label text-muted small">Заполненность бассейна</div>
            <div class="progress pool-fill-progress">
                <div class="progress-bar ${progressClass}"
                     role="progressbar"
                     style="width: ${clamped}%;"
                     aria-valuenow="${Math.round(clamped)}"
                     aria-valuemin="0"
                     aria-valuemax="100">
                </div>
            </div>
        </div>
    `;
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

// Отображение замеров в блоке бассейна
function renderMeasurements(session) {
    if (!session || !session.last_measurement) {
        return '';
    }
    
    const measurement = session.last_measurement;
    const temp = measurement.temperature;
    const oxygen = measurement.oxygen;
    const tempStratum = measurement.temperature_stratum || 'bad';
    const oxygenStratum = measurement.oxygen_stratum || 'bad';
    const tempTrend = measurement.temperature_trend;
    const tempTrendDirection = measurement.temperature_trend_direction;
    const oxygenTrend = measurement.oxygen_trend;
    const oxygenTrendDirection = measurement.oxygen_trend_direction;
    
    // Определяем цвета для страт
    const tempColorClass = tempStratum === 'good' ? 'text-success' : (tempStratum === 'acceptable' ? 'text-warning' : 'text-danger');
    const oxygenColorClass = oxygenStratum === 'good' ? 'text-success' : (oxygenStratum === 'acceptable' ? 'text-warning' : 'text-danger');
    
    // Определяем стрелочку и её цвет для температуры
    let tempArrowHtml = '';
    if (tempTrend && tempTrend !== 'same') {
        const arrowIcon = 'bi-triangle-fill';
        const arrowStyle = tempTrend === 'up' ? '' : 'transform: rotate(180deg);';
        const arrowColorClass = tempTrendDirection === 'improving' ? 'text-success' : (tempTrendDirection === 'worsening' ? 'text-danger' : 'text-muted');
        tempArrowHtml = `<i class="bi ${arrowIcon} ${arrowColorClass} ms-2" style="font-size: 1.2rem; ${arrowStyle}"></i>`;
    }
    
    // Определяем стрелочку и её цвет для кислорода
    let oxygenArrowHtml = '';
    if (oxygenTrend && oxygenTrend !== 'same') {
        const arrowIcon = 'bi-triangle-fill';
        const arrowStyle = oxygenTrend === 'up' ? '' : 'transform: rotate(180deg);';
        const arrowColorClass = oxygenTrendDirection === 'improving' ? 'text-success' : (oxygenTrendDirection === 'worsening' ? 'text-danger' : 'text-muted');
        oxygenArrowHtml = `<i class="bi ${arrowIcon} ${arrowColorClass} ms-2" style="font-size: 1.2rem; ${arrowStyle}"></i>`;
    }
    
    let html = '<div class="pool-measurements">';
    
    if (temp !== null && temp !== undefined) {
        const tempFormatted = parseFloat(temp).toFixed(1).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        html += `
            <div class="pool-measurement-item">
                <div class="pool-measurement-value ${tempColorClass}">
                    ${tempFormatted}°C
                    ${tempArrowHtml}
                </div>
            </div>
        `;
    }
    
    if (oxygen !== null && oxygen !== undefined) {
        const oxygenFormatted = parseFloat(oxygen).toFixed(1).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        html += `
            <div class="pool-measurement-item">
                <div class="pool-measurement-value ${oxygenColorClass}">
                    O<sub>2</sub> ${oxygenFormatted}
                    ${oxygenArrowHtml}
                </div>
            </div>
        `;
    }
    
    html += '</div>';
    
    // Правая часть со статистикой
    html += '<div class="pool-stats-right">';
    
    const currentLoad = session.current_load || null;
    if (currentLoad) {
        const hasWeight = currentLoad.weight !== null && currentLoad.weight !== undefined && !isNaN(currentLoad.weight);
        const hasFishCount = currentLoad.fish_count !== null && currentLoad.fish_count !== undefined && !isNaN(currentLoad.fish_count);
        
        if (hasWeight || hasFishCount) {
            html += '<div class="pool-current-load text-end fw-semibold text-white mb-2" style="font-size: 1.1rem;">';
            
            if (hasWeight) {
                const weightFormatted = formatNumber(currentLoad.weight, 2);
                if (weightFormatted) {
                    const prefix = currentLoad.weight_is_approximate ? '&asymp;&nbsp;' : '';
                    html += `<div>${prefix}${weightFormatted}&nbsp;кг</div>`;
                }
            }
            
            if (hasFishCount) {
                const fishCountFormatted = formatInteger(currentLoad.fish_count);
                if (fishCountFormatted) {
                    html += `<div>${fishCountFormatted}&nbsp;шт</div>`;
                }
            }
            
            html += '</div>';
        }
    }
    
    if (session.mortality_last_hours) {
        const mortality = session.mortality_last_hours;
        const mortalityCount = parseInt(mortality.total_count || 0);
        const mortalityHours = mortality.hours || 24;
        const mortalityColorClass = mortality.color_class || 'text-danger';
        const mortalityCountFormatted = mortalityCount.toLocaleString('ru-RU');
        let mortalityBadgeClass = mortalityColorClass.replace('text-', 'bg-');
        if (mortalityBadgeClass === mortalityColorClass) {
            mortalityBadgeClass = mortalityColorClass;
        }
        const badgeTextDarkClasses = ['bg-warning', 'bg-light', 'bg-info'];
        const mortalityBadgeTextClass = badgeTextDarkClasses.includes(mortalityBadgeClass) ? 'text-dark' : 'text-white';
        html += `
            <div class="pool-stat-item">
                <div class="pool-stat-value">
                    <span class="badge ${mortalityBadgeClass} ${mortalityBadgeTextClass}">
                        ${mortalityCountFormatted} шт
                    </span>
                </div>
                <div class="pool-stat-label">Падеж за ${mortalityHours} ч</div>
            </div>
        `;
    }
    
    html += '</div>';
    
    return html;
}

function getFillProgressClass(percent) {
    if (percent < 60) {
        return 'bg-primary';
    }
    if (percent <= 85) {
        return 'bg-success';
    }
    return 'bg-warning';
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

function formatNumber(value, fractionDigits = 2) {
    if (value === null || value === undefined || isNaN(value)) {
        return '';
    }
    return Number(value).toLocaleString('ru-RU', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits
    });
}

function formatInteger(value) {
    if (value === null || value === undefined || isNaN(value)) {
        return '';
    }
    return Number(value).toLocaleString('ru-RU');
}

let poolsList = [];
let counterpartiesList = [];
let isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
let measurementWarningTimeoutMinutes = 60; // По умолчанию 60 минут

// Загрузка при открытии страницы
$(document).ready(function() {
    loadMeasurementWarningTimeout();
    loadPools();
    loadPoolsList();
    if (isAdmin) {
        loadCounterpartiesList();
    }
});

// Загрузка настройки времени предупреждения о замерах
function loadMeasurementWarningTimeout() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/settings.php?action=get&key=measurement_warning_timeout_minutes',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                measurementWarningTimeoutMinutes = parseInt(response.value) || 60;
            }
        }
    });
}

// Загрузка списка бассейнов для модальных окон
function loadPoolsList() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/measurements.php?action=get_pools',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                poolsList = response.data;
            }
        }
    });
}

function loadCounterpartiesList() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/counterparties.php?action=list',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                counterpartiesList = response.data;
            }
        }
    });
}

// Открыть модальное окно для добавления замера
function openMeasurementModal(poolId) {
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
        $('#measurementDateTimeField').show();
        $('#measurementDateTime').prop('required', true);
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        $('#measurementDateTime').val(`${year}-${month}-${day}T${hours}:${minutes}`);
    } else {
        $('#measurementDateTimeField').hide();
        $('#measurementDateTime').prop('required', false);
    }
    
    // Устанавливаем текущий бассейн
    if (poolId) {
        $('#currentMeasurementPoolId').val(poolId);
        $('#measurementPool').val(poolId);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('measurementModal'));
    modal.show();
}

// Открыть модальное окно для добавления падежа
function openMortalityModal(poolId) {
    $('#mortalityModalTitle').text('Зарегистрировать падеж');
    $('#mortalityForm')[0].reset();
    $('#mortalityId').val('');
    $('#mortalityPool').prop('disabled', false);
    
    // Заполняем список бассейнов
    const select = $('#mortalityPool');
    select.empty().append('<option value="">Выберите бассейн</option>');
    poolsList.forEach(function(pool) {
        const selected = (poolId && pool.id == poolId) ? 'selected' : '';
        select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
    });
    
    // Показываем поле даты/времени только для администратора
    if (isAdmin) {
        $('#mortalityDateTimeField').show();
        $('#mortalityDateTime').prop('required', true);
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        $('#mortalityDateTime').val(`${year}-${month}-${day}T${hours}:${minutes}`);
    } else {
        $('#mortalityDateTimeField').hide();
        $('#mortalityDateTime').prop('required', false);
    }
    
    // Устанавливаем текущий бассейн
    if (poolId) {
        $('#currentMortalityPoolId').val(poolId);
        $('#mortalityPool').val(poolId);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('mortalityModal'));
    modal.show();
}

// Открыть модальное окно для добавления отбора
function openHarvestModal(poolId) {
    $('#harvestModalTitle').text('Добавить отбор');
    $('#harvestForm')[0].reset();
    $('#harvestId').val('');
    $('#harvestPool').prop('disabled', false);
    
    // Заполняем список бассейнов
    const select = $('#harvestPool');
    select.empty().append('<option value="">Выберите бассейн</option>');
    poolsList.forEach(function(pool) {
        const selected = (poolId && pool.id == poolId) ? 'selected' : '';
        select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
    });
    
    // Показываем поле даты/времени только для администратора
    if (isAdmin) {
        $('#harvestDateTimeField').show();
        $('#harvestDateTime').prop('required', true);
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        $('#harvestDateTime').val(`${year}-${month}-${day}T${hours}:${minutes}`);
    } else {
        $('#harvestDateTimeField').hide();
        $('#harvestDateTime').prop('required', false);
    }
    
    // Устанавливаем текущий бассейн
    if (poolId) {
        $('#currentHarvestPoolId').val(poolId);
        $('#harvestPool').val(poolId);
    }
    
    const counterpartySelect = $('#harvestCounterparty');
    counterpartySelect.empty().append('<option value="">Не указан</option>');
    counterpartiesList.forEach(function(counterparty) {
        const label = counterparty.name ? escapeHtml(counterparty.name) : '—';
        counterpartySelect.append(`<option value="${counterparty.id}">${label}</option>`);
    });
    counterpartySelect.prop('disabled', !isAdmin);
    
    const modal = new bootstrap.Modal(document.getElementById('harvestModal'));
    modal.show();
}

// Сохранить замер
function saveMeasurement() {
    const form = $('#measurementForm')[0];
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const poolId = $('#currentMeasurementPoolId').val() || $('#measurementPool').val();
    const formData = {
        pool_id: parseInt(poolId),
        temperature: parseFloat($('#measurementTemperature').val()),
        oxygen: parseFloat($('#measurementOxygen').val())
    };
    
    if (isAdmin && $('#measurementDateTime').val()) {
        formData.measured_at = $('#measurementDateTime').val().replace('T', ' ');
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/measurements.php?action=create',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#measurementModal').modal('hide');
                // Перезагружаем страницу для обновления данных
                setTimeout(function() {
                    location.reload();
                }, 500);
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

// Сохранить падеж
function saveMortality() {
    const form = $('#mortalityForm')[0];
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const poolId = $('#currentMortalityPoolId').val() || $('#mortalityPool').val();
    const formData = {
        pool_id: parseInt(poolId),
        weight: parseFloat($('#mortalityWeight').val()),
        fish_count: parseInt($('#mortalityFishCount').val())
    };
    
    if (isAdmin && $('#mortalityDateTime').val()) {
        formData.recorded_at = $('#mortalityDateTime').val().replace('T', ' ');
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/mortality.php?action=create',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#mortalityModal').modal('hide');
                // Перезагружаем страницу для обновления данных
                setTimeout(function() {
                    location.reload();
                }, 500);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении падежа');
        }
    });
}

// Сохранить отбор
function saveHarvest() {
    const form = $('#harvestForm')[0];
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const poolId = $('#currentHarvestPoolId').val() || $('#harvestPool').val();
    const formData = {
        pool_id: parseInt(poolId),
        weight: parseFloat($('#harvestWeight').val()),
        fish_count: parseInt($('#harvestFishCount').val())
    };
    const counterpartyValue = $('#harvestCounterparty').val();
    if (counterpartyValue) {
        formData.counterparty_id = parseInt(counterpartyValue);
    }
    
    if (isAdmin && $('#harvestDateTime').val()) {
        formData.recorded_at = $('#harvestDateTime').val().replace('T', ' ');
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/harvests.php?action=create',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#harvestModal').modal('hide');
                // Перезагружаем страницу для обновления данных
                setTimeout(function() {
                    location.reload();
                }, 500);
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
</script>

<?php
// Подключаем шаблоны модальных окон
// Модальное окно для замеров
$modalId = 'measurementModal';
$formId = 'measurementForm';
$poolSelectId = 'measurementPool';
$datetimeFieldId = 'measurementDateTimeField';
$datetimeInputId = 'measurementDateTime';
$temperatureId = 'measurementTemperature';
$oxygenId = 'measurementOxygen';
$currentPoolId = 'currentMeasurementPoolId';
$modalTitleId = 'measurementModalTitle';
$saveFunction = 'saveMeasurement';
require_once __DIR__ . '/../templates/measurement_modal.php';

// Модальное окно для падежа
$modalId = 'mortalityModal';
$formId = 'mortalityForm';
$poolSelectId = 'mortalityPool';
$datetimeFieldId = 'mortalityDateTimeField';
$datetimeInputId = 'mortalityDateTime';
$weightId = 'mortalityWeight';
$fishCountId = 'mortalityFishCount';
$currentPoolId = 'currentMortalityPoolId';
$modalTitleId = 'mortalityModalTitle';
$saveFunction = 'saveMortality';
require_once __DIR__ . '/../templates/mortality_modal.php';

// Модальное окно для отборов
$modalId = 'harvestModal';
$formId = 'harvestForm';
$poolSelectId = 'harvestPool';
$datetimeFieldId = 'harvestDateTimeField';
$datetimeInputId = 'harvestDateTime';
$weightId = 'harvestWeight';
$fishCountId = 'harvestFishCount';
$currentPoolId = 'currentHarvestPoolId';
$modalTitleId = 'harvestModalTitle';
$saveFunction = 'saveHarvest';
require_once __DIR__ . '/../templates/harvest_modal.php';
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
