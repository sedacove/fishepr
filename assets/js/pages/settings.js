const thresholdSliders = {};

// Загрузка настроек
function loadSettings() {
    $.ajax({
        url: BASE_URL + 'api/settings.php?action=list',
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

    resetThresholdSliders();
    const categories = buildSettingsCategories(settings);

    if (categories.length === 0) {
        container.html('<div class="alert alert-info">Настройки не найдены</div>');
        return;
    }

    const tabsWrapper = $(`
        <div class="settings-tabs">
            <ul class="nav nav-tabs" id="settingsTabsNav" role="tablist"></ul>
            <div class="tab-content pt-3" id="settingsTabContent"></div>
        </div>
    `);

    container.append(tabsWrapper);

    const nav = $('#settingsTabsNav');
    const content = $('#settingsTabContent');

    categories.forEach(function(category, index) {
        const isActive = index === 0;
        const tabId = `${category.id}-tab`;
        const panelId = `${category.id}-panel`;

        nav.append(`
            <li class="nav-item" role="presentation">
                <button class="nav-link ${isActive ? 'active' : ''}" id="${tabId}"
                        data-bs-toggle="tab"
                        data-bs-target="#${panelId}"
                        type="button"
                        role="tab"
                        aria-controls="${panelId}"
                        aria-selected="${isActive}">
                    ${escapeHtml(category.title)}
                </button>
            </li>
        `);

        content.append(`
            <div class="tab-pane fade ${isActive ? 'show active' : ''}"
                 id="${panelId}"
                 role="tabpanel"
                 aria-labelledby="${tabId}">
            </div>
        `);

        if (typeof category.render === 'function') {
            const panel = $(`#${panelId}`);
            category.render(panel);
        }
    });
}

function buildSettingsCategories(settings) {
    const categories = [];
    const usedKeys = new Set();

    const thresholds = settings.filter(function(setting) {
        return setting.key.startsWith('temp_') || setting.key.startsWith('oxygen_');
    });
    if (thresholds.length > 0) {
        thresholds.forEach(setting => usedKeys.add(setting.key));
        categories.push({
            id: 'thresholds',
            title: 'Пороговые значения',
            render: function(panel) {
                renderNormalValues(thresholds, panel);
            }
        });
    }

    const notificationSettings = settings.filter(function(setting) {
        return !usedKeys.has(setting.key) && isNotificationSetting(setting.key);
    });
    if (notificationSettings.length > 0) {
        notificationSettings.forEach(setting => usedKeys.add(setting.key));
        categories.push({
            id: 'notifications',
            title: 'Уведомления и мониторинг',
            render: function(panel) {
                renderNotificationSettings(notificationSettings, panel);
            }
        });
    }

    const payrollSettings = settings.filter(function(setting) {
        return !usedKeys.has(setting.key) && setting.key.startsWith('payroll_');
    });
    if (payrollSettings.length > 0) {
        payrollSettings.forEach(setting => usedKeys.add(setting.key));
        categories.push({
            id: 'payroll',
            title: 'Настройки ФЗП',
            render: function(panel) {
                renderPayrollSettings(payrollSettings, panel);
            }
        });
    }

    const systemSettings = settings.filter(function(setting) {
        return !usedKeys.has(setting.key);
    });
    if (systemSettings.length > 0) {
        categories.push({
            id: 'system',
            title: 'Системные настройки',
            render: function(panel) {
                renderGenericSettings(systemSettings, panel);
            }
        });
    }

    return categories;
}

function isNotificationSetting(key) {
    const prefixes = ['telegram_', 'mortality_', 'measurement_warning_', 'weighing_warning_', 'alert_'];
    const explicitKeys = [
        'mortality_alert_threshold',
        'measurement_warning_timeout_minutes',
        'weighing_warning_days',
        'meter_reading_edit_timeout_minutes'
    ];
    return prefixes.some(prefix => key.startsWith(prefix)) || explicitKeys.includes(key);
}

function renderPayrollSettings(settings, container) {
    container.empty();
    if (settings.length === 0) {
        container.append('<div class="alert alert-secondary small mb-0">Нет доступных настроек.</div>');
        return;
    }

    settings.forEach(function(setting) {
        const isAdvance = setting.key === 'payroll_advance_day';
        const label = isAdvance ? 'Дата аванса (день месяца)' : 'Дата зарплаты (день месяца)';
        const min = 1;
        const max = 31;

        container.append(`
            <div class="mb-3 pb-3 border-bottom">
                <div class="row align-items-center g-3">
                    <div class="col-lg-5">
                        <label class="form-label fw-bold mb-1">${escapeHtml(label)}</label>
                        <small class="text-muted d-block">${escapeHtml(setting.key)}</small>
                    </div>
                    <div class="col-lg-4">
                        <input type="number"
                               class="form-control setting-value"
                               data-key="${escapeHtml(setting.key)}"
                               value="${parseInt(setting.value, 10)}"
                               id="setting-${setting.id}"
                               min="${min}"
                               max="${max}">
                        <small class="text-muted">Значение от 1 до 31</small>
                    </div>
                    <div class="col-lg-3">
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
        `);
    });
}

function renderNotificationSettings(settings, container) {
    container.empty();
    if (settings.length === 0) {
        container.append('<div class="alert alert-secondary small mb-0">Нет настроек уведомлений.</div>');
        return;
    }

    container.append('<p class="text-muted small mb-3">Параметры автоматических уведомлений и контрольных интервалов.</p>');
    renderGenericSettings(settings, container, { clear: false });
}

function renderGenericSettings(settings, container, options = {}) {
    const shouldClear = options.clear !== false;
    if (shouldClear) {
        container.empty();
    }

    if (settings.length === 0) {
        container.append('<div class="alert alert-secondary small mb-0">Нет доступных настроек.</div>');
        return;
    }

    settings.forEach(function(setting) {
        if (setting.key === 'show_section_descriptions' || setting.key === 'debug_mode') {
            const isChecked = setting.value === '1' || setting.value === 'true';
            const labelText = setting.key === 'debug_mode'
                ? (setting.description || 'Режим отладки')
                : (setting.description || setting.key);
            const helpText = setting.key === 'debug_mode'
                ? 'При включении собирается отладочная статистика и становится доступна в футере для администраторов.'
                : setting.key;
            container.append(`
                <div class="mb-3 pb-3 border-bottom">
                    <div class="row align-items-center g-3">
                        <div class="col-lg-6">
                            <label class="form-label fw-bold mb-1">${escapeHtml(labelText)}</label>
                            <small class="text-muted d-block">${escapeHtml(helpText)}</small>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="setting-${setting.id}"
                                       ${isChecked ? 'checked' : ''}
                                       onchange="toggleSetting(this, '${escapeHtml(setting.key)}')">
                                <label class="form-check-label" for="setting-${setting.id}">${isChecked ? 'Включено' : 'Выключено'}</label>
                            </div>
                        </div>
                        <div class="col-lg-3 text-lg-end text-muted small">
                            ${setting.updated_at ? `Обновлено: ${escapeHtml(setting.updated_at)}${setting.updated_by_name ? ` (${escapeHtml(setting.updated_by_name)})` : ''}` : ''}
                        </div>
                    </div>
                </div>
            `);
            return;
        }

        container.append(`
            <div class="mb-3 pb-3 border-bottom">
                <div class="row align-items-center g-3">
                    <div class="col-lg-5">
                        <label class="form-label fw-bold mb-1">${escapeHtml(setting.description || setting.key)}</label>
                        <small class="text-muted d-block">${escapeHtml(setting.key)}</small>
                    </div>
                    <div class="col-lg-5">
                        <input type="text"
                               class="form-control setting-value"
                               data-key="${escapeHtml(setting.key)}"
                               value="${escapeHtml(setting.value)}"
                               id="setting-${setting.id}">
                    </div>
                    <div class="col-lg-2">
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
        `);
    });
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
    
    container.empty();
    resetThresholdSliders();
    
    // Температура
    if (Object.keys(tempSettings).length > 0) {
        renderThresholdSlider('temperature', tempSettings, {
            title: 'Температура',
            unit: '°C',
            prefix: 'temp_',
            min: 0,
            max: 25,
            step: 0.1,
            defaultValues: [5, 10, 15, 20],
            zoneNames: ['Красная зона', 'Желтая зона', 'Зеленая зона', 'Желтая зона', 'Красная зона'],
            zoneClasses: ['text-danger', 'text-warning', 'text-success', 'text-warning', 'text-danger'],
            segmentClasses: ['threshold-segment-red', 'threshold-segment-yellow', 'threshold-segment-green', 'threshold-segment-yellow', 'threshold-segment-red'],
            saveMappings: [
                { settings: ['temp_bad_below', 'temp_acceptable_min'], valueIndex: 0 },
                { settings: ['temp_good_min'], valueIndex: 1 },
                { settings: ['temp_good_max'], valueIndex: 2 },
                { settings: ['temp_acceptable_max', 'temp_bad_above'], valueIndex: 3 }
            ]
        }, container);
    }
    
    // Кислород
    if (Object.keys(oxygenSettings).length > 0) {
        renderThresholdSlider('oxygen', oxygenSettings, {
            title: 'Кислород (O2)',
            unit: 'мг/л',
            prefix: 'oxygen_',
            min: 0,
            max: 20,
            step: 0.1,
            defaultValues: [4, 6, 8, 10],
            zoneNames: ['Красная зона', 'Желтая зона', 'Зеленая зона', 'Желтая зона', 'Красная зона'],
            zoneClasses: ['text-danger', 'text-warning', 'text-success', 'text-warning', 'text-danger'],
            segmentClasses: ['threshold-segment-red', 'threshold-segment-yellow', 'threshold-segment-green', 'threshold-segment-yellow', 'threshold-segment-red'],
            saveMappings: [
                { settings: ['oxygen_bad_below', 'oxygen_acceptable_min'], valueIndex: 0 },
                { settings: ['oxygen_good_min'], valueIndex: 1 },
                { settings: ['oxygen_good_max'], valueIndex: 2 },
                { settings: ['oxygen_acceptable_max', 'oxygen_bad_above'], valueIndex: 3 }
            ]
        }, container);
    }
}

function resetThresholdSliders() {
    Object.keys(thresholdSliders).forEach(function(key) {
        const meta = thresholdSliders[key];
        if (meta && meta.sliderElement && meta.sliderElement.noUiSlider) {
            meta.sliderElement.noUiSlider.destroy();
        }
        delete thresholdSliders[key];
    });
}

function renderThresholdSlider(key, settingsMap, options, container) {
    const sliderId = `threshold-slider-${key}`;
    const zoneNames = options.zoneNames || ['Зона 1', 'Зона 2', 'Зона 3', 'Зона 4', 'Зона 5'];
    const zoneClasses = options.zoneClasses || ['text-muted', 'text-muted', 'text-muted', 'text-muted', 'text-muted'];
    const segmentClasses = options.segmentClasses || ['threshold-segment-red', 'threshold-segment-yellow', 'threshold-segment-green', 'threshold-segment-yellow', 'threshold-segment-red'];
    const defaultValues = options.defaultValues || [0, 0, 0, 0];
    const decimals = options.decimals ?? 1;

    const parseSettingValue = (settingKey, fallback) => {
        if (settingsMap[settingKey] && !isNaN(parseFloat(settingsMap[settingKey].value))) {
            return parseFloat(settingsMap[settingKey].value);
        }
        return fallback;
    };

    const initialValuesRaw = [
        parseSettingValue(`${options.prefix}acceptable_min`, defaultValues[0]),
        parseSettingValue(`${options.prefix}good_min`, defaultValues[1]),
        parseSettingValue(`${options.prefix}good_max`, defaultValues[2]),
        parseSettingValue(`${options.prefix}acceptable_max`, defaultValues[3])
    ];

    const meta = {
        key,
        title: options.title || '',
        unit: options.unit || '',
        prefix: options.prefix,
        sliderId,
        settingsMap,
        min: options.min ?? 0,
        max: options.max ?? 100,
        step: options.step ?? 0.1,
        decimals,
        zoneNames,
        zoneClasses,
        segmentClasses,
        saveMappings: options.saveMappings || [],
        currentValues: []
    };

    meta.currentValues = normalizeThresholdValues(initialValuesRaw, meta);
    thresholdSliders[key] = meta;

    const zoneLabelsHtml = zoneNames.map(function(name, index) {
        const zoneClass = zoneClasses[index] || '';
        return `<div class="threshold-zone-label ${zoneClass}" data-value="${index}">${escapeHtml(name)}</div>`;
    }).join('');

    const valueLabelsHtml = meta.currentValues.map(function(value, index) {
        return `
            <div class="threshold-value-label" data-index="${index}">
                <span id="threshold-value-${key}-${index}">${formatThresholdValue(value, meta)}${meta.unit ? ' ' + meta.unit : ''}</span>
            </div>
        `;
    }).join('');

    const html = `
        <div class="mb-5">
            <h6 class="mb-3">${escapeHtml(meta.title)} (${escapeHtml(meta.unit)})</h6>
            <div class="threshold-slider-wrapper" data-slider-key="${key}">
                <div class="threshold-zone-labels" data-slider-key="${key}">
                    ${zoneLabelsHtml}
                </div>
                <div id="${sliderId}" class="threshold-range-slider"></div>
                <div class="threshold-value-labels" data-slider-key="${key}">
                    ${valueLabelsHtml}
                </div>
            </div>
            <div class="text-center">
                <button class="btn btn-primary btn-sm threshold-save-btn" onclick="saveThresholdSlider('${key}')">
                    <i class="bi bi-save"></i> Сохранить
                </button>
            </div>
        </div>
    `;
    container.append(html);

    initThresholdSlider(key);
    updateThresholdValueLabels(key);
}

function initThresholdSlider(key) {
    const meta = thresholdSliders[key];
    if (!meta) {
        return;
    }
    const sliderElement = document.getElementById(meta.sliderId);
    if (!sliderElement) {
        return;
    }

    meta.sliderElement = sliderElement;
    if (sliderElement.noUiSlider) {
        sliderElement.noUiSlider.destroy();
    }

    const connectConfig = new Array(meta.currentValues.length + 1).fill(true);

    noUiSlider.create(sliderElement, {
        start: meta.currentValues,
        connect: connectConfig,
        range: {
            min: meta.min,
            max: meta.max
        },
        step: meta.step,
        behaviour: 'tap-drag'
    });

    applyThresholdSegmentClasses(sliderElement, meta);

    sliderElement.noUiSlider.on('update', function(values) {
        if (meta.isUpdating) {
            return;
        }

        const numericValues = values.map(function(value) {
            return clampThresholdValue(parseFloat(value), meta);
        });
        const normalized = normalizeThresholdValues(numericValues, meta);

        if (!arraysEqual(numericValues, normalized)) {
            meta.isUpdating = true;
            sliderElement.noUiSlider.set(normalized);
            meta.isUpdating = false;
        }

        meta.currentValues = normalized;
        updateThresholdValueLabels(key);
    });
}

const THRESHOLD_SEGMENT_CLASSES = ['threshold-segment-red', 'threshold-segment-yellow', 'threshold-segment-green'];

function applyThresholdSegmentClasses(sliderElement, meta) {
    const connects = sliderElement.querySelectorAll('.noUi-connect');
    const classesToRemove = Array.from(new Set(THRESHOLD_SEGMENT_CLASSES.concat(meta.segmentClasses || [])));

    connects.forEach(function(connectEl, index) {
        connectEl.classList.remove(...classesToRemove);
        const segmentClass = meta.segmentClasses && meta.segmentClasses[index];
        if (segmentClass) {
            connectEl.classList.add(segmentClass);
        }
    });
}

function clampThresholdValue(value, meta) {
    if (isNaN(value)) {
        return meta.min;
    }
    return Math.min(meta.max, Math.max(meta.min, value));
}

function normalizeThresholdValues(values, meta) {
    const normalized = values.slice().map(function(value) {
        return clampThresholdValue(value, meta);
    });
    for (let i = 1; i < normalized.length; i++) {
        if (normalized[i] < normalized[i - 1]) {
            normalized[i] = normalized[i - 1];
        }
    }
    return normalized;
}

function formatThresholdValue(value, meta) {
    const fixed = Number(value || meta.min).toFixed(meta.decimals);
    return fixed.replace('.', ',');
}

function updateThresholdValueLabels(key) {
    const meta = thresholdSliders[key];
    if (!meta) {
        return;
    }

    const formattedValues = meta.currentValues.map(function(value) {
        return `${formatThresholdValue(value, meta)}${meta.unit ? ' ' + meta.unit : ''}`;
    });

    meta.currentValues.forEach(function(value, index) {
        $(`#threshold-value-${key}-${index}`).text(formattedValues[index]);
    });

    const labelsContainer = document.querySelector(`.threshold-value-labels[data-slider-key="${key}"]`);
    if (labelsContainer) {
        const labels = labelsContainer.querySelectorAll('.threshold-value-label');
        labels.forEach(function(label, index) {
            const value = meta.currentValues[index] ?? meta.min;
            const percent = ((clampThresholdValue(value, meta) - meta.min) / (meta.max - meta.min)) * 100;
            label.style.left = `${percent}%`;
        });
    }

    updateThresholdZoneLabels(key);
}

function updateThresholdZoneLabels(key) {
    const meta = thresholdSliders[key];
    if (!meta) {
        return;
    }

    const boundaries = [meta.min, ...meta.currentValues, meta.max];
    const zoneContainer = document.querySelector(`.threshold-zone-labels[data-slider-key="${key}"]`);
    if (!zoneContainer) {
        return;
    }

    const labels = zoneContainer.querySelectorAll('.threshold-zone-label');
    labels.forEach(function(label, index) {
        const start = boundaries[index] ?? meta.min;
        const end = boundaries[index + 1] ?? meta.max;
        const middle = (start + end) / 2;
        const percent = ((middle - meta.min) / (meta.max - meta.min)) * 100;
        label.style.left = `${percent}%`;
    });
}

function saveThresholdSlider(key) {
    const meta = thresholdSliders[key];
    if (!meta || !meta.currentValues) {
        showAlert('warning', 'Нет данных для сохранения');
        return;
    }

    const formattedValues = meta.currentValues.map(function(value) {
        return Number(clampThresholdValue(value, meta)).toFixed(meta.decimals);
    });

    const payloads = [];
    meta.saveMappings.forEach(function(mapping) {
        const mappedValue = formattedValues[mapping.valueIndex];
        if (mappedValue === undefined) {
            return;
        }
        mapping.settings.forEach(function(settingKey) {
            payloads.push({
                key: settingKey,
                value: mappedValue
            });
        });
    });

    if (payloads.length === 0) {
        showAlert('warning', 'Нет данных для сохранения');
        return;
    }

    let saved = 0;
    let errors = 0;

    payloads.forEach(function(payload) {
        $.ajax({
            url: BASE_URL + 'api/settings.php?action=update',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    saved++;
                } else {
                    errors++;
                }
                finalizeThresholdSave(meta, saved, errors, payloads.length);
            },
            error: function() {
                errors++;
                finalizeThresholdSave(meta, saved, errors, payloads.length);
            }
        });
    });
}

function finalizeThresholdSave(meta, saved, errors, total) {
    if (saved + errors !== total) {
        return;
    }
    if (errors === 0) {
        showAlert('success', `${meta.title} успешно сохранены`);
        loadSettings();
    } else if (saved > 0) {
        showAlert('warning', `Сохранено: ${saved}, ошибок: ${errors}`);
    } else {
        showAlert('danger', `Не удалось сохранить значения (${meta.title})`);
    }
}

function arraysEqual(a, b) {
    if (a.length !== b.length) {
        return false;
    }
    for (let i = 0; i < a.length; i++) {
        if (a[i] !== b[i]) {
            return false;
        }
    }
    return true;
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
        url: BASE_URL + 'api/settings.php?action=update',
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
        url: BASE_URL + 'api/settings.php?action=update',
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
        url: BASE_URL + 'api/settings.php?action=update',
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

