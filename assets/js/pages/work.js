(function() {
    'use strict';

    if (window.__workPageInitialized) {
        return;
    }
    window.__workPageInitialized = true;

    const config = window.workConfig || {};
    const baseUrl = config.baseUrl || '';
    const isAdmin = Boolean(config.isAdmin);
    const maxPoolCapacityKg = Number(config.maxPoolCapacityKg) || 0;
    const feedingStrategyLabels = {
        econom: 'Эконом',
        normal: 'Норма',
        growth: 'Рост'
    };

    let measurementWarningTimeoutMinutes = 60;
    let poolsList = [];
    let counterpartiesList = [];

    document.addEventListener('DOMContentLoaded', function() {
        loadMeasurementWarningTimeout();
        loadPools();
        loadPoolsList();
        if (isAdmin) {
            loadCounterpartiesList();
        }
    });

    function getModalInstance(id) {
        const el = document.getElementById(id);
        return el ? bootstrap.Modal.getOrCreateInstance(el) : null;
    }

    function loadPools() {
        const grid = $('#poolsGrid');

        $.ajax({
            url: `${baseUrl}api/work.php`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderPools(response.data || []);
                } else {
                    showAlert('danger', response.message || 'Ошибка загрузки данных');
                    grid.html('<div class="alert alert-warning">Ошибка загрузки данных</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('loadPools error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке бассейнов');
                grid.html('<div class="alert alert-danger">Ошибка при загрузке данных</div>');
            }
        });
    }

    function renderPools(pools) {
        const grid = $('#poolsGrid');
        grid.empty();

        if (!pools.length) {
            grid.html('<div class="alert alert-info w-100">Нет активных бассейнов</div>');
            return;
        }

        pools.forEach(function(pool) {
            const session = pool.active_session || null;
            const isEmpty = !session;

            let measurementWarningHtml = '';
            let weighingWarningHtml = '';
            if (session) {
                const diffMinutes = typeof session.last_measurement_diff_minutes === 'number'
                    ? session.last_measurement_diff_minutes
                    : (session.last_measurement_diff_minutes === null ? null : undefined);
                const diffLabel = session.last_measurement_diff_label || '';

                if (diffMinutes === null) {
                    const tooltip = escapeHtml(diffLabel || 'Последний замер отсутствует');
                    measurementWarningHtml = `<i class="bi bi-exclamation-triangle-fill text-danger me-2" title="${tooltip}" style="font-size: 1.2rem;"></i>`;
                } else if (diffMinutes !== undefined && diffMinutes > measurementWarningTimeoutMinutes) {
                    const label = diffLabel ? `${diffLabel} назад` : 'достаточно давно';
                    measurementWarningHtml = `<i class="bi bi-exclamation-triangle-fill text-danger me-2" title="Замер не проводился ${escapeHtml(label.replace('назад', '').trim())}" style="font-size: 1.2rem;"></i>`;
                }

                if (session.weighing_warning) {
                    const weighingLabel = session.last_weighing_diff_label || '';
                    const weighingTooltip = weighingLabel && weighingLabel !== 'ещё не проводился'
                        ? `Последняя навеска ${escapeHtml(weighingLabel)} назад`
                        : 'Навеска ещё не проводилась';
                    weighingWarningHtml = `<i class="bi bi-exclamation-circle-fill text-warning me-2" title="${weighingTooltip}" style="font-size: 1.1rem;"></i>`;
                }
            }

            let avgWeightHtml = '';
            if (session && session.avg_fish_weight !== undefined && session.avg_fish_weight !== null) {
                const avgWeight = formatNumber(session.avg_fish_weight, 3);
                if (avgWeight) {
                    avgWeightHtml = ` <span class="badge bg-primary pool-block-avg-weight-badge">${avgWeight} кг</span>`;
                }
            }

            let sessionInfo = '';
            if (session && session.start_mass !== undefined && session.start_fish_count !== undefined) {
                const mass = formatNumber(session.start_mass, 2);
                const count = formatInteger(session.start_fish_count);

                let durationInfo = '';
                if (session.start_date) {
                    const startDate = new Date(session.start_date);
                    const now = new Date();
                    const daysDiff = Math.floor((now - startDate) / (1000 * 60 * 60 * 24));
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
                    durationInfo = `<div class="pool-block-session-duration">${daysDiff} ${daysText}</div>`;
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
            const sessionLink = session && session.id ? `${baseUrl}session-details?id=${session.id}` : null;
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
            const contentRowHtml = measurementsHtml ? `<div class="pool-content-row">${measurementsHtml}</div>` : '';
            const sessionTitle = sessionLink
                ? `<a href="${sessionLink}" class="text-decoration-none">${escapeHtml(session.name)}</a>`
                : escapeHtml(session && session.name ? session.name : 'ПУСТО');

            const blockHtml = `
                <div class="pool-block ${emptyClass}" data-pool-id="${pool.id}">
                    <div class="pool-block-header">
                        <div class="pool-block-title">
                            ${measurementWarningHtml}${weighingWarningHtml}${sessionTitle}${avgWeightHtml}
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

        const tempColorClass = tempStratum === 'good' ? 'text-success' : (tempStratum === 'acceptable' ? 'text-warning' : 'text-danger');
        const oxygenColorClass = oxygenStratum === 'good' ? 'text-success' : (oxygenStratum === 'acceptable' ? 'text-warning' : 'text-danger');

        let tempArrowHtml = '';
        if (tempTrend && tempTrend !== 'same') {
            const arrowIcon = 'bi-triangle-fill';
            const arrowStyle = tempTrend === 'up' ? '' : 'transform: rotate(180deg);';
            const arrowColorClass = tempTrendDirection === 'improving' ? 'text-success' : (tempTrendDirection === 'worsening' ? 'text-danger' : 'text-muted');
            tempArrowHtml = `<i class="bi ${arrowIcon} ${arrowColorClass} ms-2" style="font-size: 1.2rem; ${arrowStyle}"></i>`;
        }

        let oxygenArrowHtml = '';
        if (oxygenTrend && oxygenTrend !== 'same') {
            const arrowIcon = 'bi-triangle-fill';
            const arrowStyle = oxygenTrend === 'up' ? '' : 'transform: rotate(180deg);';
            const arrowColorClass = oxygenTrendDirection === 'improving' ? 'text-success' : (oxygenTrendDirection === 'worsening' ? 'text-danger' : 'text-muted');
            oxygenArrowHtml = `<i class="bi ${arrowIcon} ${arrowColorClass} ms-2" style="font-size: 1.2rem; ${arrowStyle}"></i>`;
        }

        let html = '<div class="pool-measurements">';

        if (temp !== null && temp !== undefined) {
            const tempFormatted = formatNumber(temp, 1);
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
            const oxygenFormatted = formatNumber(oxygen, 1);
            html += `
                <div class="pool-measurement-item">
                    <div class="pool-measurement-value ${oxygenColorClass}">
                        O<sub>2</sub> ${oxygenFormatted}
                        ${oxygenArrowHtml}
                    </div>
                </div>
            `;
        }

        const feedingHtml = renderFeedingPlanInline(session.feeding_plan);
        if (feedingHtml) {
            html += feedingHtml;
        }

        html += '</div>';

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
            const mortalityCount = parseInt(mortality.total_count || 0, 10);
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

    function renderFeedingPlanInline(plan) {
        if (!plan || plan.per_feeding === null || plan.per_feeding === undefined) {
            return '';
        }

        const feedName = escapeHtml(plan.feed_name || 'Корм');
        const strategyLabel = escapeHtml(plan.strategy_label || feedingStrategyLabels[plan.strategy] || 'Норма');
        const amount = formatNumber(plan.per_feeding, 2);
        if (!amount) {
            return '';
        }

        return `
            <div class="pool-feeding-inline">
                <i class="bi bi-bowl-hot"></i>
                <div class="pool-feeding-text">
                    <div>${feedName}: ${strategyLabel}</div>
                    <div class="pool-feeding-amount">${amount} кг</div>
                </div>
            </div>
        `;
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

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function formatNumber(value, fractionDigits) {
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

    function loadMeasurementWarningTimeout() {
        $.ajax({
            url: `${baseUrl}api/settings.php?action=get&key=measurement_warning_timeout_minutes`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    measurementWarningTimeoutMinutes = parseInt(response.value, 10) || 60;
                }
            },
            error: function(xhr, status, error) {
                console.error('loadMeasurementWarningTimeout error:', status, error, xhr.responseText);
            }
        });
    }

    function loadPoolsList() {
        $.ajax({
            url: `${baseUrl}api/measurements.php?action=get_pools`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    poolsList = response.data || [];
                }
            },
            error: function(xhr, status, error) {
                console.error('loadPoolsList error:', status, error, xhr.responseText);
            }
        });
    }

    function loadCounterpartiesList() {
        $.ajax({
            url: `${baseUrl}api/counterparties.php?action=list`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    counterpartiesList = response.data || [];
                }
            },
            error: function(xhr, status, error) {
                console.error('loadCounterpartiesList error:', status, error, xhr.responseText);
            }
        });
    }

    function openMeasurementModal(poolId) {
        $('#measurementModalTitle').text('Добавить замер');
        $('#measurementForm')[0].reset();
        $('#measurementId').val('');
        $('#measurementPool').prop('disabled', false);

        const select = $('#measurementPool');
        select.empty().append('<option value="">Выберите бассейн</option>');
        poolsList.forEach(function(pool) {
            const selected = (poolId && pool.id === poolId) ? 'selected' : '';
            select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
        });

        if (isAdmin) {
            $('#measurementDateTimeField').show();
            $('#measurementDateTime').prop('required', true);
            $('#measurementDateTime').val(toDateTimeLocalValue(new Date()));
        } else {
            $('#measurementDateTimeField').hide();
            $('#measurementDateTime').prop('required', false);
        }

        if (poolId) {
            $('#currentMeasurementPoolId').val(poolId);
            $('#measurementPool').val(poolId);
        }

        const modal = getModalInstance('measurementModal');
        if (modal) {
            modal.show();
        }
    }

    function openMortalityModal(poolId) {
        $('#mortalityModalTitle').text('Зарегистрировать падеж');
        $('#mortalityForm')[0].reset();
        $('#mortalityId').val('');
        $('#mortalityPool').prop('disabled', false);

        const select = $('#mortalityPool');
        select.empty().append('<option value="">Выберите бассейн</option>');
        poolsList.forEach(function(pool) {
            const selected = (poolId && pool.id === poolId) ? 'selected' : '';
            select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
        });

        if (isAdmin) {
            $('#mortalityDateTimeField').show();
            $('#mortalityDateTime').prop('required', true);
            $('#mortalityDateTime').val(toDateTimeLocalValue(new Date()));
        } else {
            $('#mortalityDateTimeField').hide();
            $('#mortalityDateTime').prop('required', false);
        }

        if (poolId) {
            $('#currentMortalityPoolId').val(poolId);
            $('#mortalityPool').val(poolId);
        }

        const modal = getModalInstance('mortalityModal');
        if (modal) {
            modal.show();
        }
    }

    function openHarvestModal(poolId) {
        $('#harvestModalTitle').text('Добавить отбор');
        $('#harvestForm')[0].reset();
        $('#harvestId').val('');
        $('#harvestPool').prop('disabled', false);

        const select = $('#harvestPool');
        select.empty().append('<option value="">Выберите бассейн</option>');
        poolsList.forEach(function(pool) {
            const selected = (poolId && pool.id === poolId) ? 'selected' : '';
            select.append(`<option value="${pool.id}" ${selected}>${escapeHtml(pool.name)}</option>`);
        });

        if (isAdmin) {
            $('#harvestDateTimeField').show();
            $('#harvestDateTime').prop('required', true);
            $('#harvestDateTime').val(toDateTimeLocalValue(new Date()));
        } else {
            $('#harvestDateTimeField').hide();
            $('#harvestDateTime').prop('required', false);
        }

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

        const modal = getModalInstance('harvestModal');
        if (modal) {
            modal.show();
        }
    }

    function toDateTimeLocalValue(date) {
        const pad = (value) => String(value).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    function toggleMortalitySavingState(isSaving) {
        const btn = $('#mortalityModal .modal-footer .btn-primary');
        if (!btn.length) {
            return;
        }

        if (isSaving) {
            if (!btn.data('original-html')) {
                btn.data('original-html', btn.html());
            }
            btn.prop('disabled', true);
            btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
        } else {
            const original = btn.data('original-html');
            btn.html(original || 'Сохранить');
            btn.prop('disabled', false);
            btn.removeData('original-html');
        }
    }

    function saveMeasurement() {
        const form = $('#measurementForm')[0];

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const poolId = $('#currentMeasurementPoolId').val() || $('#measurementPool').val();
        const formData = {
            pool_id: parseInt(poolId, 10),
            temperature: parseFloat($('#measurementTemperature').val()),
            oxygen: parseFloat($('#measurementOxygen').val())
        };

        if (isAdmin && $('#measurementDateTime').val()) {
            formData.measured_at = $('#measurementDateTime').val().replace('T', ' ');
        }

        $.ajax({
            url: `${baseUrl}api/measurements.php?action=create`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Замер сохранён');
                    const modal = getModalInstance('measurementModal');
                    if (modal) {
                        modal.hide();
                    }
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить замер');
                }
            },
            error: function(xhr, status, error) {
                console.error('saveMeasurement error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при сохранении замера');
            }
        });
    }

    function saveMortality() {
        const form = $('#mortalityForm')[0];

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const poolId = $('#currentMortalityPoolId').val() || $('#mortalityPool').val();
        const formData = {
            pool_id: parseInt(poolId, 10),
            weight: parseFloat($('#mortalityWeight').val()),
            fish_count: parseInt($('#mortalityFishCount').val(), 10)
        };

        if (isAdmin && $('#mortalityDateTime').val()) {
            formData.recorded_at = $('#mortalityDateTime').val().replace('T', ' ');
        }

        toggleMortalitySavingState(true);

        $.ajax({
            url: `${baseUrl}api/mortality.php?action=create`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Падеж сохранён');
                    const modal = getModalInstance('mortalityModal');
                    if (modal) {
                        modal.hide();
                    }
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить падеж');
                }
            },
            error: function(xhr, status, error) {
                console.error('saveMortality error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при сохранении падежа');
            }
        }).always(function() {
            toggleMortalitySavingState(false);
        });
    }

    function saveHarvest() {
        const form = $('#harvestForm')[0];

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const poolId = $('#currentHarvestPoolId').val() || $('#harvestPool').val();
        const formData = {
            pool_id: parseInt(poolId, 10),
            weight: parseFloat($('#harvestWeight').val()),
            fish_count: parseInt($('#harvestFishCount').val(), 10)
        };
        const counterpartyValue = $('#harvestCounterparty').val();
        if (counterpartyValue) {
            formData.counterparty_id = parseInt(counterpartyValue, 10);
        }

        if (isAdmin && $('#harvestDateTime').val()) {
            formData.recorded_at = $('#harvestDateTime').val().replace('T', ' ');
        }

        $.ajax({
            url: `${baseUrl}api/harvests.php?action=create`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Отбор сохранён');
                    const modal = getModalInstance('harvestModal');
                    if (modal) {
                        modal.hide();
                    }
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить отбор');
                }
            },
            error: function(xhr, status, error) {
                console.error('saveHarvest error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при сохранении отбора');
            }
        });
    }

    window.openMeasurementModal = openMeasurementModal;
    window.openMortalityModal = openMortalityModal;
    window.openHarvestModal = openHarvestModal;
    window.saveMeasurement = saveMeasurement;
    window.saveMortality = saveMortality;
    window.saveHarvest = saveHarvest;
})();
