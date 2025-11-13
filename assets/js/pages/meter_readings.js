(function() {
    'use strict';

    if (window.__meterReadingsPageInitialized) {
        return;
    }
    window.__meterReadingsPageInitialized = true;

    const config = window.meterReadingsConfig || {};
    const baseUrl = config.baseUrl || '';
    const isAdmin = Boolean(config.isAdmin);

    let metersList = [];
    let currentMeterId = null;
    let currentReadingId = null;
    let readingModal = null;

    function toDateTimeLocalValue(date) {
        const pad = (value) => String(value).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const modalElement = document.getElementById('readingModal');
        if (modalElement) {
            readingModal = new bootstrap.Modal(modalElement);
        }
        loadMeters();
    });

    function loadMeters() {
        $.ajax({
            url: `${baseUrl}api/meters.php?action=list`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    metersList = response.data || [];
                    renderMetersTabs();
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить список приборов');
                }
            },
            error: function(xhr, status, error) {
                console.error('loadMeters error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке списка приборов');
            }
        });
    }

    function renderMetersTabs() {
        const tabsNav = $('#metersTabs');
        const tabsContent = $('#metersTabContent');
        tabsNav.empty();
        tabsContent.empty();

        if (!metersList.length) {
            tabsContent.html('<div class="alert alert-info">Приборы учета не настроены. Обратитесь к администратору.</div>');
            return;
        }

        metersList.forEach(function(meter, index) {
            const tabId = 'meter-' + meter.id;
            const isActive = index === 0 ? 'active' : '';
            const show = index === 0 ? 'show active' : '';

            tabsNav.append(`
                <li class="nav-item" role="presentation">
                    <button class="nav-link ${isActive}" id="${tabId}-tab" data-bs-toggle="tab"
                            data-bs-target="#${tabId}" type="button" role="tab"
                            aria-controls="${tabId}" aria-selected="${index === 0}"
                            onclick="switchMeter(${meter.id})">
                        ${escapeHtml(meter.name)}
                    </button>
                </li>
            `);

            tabsContent.append(`
                <div class="tab-pane fade ${show}" id="${tabId}" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <div>
                                    <h5 class="card-title mb-1">${escapeHtml(meter.name)}</h5>
                                    ${meter.description ? `<p class="text-muted mb-0">${escapeHtml(meter.description)}</p>` : ''}
                                </div>
                                <button class="btn btn-primary btn-sm" onclick="openReadingModal(${meter.id})">
                                    <i class="bi bi-plus-circle"></i> Добавить показание
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Дата</th>
                                            <th>Показание</th>
                                            <th>Кто вносил</th>
                                            <th class="text-end">Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody id="readingsTableBody-${meter.id}">
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
            `);
        });

        currentMeterId = metersList[0].id;
        loadReadings(currentMeterId);
    }

    function switchMeter(meterId) {
        currentMeterId = meterId;
        loadReadings(meterId);
    }

    function loadReadings(meterId) {
        const tbody = $(`#readingsTableBody-${meterId}`);
        tbody.html(`
            <tr>
                <td colspan="4" class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    <div>Загрузка...</div>
                </td>
            </tr>
        `);

        $.ajax({
            url: `${baseUrl}api/meter_readings.php?action=list&meter_id=${meterId}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderReadings(tbody, response.data || []);
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить показания');
                }
            },
            error: function(xhr, status, error) {
                console.error('loadReadings error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке показаний');
            }
        });
    }

    function renderReadings(tbody, readings) {
        tbody.empty();

        if (!readings.length) {
            tbody.html('<tr><td colspan="4" class="text-center text-muted py-4">Показаний пока нет</td></tr>');
            return;
        }

        readings.forEach(function(reading) {
            const actions = [];
            if (reading.can_edit) {
                actions.push(`
                    <button class="btn btn-sm btn-primary me-2" onclick="openReadingModal(${reading.meter_id}, ${reading.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                `);
            }
            if (reading.can_delete) {
                actions.push(`
                    <button class="btn btn-sm btn-danger" onclick="confirmDeleteReading(${reading.id}, ${reading.meter_id})">
                        <i class="bi bi-trash"></i>
                    </button>
                `);
            }

            const formattedValue = Number(reading.reading_value).toLocaleString('ru-RU', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 4
            });

            tbody.append(`
                <tr>
                    <td>${escapeHtml(reading.recorded_at_display || '')}</td>
                    <td class="fw-semibold">${formattedValue}</td>
                    <td>${escapeHtml(reading.recorded_by_label || '')}</td>
                    <td class="text-end">
                        ${actions.join('') || '<span class="text-muted">—</span>'}
                    </td>
                </tr>
            `);
        });
    }

    function openReadingModal(meterId, readingId = null) {
        currentMeterId = meterId;
        currentReadingId = readingId;
        const form = document.getElementById('readingForm');
        if (form) {
            form.reset();
        }
        $('#readingMeterId').val(meterId);
        $('#readingId').val(readingId || '');
        $('#readingModalTitle').text(readingId ? 'Редактировать показание' : 'Добавить показание');
        const recordedAtInput = $('#readingRecordedAt');
        if (recordedAtInput.length) {
            recordedAtInput.val(toDateTimeLocalValue(new Date()));
        }

        if (readingId) {
            $.ajax({
                url: `${baseUrl}api/meter_readings.php?action=get&id=${readingId}`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        $('#readingValue').val(response.data.reading_value);
                        if (recordedAtInput.length && response.data.recorded_at_iso) {
                            recordedAtInput.val(response.data.recorded_at_iso);
                        }
                        if (readingModal) {
                            readingModal.show();
                        }
                    } else {
                        showAlert('danger', response.message || 'Не удалось получить показание');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('openReadingModal load error:', status, error, xhr.responseText);
                    showAlert('danger', 'Ошибка при загрузке показания');
                }
            });
        } else {
            if (readingModal) {
                readingModal.show();
            }
        }
    }

    function saveReading() {
        const meterId = parseInt($('#readingMeterId').val(), 10);
        const readingId = $('#readingId').val() ? parseInt($('#readingId').val(), 10) : null;
        const valueRaw = $('#readingValue').val();
        if (valueRaw === '') {
            showAlert('warning', 'Введите показание');
            return;
        }

        const payload = {
            meter_id: meterId,
            reading_value: parseFloat(valueRaw)
        };
        if (readingId) {
            payload.id = readingId;
        }
        const recordedAtInput = $('#readingRecordedAt');
        if (recordedAtInput.length) {
            const recordedAtValue = recordedAtInput.val();
            // Всегда передаем recorded_at, даже если пустое, чтобы сервер мог обработать
            payload.recorded_at = recordedAtValue || null;
        }

        const action = readingId ? 'update' : 'create';

        $.ajax({
            url: `${baseUrl}api/meter_readings.php?action=${action}`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Показание сохранено');
                    if (readingModal) {
                        readingModal.hide();
                    }
                    loadReadings(meterId);
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить показание');
                }
            },
            error: function(xhr, status, error) {
                console.error('saveReading error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при сохранении показания');
            }
        });
    }

    function confirmDeleteReading(readingId, meterId) {
        if (!confirm('Удалить показание?')) {
            return;
        }

        $.ajax({
            url: `${baseUrl}api/meter_readings.php?action=delete`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: readingId }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Показание удалено');
                    loadReadings(meterId);
                } else {
                    showAlert('danger', response.message || 'Не удалось удалить показание');
                }
            },
            error: function(xhr, status, error) {
                console.error('deleteReading error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при удалении показания');
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

    window.switchMeter = switchMeter;
    window.openReadingModal = openReadingModal;
    window.saveReading = saveReading;
    window.confirmDeleteReading = confirmDeleteReading;
})();
