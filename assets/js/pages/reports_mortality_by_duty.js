(function () {
    'use strict';

    const form = document.getElementById('mortalityByDutyFilters');
    const alertBox = document.getElementById('mortalityByDutyAlert');
    const results = document.getElementById('mortalityByDutyResults');
    const tbody = document.getElementById('mortalityByDutyBody');
    const totalShiftsEl = document.getElementById('mortalityByDutyTotalShifts');
    const totalAvgPerShiftEl = document.getElementById('mortalityByDutyTotalAvgPerShift');
    const totalKgEl = document.getElementById('mortalityByDutyTotalKg');
    const totalCountEl = document.getElementById('mortalityByDutyTotalCount');
    const chartCanvas = document.getElementById('mortalityByDutyChart');
    const metricInputs = document.querySelectorAll('input[name="mortalityMetric"]');
    let chart = null;
    let lastPayload = null;
    let currentMetric = 'count';

    if (!form || !tbody || !chartCanvas) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadReport();
    });

    metricInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            currentMetric = getMetricValue();
            if (lastPayload) {
                renderChart(lastPayload);
            }
        });
    });

    function getMetricValue() {
        const checked = document.querySelector('input[name="mortalityMetric"]:checked');
        return checked ? checked.value : 'count';
    }

    function showAlert(message, type) {
        alertBox.className = 'alert alert-' + (type || 'info');
        alertBox.textContent = message;
        alertBox.style.display = 'block';
    }

    function hideAlert() {
        alertBox.style.display = 'none';
    }

    function escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function fmtNum(value, digits) {
        return Number(value || 0).toFixed(digits).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function loadReport() {
        hideAlert();
        results.style.display = 'none';

        const params = new URLSearchParams();
        const dateFrom = document.getElementById('mortalityDateFrom').value.trim();
        const dateTo = document.getElementById('mortalityDateTo').value.trim();
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);

        showAlert('Загрузка...', 'info');
        fetch((window.BASE_URL || '/') + 'api/reports.php?action=mortality_by_duty&' + params.toString())
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success) {
                    showAlert((data && data.message) ? data.message : 'Ошибка загрузки отчета', 'danger');
                    return;
                }
                currentMetric = getMetricValue();
                render(data.data || { items: [], totals: {} });
            })
            .catch(() => {
                showAlert('Ошибка сети при загрузке отчета', 'danger');
            });
    }

    function render(payload) {
        const items = Array.isArray(payload.items) ? payload.items : [];
        const totals = payload.totals || {};

        if (!items.length) {
            showAlert('Нет данных за выбранный период', 'info');
            return;
        }
        hideAlert();
        results.style.display = 'block';
        lastPayload = payload;

        tbody.innerHTML = '';
        items.forEach(row => {
            const deletedBadge = row.is_deleted ? ' <span class="badge bg-secondary">удалён</span>' : '';
            const avgPerShift = (row.shifts_count && Number(row.shifts_count) > 0)
                ? (fmtNum(row.avg_weight_kg_per_shift, 2) + ' кг / ' + fmtNum(row.avg_fish_count_per_shift, 1) + ' шт')
                : '—';
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + escapeHtml(row.duty_name) + deletedBadge + '</td>' +
                '<td class="text-end">' + fmtNum(row.shifts_count || 0, 0) + '</td>' +
                '<td class="text-end">' + avgPerShift + '</td>' +
                '<td class="text-end">' + fmtNum(row.total_weight_kg, 2) + '</td>' +
                '<td class="text-end">' + fmtNum(row.total_fish_count, 0) + '</td>';
            tbody.appendChild(tr);
        });

        if (totalShiftsEl) {
            totalShiftsEl.textContent = fmtNum(totals.total_shifts_count || 0, 0);
        }
        if (totalAvgPerShiftEl) {
            const hasShifts = (totals.total_shifts_count || 0) > 0;
            totalAvgPerShiftEl.textContent = hasShifts
                ? (fmtNum(totals.avg_weight_kg_per_shift, 2) + ' кг / ' + fmtNum(totals.avg_fish_count_per_shift, 1) + ' шт')
                : '—';
        }
        totalKgEl.textContent = fmtNum(totals.total_weight_kg, 2);
        totalCountEl.textContent = fmtNum(totals.total_fish_count, 0);

        renderChart(payload);
    }

    function renderChart(payload) {
        const items = Array.isArray(payload.items) ? payload.items : [];
        const labels = items.map(i => i.duty_name + (i.is_deleted ? ' (удалён)' : ''));
        const values = currentMetric === 'kg'
            ? items.map(i => Number(i.total_weight_kg || 0))
            : items.map(i => Number(i.total_fish_count || 0));
        const ctx = chartCanvas.getContext('2d');
        if (chart) chart.destroy();

        const datasetLabel = currentMetric === 'kg' ? 'Падеж, кг' : 'Падеж, шт';
        const axisTitle = currentMetric === 'kg' ? 'Вес падежа, кг' : 'Количество падежа, шт';

        chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: datasetLabel,
                    data: values,
                    backgroundColor: 'rgba(220, 53, 69, 0.75)',
                    borderColor: 'rgb(220, 53, 69)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    x: { ticks: { maxRotation: 45, minRotation: 0 } },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: axisTitle }
                    }
                }
            }
        });
    }
})();
