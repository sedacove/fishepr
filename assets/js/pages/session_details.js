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

        buildGrowthForecastChart(session, data.weighings || [], data.growth_forecast || {});
        buildTemperatureChart(data.measurements || []);
        buildOxygenChart(data.measurements || []);
        buildMortalityChart(data.mortality || []);
        buildCounterpartyHarvestsChart(data.harvests || []);
        buildHarvestsChart(data.harvests || []);
        buildWeighingsChart(data.weighings || []);
    }

    /**
     * Вычисляет вес по идеальной кривой роста
     * W(t) = max_weight / (1 + exp(-coefficient * (t - inflection_point)))
     * 
     * @param {number} days Возраст рыбы в днях
     * @param {object} params Параметры формулы: {max_weight, coefficient, inflection_point}
     * @returns {number} Вес в граммах
     */
    function calculateIdealWeight(days, params) {
        const maxWeight = params.max_weight || 2500;
        const coefficient = params.coefficient || 0.015;
        const inflectionPoint = params.inflection_point || 220;
        
        // W(t) = max_weight / (1 + exp(-coefficient * (t - inflection_point)))
        const expValue = Math.exp(-coefficient * (days - inflectionPoint));
        return maxWeight / (1 + expValue);
    }

    /**
     * Находит возраст рыбы (в днях) по заданному весу на идеальной кривой
     * Использует бинарный поиск для нахождения обратного значения
     * 
     * @param {number} targetWeightGrams Целевой вес в граммах
     * @param {object} params Параметры формулы: {max_weight, coefficient, inflection_point}
     * @returns {number} Возраст в днях
     */
    function findAgeByWeight(targetWeightGrams, params) {
        const maxWeight = params.max_weight || 2500;
        const maxAge = 1095; // Максимальный возраст для поиска (3 года)
        
        if (targetWeightGrams <= 0) {
            return 0;
        }
        if (targetWeightGrams >= maxWeight) {
            return maxAge;
        }

        // Бинарный поиск
        let left = 0;
        let right = maxAge;
        const tolerance = 0.1; // Точность в граммах

        while (right - left > 0.1) {
            const mid = (left + right) / 2;
            const weight = calculateIdealWeight(mid, params);
            
            if (Math.abs(weight - targetWeightGrams) < tolerance) {
                return mid;
            }
            
            if (weight < targetWeightGrams) {
                left = mid;
            } else {
                right = mid;
            }
        }

        return (left + right) / 2;
    }

    function buildGrowthForecastChart(session, weighings, growthForecastParams) {
        const ctx = getChartContext('growthForecastChart');
        if (!ctx) {
            return;
        }

        // Параметры формулы из настроек (значения по умолчанию)
        const params = {
            max_weight: growthForecastParams.max_weight || 2500,
            coefficient: growthForecastParams.coefficient || 0.015,
            inflection_point: growthForecastParams.inflection_point || 220
        };

        // Формула: W(t) = max_weight / (1 + exp(-coefficient * (t - inflection_point)))
        // t от 0 до 1095 дней (3 года)
        const maxDays = 1095; // 3 года
        const step = 10; // Шаг в днях для построения графика
        const labels = [];
        const weights = [];

        for (let t = 0; t <= maxDays; t += step) {
            labels.push(t);
            const weight = calculateIdealWeight(t, params);
            weights.push(weight);
        }

        // Добавляем последнюю точку точно на 1095 день
        if (labels[labels.length - 1] !== maxDays) {
            labels.push(maxDays);
            const weight = calculateIdealWeight(maxDays, params);
            weights.push(weight);
        }

        // Преобразуем данные в формат {x, y} для числовой шкалы
        const idealDataPoints = labels.map((day, index) => ({
            x: day,
            y: weights[index]
        }));

        // Строим реальный график навесок
        const realDataPoints = [];
        let initialAge = 0;

        if (session && session.start_mass && session.start_fish_count && session.start_fish_count > 0) {
            // Вычисляем средний начальный вес одной рыбы в граммах
            const startWeightGrams = (session.start_mass * 1000) / session.start_fish_count;
            
            // Находим возраст рыбы на момент старта сессии на идеальной кривой
            initialAge = findAgeByWeight(startWeightGrams, params);
            
            // Добавляем начальную точку
            if (session.start_date) {
                realDataPoints.push({
                    x: initialAge,
                    y: startWeightGrams,
                    label: 'Начало сессии'
                });
            }
        }

        // Добавляем точки реальных навесок
        if (weighings && weighings.length > 0 && session && session.start_date) {
            const startDate = new Date(session.start_date);
            
            weighings.forEach(function(weighing) {
                // Используем avg_weight если есть, иначе вычисляем из weight и fish_count
                let avgWeightGrams = null;
                if (weighing.avg_weight !== null && weighing.avg_weight !== undefined) {
                    avgWeightGrams = weighing.avg_weight * 1000; // Конвертируем кг в граммы
                } else if (weighing.weight && weighing.fish_count && weighing.fish_count > 0) {
                    avgWeightGrams = (weighing.weight * 1000) / weighing.fish_count;
                }
                
                if (avgWeightGrams === null || avgWeightGrams <= 0) {
                    return;
                }
                
                // Вычисляем возраст рыбы: начальный возраст + дни с начала сессии
                const weighingDate = new Date(weighing.recorded_at);
                if (isNaN(weighingDate.getTime())) {
                    return; // Некорректная дата
                }
                
                const daysSinceStart = Math.floor((weighingDate - startDate) / (1000 * 60 * 60 * 24));
                const fishAge = initialAge + daysSinceStart;
                
                realDataPoints.push({
                    x: fishAge,
                    y: avgWeightGrams,
                    label: formatDateTime(weighing.recorded_at)
                });
            });
        }

        // Сортируем реальные точки по возрасту
        realDataPoints.sort(function(a, b) {
            return a.x - b.x;
        });

        const datasets = [{
            label: 'Идеальная кривая роста',
            data: idealDataPoints,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 0,
            pointHoverRadius: 4
        }];

        // Добавляем реальный график, если есть данные
        if (realDataPoints.length > 0) {
            datasets.push({
                label: 'Реальные навески',
                data: realDataPoints,
                borderColor: 'rgb(255, 206, 86)',
                backgroundColor: 'rgba(255, 206, 86, 0.2)',
                borderWidth: 2,
                fill: false,
                tension: 0.2,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: 'rgb(255, 206, 86)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            });
        }

        renderChart('growthForecastChart', ctx, {
            type: 'line',
            data: {
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 3,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                // При использовании числовой шкалы parsed.x содержит значение дня
                                const days = Math.round(context.parsed.x);
                                const weight = context.parsed.y;
                                const weightKg = (weight / 1000).toFixed(2);
                                const datasetLabel = context.dataset.label || '';
                                
                                // Для реальных навесок показываем дополнительную информацию
                                if (datasetLabel === 'Реальные навески' && context.raw.label) {
                                    return `${datasetLabel}: День ${days}, ${weight.toFixed(1)} г (${weightKg} кг) - ${context.raw.label}`;
                                }
                                
                                return `${datasetLabel}: День ${days}, ${weight.toFixed(1)} г (${weightKg} кг)`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        title: {
                            display: true,
                            text: 'Возраст (дни)'
                        },
                        min: 0,
                        max: maxDays,
                        ticks: {
                            stepSize: 50,
                            maxTicksLimit: 25,
                            callback: function(value) {
                                const days = Math.round(value);
                                if (days < 0 || days > maxDays) {
                                    return '';
                                }
                                // Показываем каждые 50 дней или на круглых числах
                                if (days === 0) {
                                    return '0';
                                }
                                if (days % 365 === 0) {
                                    const years = days / 365;
                                    return `${days} дн (${years} год${years > 1 ? 'а' : ''})`;
                                }
                                if (days % 100 === 0) {
                                    return days;
                                }
                                if (days % 50 === 0) {
                                    return days;
                                }
                                return '';
                            }
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Вес (г)'
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(0);
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                }
            }
        });
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
