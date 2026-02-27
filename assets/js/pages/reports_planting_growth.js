(function() {
    'use strict';

    const form = document.getElementById('plantingGrowthFilters');
    const alertBox = document.getElementById('plantingGrowthAlert');
    const summaryBlock = document.getElementById('plantingGrowthSummary');
    const card = document.getElementById('plantingGrowthCard');
    const titleEl = document.getElementById('plantingGrowthTitle');
    const subtitleEl = document.getElementById('plantingGrowthSubtitle');
    const chartCanvas = document.getElementById('plantingGrowthChart');

    let chartInstance = null;

    if (!form || !chartCanvas) {
        return;
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        loadGrowthData();
    });

    function showAlert(message, type = 'info') {
        if (!alertBox) return;
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.style.display = 'block';
    }

    function hideAlert() {
        if (!alertBox) return;
        alertBox.style.display = 'none';
    }

    function loadGrowthData() {
        hideAlert();
        card.style.display = 'none';
        if (summaryBlock) summaryBlock.style.display = 'none';

        const formData = new FormData(form);
        const plantingId = formData.get('planting_id');

        if (!plantingId) {
            showAlert('Пожалуйста, выберите посадку.', 'warning');
            return;
        }

        const params = new URLSearchParams();
        params.append('planting_id', plantingId);

        const dateFrom = formData.get('date_from');
        const dateTo = formData.get('date_to');
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);

        showAlert('Загрузка данных...', 'info');

        fetch((window.BASE_URL || '/') + 'api/reports.php?action=planting_growth&' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (!data || !data.success) {
                    showAlert(data && data.message ? data.message : 'Ошибка при загрузке данных отчета', 'danger');
                    return;
                }
                const hasPoints = Array.isArray(data.data.points) && data.data.points.length > 0;
                const hasInitial = data.data.initial && data.data.initial.avg_weight_g != null;
                const hasPlantingPoint = data.data.planting_point && data.data.planting_point.avg_weight_g != null;
                const hasChartData = hasPoints || hasInitial || hasPlantingPoint;
                const hasSummary = data.data.summary != null;
                if (!hasSummary && !hasChartData) {
                    showAlert('Для выбранной посадки нет данных (нет данных посадки, сессий и навесок).', 'info');
                    return;
                }
                hideAlert();
                if (hasSummary) {
                    fillSummary(data.data.summary);
                    if (summaryBlock) summaryBlock.style.display = 'block';
                }
                if (hasChartData) {
                    renderChart(data.data);
                    card.style.display = 'block';
                }
            })
            .catch(err => {
                console.error('Ошибка при запросе отчета по росту посадок:', err);
                showAlert('Произошла ошибка при загрузке отчета.', 'danger');
            });
    }

    function fillSummary(s) {
        const daysValEl = document.getElementById('plantingGrowthDaysValue');
        if (daysValEl) {
            const days = s.days_since_planting != null ? Number(s.days_since_planting) : null;
            daysValEl.textContent = (days !== null && !Number.isNaN(days)) ? formatNumber(days, 0) : '—';
        }
        setSummaryValue('summaryCurrentBiomass', s.current_biomass_kg != null ? formatNumber(s.current_biomass_kg, 2) + ' кг' : '—');
        setSummaryValue('summaryCurrentAvg', s.current_avg_weight_g != null ? formatNumber(s.current_avg_weight_g, 1) + ' г/шт' : '—');
        setSummaryValue('summaryInitialWeight', s.initial_weight_kg != null ? formatNumber(s.initial_weight_kg, 2) + ' кг' : '—');
        setSummaryValue('summaryInitialAvg', s.initial_avg_weight_g != null ? formatNumber(s.initial_avg_weight_g, 1) + ' г/шт' : '—');
        setSummaryValue('summaryHarvest', (s.total_harvest_kg != null || s.total_harvest_count != null)
            ? formatNumber(s.total_harvest_kg || 0, 2) + ' кг / ' + formatNumber(s.total_harvest_count || 0, 0) + ' шт'
            : '— кг / — шт');
        setSummaryValue('summaryMortality', (s.total_mortality_kg != null || s.total_mortality_count != null)
            ? formatNumber(s.total_mortality_kg || 0, 2) + ' кг / ' + formatNumber(s.total_mortality_count || 0, 0) + ' шт'
            : '— кг / — шт');
    }

    function setSummaryValue(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function renderChart(payload) {
        const points = payload.points || [];
        const initial = payload.initial || null;
        const plantingPoint = payload.planting_point || null;
        const planting = payload.planting || {};
        const filters = payload.filters || {};

        // Объединяем: данные посадки → первая сессия → навески, сортируем по дате
        let allPoints = [];
        if (plantingPoint && plantingPoint.avg_weight_g != null) {
            allPoints.push(plantingPoint);
        }
        if (initial && initial.avg_weight_g != null) {
            allPoints.push(initial);
        }
        allPoints = allPoints.concat(points);
        allPoints.sort(function(a, b) {
            return new Date(a.recorded_at) - new Date(b.recorded_at);
        });

        const labels = [];
        const values = [];
        allPoints.forEach(function(point) {
            const dateLabel = formatDate(point.recorded_at);
            let suffix = '';
            if (point.is_planting) {
                suffix = ' (посадка)';
            } else if (point.is_initial) {
                suffix = ' (нач.)';
            }
            labels.push(dateLabel + suffix);
            values.push(point.avg_weight_g !== null && point.avg_weight_g !== undefined
                ? point.avg_weight_g
                : null);
        });

        // Заголовок и подпись
        const name = planting.name || 'Посадка';
        const breed = planting.fish_breed ? `, ${planting.fish_breed}` : '';
        titleEl.textContent = `${name}${breed}`;

        const parts = [];
        if (filters.date_from || filters.date_to) {
            const from = filters.date_from ? formatDate(filters.date_from) : '';
            const to = filters.date_to ? formatDate(filters.date_to) : '';
            parts.push(`Период: ${from || 'начало'} — ${to || 'по настоящее время'}`);
        }
        parts.push('По оси X — даты, по оси Y — средняя навеска, г/шт');
        subtitleEl.textContent = parts.join(' · ');

        const ctx = chartCanvas.getContext('2d');

        if (chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Средняя навеска (г/шт)',
                    data: values,
                    backgroundColor: 'rgba(255, 193, 7, 0.85)',
                    borderColor: 'rgb(255, 193, 7)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed.y;
                                if (value === null || value === undefined) {
                                    return 'нет данных';
                                }
                                return 'Средняя навеска: ' + formatNumber(value, 1) + ' г';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Дата'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Средняя навеска, г/шт'
                        },
                        beginAtZero: true
                    }
                }
            }
        });

        if (card) card.style.display = 'block';
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        if (Number.isNaN(date.getTime())) {
            return dateString;
        }
        return date.toLocaleDateString('ru-RU', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    }

    function formatNumber(value, decimals) {
        const num = Number(value);
        if (Number.isNaN(num)) return '-';
        return num.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
})();

