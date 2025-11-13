(function() {
    'use strict';

    if (window.__sessionDetailsPageInitialized) {
        return;
    }
    window.__sessionDetailsPageInitialized = true;

    const config = window.sessionDetailsConfig || {};
    const baseUrl = config.baseUrl || '';
    const sessionId = config.sessionId || null;
    const isAdmin = Boolean(config.isAdmin);

    const chartInstances = {};

    document.addEventListener('DOMContentLoaded', function() {
        if (!sessionId) {
            showAlert('danger', 'ID сессии не указан');
            return;
        }
        loadSessionDetails();
    });

    function loadSessionDetails() {
        $.ajax({
            url: `${baseUrl}api/session_details.php?id=${sessionId}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displaySessionData(response.data || {});
                    $('#loadingIndicator').hide();
                    $('#sessionContent').show();
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить данные сессии');
                    $('#loadingIndicator').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Session details error:', status, error, xhr.responseText);
                let errorMessage = 'Ошибка при загрузке данных сессии';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        const parsed = JSON.parse(xhr.responseText);
                        if (parsed && parsed.message) {
                            errorMessage = parsed.message;
                        }
                    } catch (_e) {
                        errorMessage = `Ошибка: ${error}`;
                    }
                }
                showAlert('danger', errorMessage);
                $('#loadingIndicator').hide();
            }
        });
    }

    function displaySessionData(data) {
        const session = data.session || {};
        $('#sessionName').text(session.name || '—');
        $('#poolName').text(session.pool_name || '—');
        $('#plantingName').text(session.planting_name || '—');
        $('#fishBreed').text(session.fish_breed || '—');
        $('#startDate').text(session.start_date ? formatDate(session.start_date) : '—');
        $('#startMass').text(session.start_mass ? formatNumber(session.start_mass, 2) : '—');
        $('#startFishCount').text(session.start_fish_count ? formatNumber(session.start_fish_count, 0) : '—');
        $('#previousFcr').text(session.previous_fcr ? formatNumber(session.previous_fcr, 2) : '—');

        if (isAdmin) {
            $('#endMass').text(session.end_mass ? `${formatNumber(session.end_mass, 2)} кг` : '—');
            $('#feedAmount').text(session.feed_amount ? `${formatNumber(session.feed_amount, 2)} кг` : '—');
            $('#fcr').text(session.fcr ? formatNumber(session.fcr, 2) : '—');
        }

        const statusBadge = session.is_completed
            ? '<span class="badge bg-secondary">Завершена</span>'
            : '<span class="badge bg-success">Активна</span>';
        $('#sessionStatus').html(statusBadge);

        buildTemperatureChart(data.measurements || []);
        buildOxygenChart(data.measurements || []);
        buildMortalityChart(data.mortality || []);
        buildCounterpartyHarvestsChart(data.harvests || []);
        buildHarvestsChart(data.harvests || []);
        buildWeighingsChart(data.weighings || []);
    }

    function buildTemperatureChart(measurements) {
        const ctx = getChartContext('temperatureChart');
        if (!ctx) {
            return;
        }

        const labels = measurements.map(m => formatDateTime(m.measured_at));
        const data = measurements.map(m => parseFloat(m.temperature));
        const gradient = createVerticalGradient(ctx, 'rgba(255, 99, 132, 0.5)', 'rgba(255, 99, 132, 0)');

        renderChart('temperatureChart', ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Температура (°C)',
                    data,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: defaultLineOptions('Температура (°C)', { min: 5, max: 25 })
        });
    }

    function buildOxygenChart(measurements) {
        const ctx = getChartContext('oxygenChart');
        if (!ctx) {
            return;
        }

        const labels = measurements.map(m => formatDateTime(m.measured_at));
        const data = measurements.map(m => parseFloat(m.oxygen));
        const gradient = createVerticalGradient(ctx, 'rgba(54, 162, 235, 0.5)', 'rgba(54, 162, 235, 0)');

        renderChart('oxygenChart', ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'O₂',
                    data,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: defaultLineOptions('O₂', { min: 5, max: 20 })
        });
    }

    function buildMortalityChart(mortality) {
        const ctx = getChartContext('mortalityChart');
        if (!ctx) {
            return;
        }

        const labels = mortality.map(m => formatDate(m.day || m.day_label || m.recorded_at));
        const countData = mortality.map(m => parseInt(m.total_count ?? m.fish_count ?? 0, 10));
        const weightData = mortality.map(m => parseFloat(m.total_weight ?? m.weight ?? 0));
        const gradient = createVerticalGradient(ctx, 'rgba(220, 53, 69, 0.5)', 'rgba(220, 53, 69, 0)');

        renderChart('mortalityChart', ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Количество (шт)',
                        data: countData,
                        backgroundColor: gradient,
                        borderColor: 'rgb(220, 53, 69)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Вес (кг)',
                        data: weightData,
                        type: 'line',
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Количество (шт)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Вес (кг)' }
                    }
                },
                plugins: { legend: { display: true, position: 'top' } }
            }
        });
    }

    function buildCounterpartyHarvestsChart(harvests) {
        const ctx = getChartContext('counterpartyHarvestsChart');
        if (!ctx) {
            return;
        }

        const counterpartyMap = {};
        harvests.forEach(function(item) {
            const key = item.counterparty_name || 'Не указан';
            if (!counterpartyMap[key]) {
                counterpartyMap[key] = { weight: 0, count: 0 };
            }
            counterpartyMap[key].weight += parseFloat(item.weight || 0);
            counterpartyMap[key].count += parseInt(item.fish_count || 0, 10);
        });

        const labels = Object.keys(counterpartyMap);
        const weightData = labels.map(label => parseFloat(counterpartyMap[label].weight || 0));
        const countData = labels.map(label => parseInt(counterpartyMap[label].count || 0, 10));

        renderChart('counterpartyHarvestsChart', ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Вес (кг)',
                        data: weightData,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgb(75, 192, 192)',
                        borderWidth: 1
                    },
                    {
                        label: 'Количество (шт)',
                        data: countData,
                        backgroundColor: 'rgba(153, 102, 255, 0.6)',
                        borderColor: 'rgb(153, 102, 255)',
                        borderWidth: 1
                    }
                ]
            },
            options: defaultBarOptions()
        });
    }

    function buildHarvestsChart(harvests) {
        const ctx = getChartContext('harvestsChart');
        if (!ctx) {
            return;
        }

        const aggregated = {};
        harvests.forEach(function(item) {
            const date = formatDate(item.recorded_at);
            if (!aggregated[date]) {
                aggregated[date] = { weight: 0, count: 0 };
            }
            aggregated[date].weight += parseFloat(item.weight || 0);
            aggregated[date].count += parseInt(item.fish_count || 0, 10);
        });

        const labels = Object.keys(aggregated);
        const weightData = labels.map(label => parseFloat(aggregated[label].weight || 0));
        const countData = labels.map(label => parseInt(aggregated[label].count || 0, 10));
        const gradient = createVerticalGradient(ctx, 'rgba(75, 192, 192, 0.5)', 'rgba(75, 192, 192, 0)');

        renderChart('harvestsChart', ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Вес (кг)',
                        data: weightData,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Количество (шт)',
                        data: countData,
                        borderColor: 'rgb(153, 102, 255)',
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        fill: false,
                        tension: 0.4
                    }
                ]
            },
            options: defaultLineOptions('Показатели')
        });
    }

    function buildWeighingsChart(weighings) {
        const ctx = getChartContext('weighingsChart');
        if (!ctx) {
            return;
        }

        const labels = weighings.map(w => formatDate(w.recorded_at));
        const avgWeightData = weighings.map(w => parseFloat(w.avg_weight || 0));
        const gradient = createVerticalGradient(ctx, 'rgba(255, 205, 86, 0.5)', 'rgba(255, 205, 86, 0)');

        renderChart('weighingsChart', ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Средний вес (кг)',
                    data: avgWeightData,
                    borderColor: 'rgb(255, 205, 86)',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: defaultLineOptions('Средний вес (кг)')
        });
    }

    function getChartContext(canvasId) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            return null;
        }
        return canvas.getContext('2d');
    }

    function renderChart(key, ctx, config) {
        if (chartInstances[key]) {
            chartInstances[key].destroy();
        }
        chartInstances[key] = new Chart(ctx, config);
    }

    function createVerticalGradient(ctx, fromColor, toColor) {
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, fromColor);
        gradient.addColorStop(1, toColor);
        return gradient;
    }

    function defaultLineOptions(yLabel = '', range = {}) {
        return {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    title: yLabel ? { display: true, text: yLabel } : undefined,
                    min: range.min,
                    max: range.max
                }
            },
            plugins: { legend: { display: true, position: 'top' } }
        };
    }

    function defaultBarOptions() {
        return {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                x: { stacked: false },
                y: { beginAtZero: true }
            },
            plugins: { legend: { display: true, position: 'top' } }
        };
    }

    function showAlert(type, message) {
        $('#alert-container').html(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
    }

    function formatDate(value) {
        if (!value) {
            return '';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleDateString('ru-RU');
    }

    function formatDateTime(value) {
        if (!value) {
            return '';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
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
})();
