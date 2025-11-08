<?php
/**
 * Страница настроек
 * Доступна только администраторам
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';

$page_title = 'Настройки';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Настройки системы</h1>
            <?php renderSectionDescription('settings'); ?>
        </div>
    </div>
    
    <div id="alert-container"></div>
    
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-4">Системные настройки</h5>
            
            <div id="settingsContainer">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Загрузка настроек
function loadSettings() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/settings.php?action=list',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderSettings(response.data);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке настроек');
        }
    });
}

// Отображение настроек
function renderSettings(settings) {
    const container = $('#settingsContainer');
    container.empty();
    
    if (settings.length === 0) {
        container.html('<div class="alert alert-info">Настройки не найдены</div>');
        return;
    }
    
    // Разделяем настройки на группы
    const normalValuesSettings = [];
    const otherSettings = [];
    
    const payrollSettings = [];
    
    settings.forEach(function(setting) {
        if (setting.key.startsWith('temp_') || setting.key.startsWith('oxygen_')) {
            normalValuesSettings.push(setting);
        } else if (setting.key.startsWith('payroll_')) {
            payrollSettings.push(setting);
        } else {
            otherSettings.push(setting);
        }
    });
    
    // Рендерим блок нормальных значений
    if (normalValuesSettings.length > 0) {
        renderNormalValues(normalValuesSettings, container);
    }
    
    // Рендерим остальные настройки
    if (payrollSettings.length > 0) {
        container.append('<hr class="my-4">');
        container.append('<h5 class="mb-4">Настройки ФЗП</h5>');
        payrollSettings.forEach(function(setting) {
            const isAdvance = setting.key === 'payroll_advance_day';
            const label = isAdvance ? 'Дата аванса (день месяца)' : 'Дата зарплаты (день месяца)';
            const min = 1;
            const max = 31;
            
            const html = `
                <div class="mb-4 pb-4 border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <label class="form-label fw-bold mb-1">${escapeHtml(label)}</label>
                            <small class="text-muted d-block">${escapeHtml(setting.key)}</small>
                        </div>
                        <div class="col-md-4">
                            <input type="number" 
                                   class="form-control setting-value" 
                                   data-key="${escapeHtml(setting.key)}" 
                                   value="${parseInt(setting.value, 10)}"
                                   id="setting-${setting.id}"
                                   min="${min}"
                                   max="${max}">
                            <small class="text-muted">Значение от 1 до 31</small>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary btn-sm w-100" 
                                    onclick="savePayrollSetting('${escapeHtml(setting.key)}', ${setting.id})">
                                <i class="bi bi-save"></i> Сохранить
                            </button>
                        </div>
                    </div>
                    ${setting.updated_at ? `
                        <small class="text-muted mt-2 d-block">
                            Обновлено: ${escapeHtml(setting.updated_at)}
                            ${setting.updated_by_name ? ` (${escapeHtml(setting.updated_by_name)})` : ''}
                        </small>
                    ` : ''}
                </div>
            `;
            container.append(html);
        });
    }
    
    if (otherSettings.length > 0) {
        container.append('<hr class="my-4">');
        container.append('<h5 class="mb-4">Системные настройки</h5>');
        otherSettings.forEach(function(setting) {
            if (setting.key === 'show_section_descriptions') {
                const isChecked = setting.value === '1' || setting.value === 'true';
                const settingHtml = `
                    <div class="mb-4 pb-4 border-bottom">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-1">${escapeHtml(setting.description || setting.key)}</label>
                                <small class="text-muted d-block">${escapeHtml(setting.key)}</small>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="setting-${setting.id}"
                                           ${isChecked ? 'checked' : ''}
                                           onchange="toggleSetting(this, '${escapeHtml(setting.key)}')">
                                    <label class="form-check-label" for="setting-${setting.id}">${isChecked ? 'Включено' : 'Выключено'}</label>
                                </div>
                            </div>
                            <div class="col-md-3 text-md-end text-muted">
                                ${setting.updated_at ? `Обновлено: ${escapeHtml(setting.updated_at)}${setting.updated_by_name ? ` (${escapeHtml(setting.updated_by_name)})` : ''}` : ''}
                            </div>
                        </div>
                    </div>
                `;
                container.append(settingHtml);
                return;
            }
            
            const settingHtml = `
                <div class="mb-4 pb-4 border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">${escapeHtml(setting.description || setting.key)}</label>
                            <small class="text-muted d-block">${escapeHtml(setting.key)}</small>
                        </div>
                        <div class="col-md-6">
                            <input type="text" 
                                   class="form-control setting-value" 
                                   data-key="${escapeHtml(setting.key)}" 
                                   value="${escapeHtml(setting.value)}"
                                   id="setting-${setting.id}">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary btn-sm w-100" 
                                    onclick="saveSetting('${escapeHtml(setting.key)}', ${setting.id})">
                                <i class="bi bi-save"></i> Сохранить
                            </button>
                        </div>
                    </div>
                    ${setting.updated_at ? `
                        <small class="text-muted mt-2 d-block">
                            Обновлено: ${escapeHtml(setting.updated_at)}
                            ${setting.updated_by_name ? ` (${escapeHtml(setting.updated_by_name)})` : ''}
                        </small>
                    ` : ''}
                </div>
            `;
            container.append(settingHtml);
        });
    }
}

// Отображение нормальных значений показателей
function renderNormalValues(settings, container) {
    // Группируем по типу показателя
    const tempSettings = {};
    const oxygenSettings = {};
    
    settings.forEach(function(setting) {
        if (setting.key.startsWith('temp_')) {
            tempSettings[setting.key] = setting;
        } else if (setting.key.startsWith('oxygen_')) {
            oxygenSettings[setting.key] = setting;
        }
    });
    
    container.append('<h5 class="mb-4">Нормальные значения показателей</h5>');
    
    // Температура
    if (Object.keys(tempSettings).length > 0) {
        container.append(renderGradientScale('Температура', tempSettings, 'temp_', '°C'));
    }
    
    // Кислород
    if (Object.keys(oxygenSettings).length > 0) {
        container.append(renderGradientScale('Кислород (O2)', oxygenSettings, 'oxygen_', 'мг/л'));
    }
}

// Рендеринг градиентной шкалы
function renderGradientScale(title, settings, prefix, unit) {
    const badBelow = parseFloat(settings[prefix + 'bad_below']?.value || 0);
    const acceptableMin = parseFloat(settings[prefix + 'acceptable_min']?.value || 0);
    const goodMin = parseFloat(settings[prefix + 'good_min']?.value || 0);
    const goodMax = parseFloat(settings[prefix + 'good_max']?.value || 0);
    const acceptableMax = parseFloat(settings[prefix + 'acceptable_max']?.value || 0);
    const badAbove = parseFloat(settings[prefix + 'bad_above']?.value || 0);
    
    // Вычисляем диапазон для шкалы
    const maxValue = Math.max(badAbove, acceptableMax, goodMax) * 1.1;
    const minValue = Math.min(badBelow, acceptableMin, goodMin) * 0.9;
    const range = maxValue - minValue;
    
    // Функция для вычисления позиции в процентах
    const getPosition = (value) => {
        return ((value - minValue) / range) * 100;
    };
    
    const badBelowPos = getPosition(badBelow);
    const acceptableMinPos = getPosition(acceptableMin);
    const goodMinPos = getPosition(goodMin);
    const goodMaxPos = getPosition(goodMax);
    const acceptableMaxPos = getPosition(acceptableMax);
    const badAbovePos = getPosition(badAbove);
    
    // Создаем плавный градиент
    // Красный (плохо) -> Желтый (допустимо) -> Зеленый (хорошо) -> Желтый (допустимо) -> Красный (плохо)
    // Градиент автоматически создает плавные переходы между цветами
    const gradientStops = [
        `#dc3545 0%`,
        `#dc3545 ${badBelowPos}%`,
        `#ffc107 ${badBelowPos}%`,
        `#ffc107 ${acceptableMinPos}%`,
        `#28a745 ${acceptableMinPos}%`,
        `#28a745 ${goodMinPos}%`,
        `#28a745 ${goodMaxPos}%`,
        `#ffc107 ${goodMaxPos}%`,
        `#ffc107 ${acceptableMaxPos}%`,
        `#dc3545 ${acceptableMaxPos}%`,
        `#dc3545 ${badAbovePos}%`,
        `#dc3545 100%`
    ].join(', ');
    
    const badBelowId = settings[prefix + 'bad_below']?.id || 0;
    const acceptableMinId = settings[prefix + 'acceptable_min']?.id || 0;
    const goodMinId = settings[prefix + 'good_min']?.id || 0;
    const goodMaxId = settings[prefix + 'good_max']?.id || 0;
    const acceptableMaxId = settings[prefix + 'acceptable_max']?.id || 0;
    const badAboveId = settings[prefix + 'bad_above']?.id || 0;
    
    return `
        <div class="mb-5">
            <h6 class="mb-3">${escapeHtml(title)} (${unit})</h6>
            <div class="gradient-scale-container">
                <div class="gradient-scale-wrapper">
                    <div class="gradient-scale" style="background: linear-gradient(to right, ${gradientStops});">
                        <div class="gradient-scale-input" style="left: ${badBelowPos}%;">
                            <input type="number" 
                                   class="form-control form-control-sm gradient-input" 
                                   data-key="${prefix}bad_below" 
                                   value="${badBelow}"
                                   id="setting-${badBelowId}"
                                   step="0.1">
                        </div>
                        <div class="gradient-scale-input" style="left: ${acceptableMinPos}%;">
                            <input type="number" 
                                   class="form-control form-control-sm gradient-input" 
                                   data-key="${prefix}acceptable_min" 
                                   value="${acceptableMin}"
                                   id="setting-${acceptableMinId}"
                                   step="0.1">
                        </div>
                        <div class="gradient-scale-input" style="left: ${goodMinPos}%;">
                            <input type="number" 
                                   class="form-control form-control-sm gradient-input" 
                                   data-key="${prefix}good_min" 
                                   value="${goodMin}"
                                   id="setting-${goodMinId}"
                                   step="0.1">
                        </div>
                        <div class="gradient-scale-input" style="left: ${goodMaxPos}%;">
                            <input type="number" 
                                   class="form-control form-control-sm gradient-input" 
                                   data-key="${prefix}good_max" 
                                   value="${goodMax}"
                                   id="setting-${goodMaxId}"
                                   step="0.1">
                        </div>
                        <div class="gradient-scale-input" style="left: ${acceptableMaxPos}%;">
                            <input type="number" 
                                   class="form-control form-control-sm gradient-input" 
                                   data-key="${prefix}acceptable_max" 
                                   value="${acceptableMax}"
                                   id="setting-${acceptableMaxId}"
                                   step="0.1">
                        </div>
                        <div class="gradient-scale-input" style="left: ${badAbovePos}%;">
                            <input type="number" 
                                   class="form-control form-control-sm gradient-input" 
                                   data-key="${prefix}bad_above" 
                                   value="${badAbove}"
                                   id="setting-${badAboveId}"
                                   step="0.1">
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-center">
                    <button class="btn btn-primary btn-sm" onclick="saveAllGradientSettings('${prefix}')">
                        <i class="bi bi-save"></i> Сохранить все значения
                    </button>
                </div>
            </div>
        </div>
    `;
}

// Сохранить все настройки градиента
function saveAllGradientSettings(prefix) {
    const inputs = $(`.gradient-input[data-key^="${prefix}"]`);
    let saved = 0;
    let errors = 0;
    
    inputs.each(function() {
        const input = $(this);
        const key = input.data('key');
        const id = input.attr('id').replace('setting-', '');
        
        const value = input.val().trim();
        if (value === '') {
            errors++;
            return;
        }
        
        const numValue = parseFloat(value);
        if (isNaN(numValue)) {
            errors++;
            return;
        }
        
        // Сохраняем асинхронно
        $.ajax({
            url: '<?php echo BASE_URL; ?>api/settings.php?action=update',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                key: key,
                value: value
            }),
            dataType: 'json',
            success: function(response) {
                saved++;
                if (saved + errors === inputs.length) {
                    if (errors === 0) {
                        showAlert('success', 'Все значения успешно сохранены');
                        loadSettings();
                    } else {
                        showAlert('warning', `Сохранено: ${saved}, ошибок: ${errors}`);
                    }
                }
            },
            error: function() {
                errors++;
                if (saved + errors === inputs.length) {
                    showAlert('warning', `Сохранено: ${saved}, ошибок: ${errors}`);
                }
            }
        });
    });
    
    if (inputs.length === 0) {
        showAlert('warning', 'Нет значений для сохранения');
    }
}

// Сохранить настройку
function saveSetting(key, id) {
    const input = $(`#setting-${id}`);
    const value = input.val().trim();
    
    // Валидация для числовых значений
    if (key === 'measurement_edit_timeout_minutes') {
        const numValue = parseInt(value);
        if (isNaN(numValue) || numValue < 1) {
            showAlert('warning', 'Значение должно быть положительным числом');
            return;
        }
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/settings.php?action=update',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            key: key,
            value: value
        }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                // Перезагружаем настройки
                loadSettings();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении настройки');
        }
    });
}

function savePayrollSetting(key, id) {
    const input = $(`#setting-${id}`);
    const value = input.val().trim();
    const numValue = parseInt(value, 10);
    
    if (isNaN(numValue) || numValue < 1 || numValue > 31) {
        showAlert('warning', 'Введите день месяца от 1 до 31');
        input.focus();
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/settings.php?action=update',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            key: key,
            value: String(numValue)
        }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadSettings();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении настройки');
        }
    });
}

function toggleSetting(checkbox, key) {
    const value = checkbox.checked ? '1' : '0';
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/settings.php?action=update',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            key: key,
            value: value
        }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                if (checkbox.nextElementSibling) {
                    checkbox.nextElementSibling.textContent = checkbox.checked ? 'Включено' : 'Выключено';
                }
            } else {
                checkbox.checked = !checkbox.checked;
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            checkbox.checked = !checkbox.checked;
            const res = xhr.responseJSON || {};
            showAlert('danger', res.message || 'Ошибка при сохранении настройки');
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
    loadSettings();
    
    // Сохранение по Enter
    $(document).on('keypress', '.setting-value', function(e) {
        if (e.which === 13) {
            const key = $(this).data('key');
            const id = $(this).attr('id').replace('setting-', '');
            saveSetting(key, id);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
