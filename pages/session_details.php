<?php
/**
 * Страница деталей сессии
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

// Требуем авторизацию
requireAuth();

$sessionId = $_GET['id'] ?? null;

if (!$sessionId) {
    header('Location: ' . BASE_URL . 'pages/work.php');
    exit;
}

$page_title = 'Детали сессии';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/session_details.css">

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <a href="<?php echo BASE_URL; ?>pages/work.php" class="btn btn-outline-secondary mb-3">
                <i class="bi bi-arrow-left"></i> Назад к рабочей
            </a>
        </div>
    </div>
    
    <div id="alert-container"></div>
    
    <div id="loadingIndicator" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Загрузка...</span>
        </div>
        <p class="mt-3 text-muted">Загрузка данных сессии...</p>
    </div>
    
    <div id="sessionContent" style="display: none;">
        <!-- Информация о сессии -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="mb-0">Информация о сессии</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Название:</strong> <span id="sessionName"></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Бассейн:</strong> <span id="poolName"></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Посадка:</strong> <span id="plantingName"></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Порода рыбы:</strong> <span id="fishBreed"></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Дата начала:</strong> <span id="startDate"></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Начальная масса:</strong> <span id="startMass"></span> кг
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Начальное количество:</strong> <span id="startFishCount"></span> шт
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Прошлый FCR:</strong> <span id="previousFcr"></span>
                    </div>
                    <?php if (isAdmin()): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Конечная масса:</strong> <span id="endMass"></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Внесено корма:</strong> <span id="feedAmount"></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>FCR:</strong> <span id="fcr"></span>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6 mb-3">
                        <strong>Статус:</strong> 
                        <span id="sessionStatus" class="badge"></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Графики -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Температура</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="temperatureChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">O<sub>2</sub></h5>
                    </div>
                    <div class="card-body">
                        <canvas id="oxygenChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Падеж</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="mortalityChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Отборы</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="harvestsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Навески (средний вес)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="weighingsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const sessionId = <?php echo json_encode($sessionId); ?>;

// Загрузка данных сессии
function loadSessionDetails() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/session_details.php?id=' + sessionId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displaySessionData(response.data);
                $('#loadingIndicator').hide();
                $('#sessionContent').show();
            } else {
                showAlert('danger', response.message);
                $('#loadingIndicator').hide();
            }
        },
        error: function(xhr, status, error) {
            let errorMessage = 'Ошибка при загрузке данных сессии';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    errorMessage = 'Ошибка: ' + error;
                }
            }
            showAlert('danger', errorMessage);
            $('#loadingIndicator').hide();
            console.error('Session details error:', xhr, status, error);
        }
    });
}

// Отображение данных сессии
function displaySessionData(data) {
    const session = data.session;
    
    // Заполняем информацию о сессии
    $('#sessionName').text(session.name || '—');
    $('#poolName').text(session.pool_name || '—');
    $('#plantingName').text(session.planting_name || '—');
    $('#fishBreed').text(session.fish_breed || '—');
    $('#startDate').text(session.start_date ? formatDate(session.start_date) : '—');
    $('#startMass').text(session.start_mass ? formatNumber(session.start_mass, 2) : '—');
    $('#startFishCount').text(session.start_fish_count ? formatNumber(session.start_fish_count, 0) : '—');
    $('#previousFcr').text(session.previous_fcr ? formatNumber(session.previous_fcr, 2) : '—');
    
    <?php if (isAdmin()): ?>
    $('#endMass').text(session.end_mass ? formatNumber(session.end_mass, 2) + ' кг' : '—');
    $('#feedAmount').text(session.feed_amount ? formatNumber(session.feed_amount, 2) + ' кг' : '—');
    $('#fcr').text(session.fcr ? formatNumber(session.fcr, 2) : '—');
    <?php endif; ?>
    
    const statusBadge = session.is_completed ? 
        '<span class="badge bg-secondary">Завершена</span>' : 
        '<span class="badge bg-success">Активна</span>';
    $('#sessionStatus').html(statusBadge);
    
    // Строим графики
    buildTemperatureChart(data.measurements);
    buildOxygenChart(data.measurements);
    buildMortalityChart(data.mortality);
    buildHarvestsChart(data.harvests);
    buildWeighingsChart(data.weighings);
}

// График температуры
function buildTemperatureChart(measurements) {
    const ctx = document.getElementById('temperatureChart').getContext('2d');
    
    const labels = measurements.map(m => formatDateTime(m.measured_at));
    const tempData = measurements.map(m => parseFloat(m.temperature));
    
    // Создаем градиент
    const tempGradient = ctx.createLinearGradient(0, 0, 0, 400);
    tempGradient.addColorStop(0, 'rgba(255, 99, 132, 0.5)');
    tempGradient.addColorStop(1, 'rgba(255, 99, 132, 0)');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Температура (°C)',
                    data: tempData,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: tempGradient,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    min: 5,
                    max: 25,
                    title: {
                        display: true,
                        text: 'Температура (°C)'
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });
}

// График кислорода
function buildOxygenChart(measurements) {
    const ctx = document.getElementById('oxygenChart').getContext('2d');
    
    const labels = measurements.map(m => formatDateTime(m.measured_at));
    const oxygenData = measurements.map(m => parseFloat(m.oxygen));
    
    // Создаем градиент
    const oxygenGradient = ctx.createLinearGradient(0, 0, 0, 400);
    oxygenGradient.addColorStop(0, 'rgba(54, 162, 235, 0.5)');
    oxygenGradient.addColorStop(1, 'rgba(54, 162, 235, 0)');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'O₂',
                    data: oxygenData,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: oxygenGradient,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    min: 5,
                    max: 20,
                    title: {
                        display: true,
                        text: 'O₂'
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });
}

// График падежа
function buildMortalityChart(mortality) {
    const ctx = document.getElementById('mortalityChart').getContext('2d');
    
    const labels = mortality.map(m => formatDateTime(m.recorded_at));
    const countData = mortality.map(m => parseInt(m.fish_count));
    const weightData = mortality.map(m => parseFloat(m.weight));
    
    const countGradient = ctx.createLinearGradient(0, 0, 0, 400);
    countGradient.addColorStop(0, 'rgba(220, 53, 69, 0.5)');
    countGradient.addColorStop(1, 'rgba(220, 53, 69, 0)');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Количество (шт)',
                    data: countData,
                    backgroundColor: countGradient,
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
                    title: {
                        display: true,
                        text: 'Количество (шт)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Вес (кг)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });
}

// График отборов
function buildHarvestsChart(harvests) {
    const ctx = document.getElementById('harvestsChart').getContext('2d');
    
    const labels = harvests.map(h => formatDateTime(h.recorded_at));
    const countData = harvests.map(h => parseInt(h.fish_count));
    const weightData = harvests.map(h => parseFloat(h.weight));
    
    const countGradient = ctx.createLinearGradient(0, 0, 0, 400);
    countGradient.addColorStop(0, 'rgba(25, 135, 84, 0.5)');
    countGradient.addColorStop(1, 'rgba(25, 135, 84, 0)');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Количество (шт)',
                    data: countData,
                    backgroundColor: countGradient,
                    borderColor: 'rgb(25, 135, 84)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Вес (кг)',
                    data: weightData,
                    type: 'line',
                    borderColor: 'rgb(40, 167, 69)',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
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
                    title: {
                        display: true,
                        text: 'Количество (шт)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Вес (кг)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });
}

// График навесок (средний вес)
function buildWeighingsChart(weighings) {
    const ctx = document.getElementById('weighingsChart').getContext('2d');
    
    const labels = weighings.map(w => formatDateTime(w.recorded_at));
    const avgWeightData = weighings.map(w => {
        const count = parseInt(w.fish_count);
        const weight = parseFloat(w.weight);
        return count > 0 ? weight / count : 0;
    });
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(255, 193, 7, 0.5)');
    gradient.addColorStop(1, 'rgba(255, 193, 7, 0)');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Средний вес (кг)',
                data: avgWeightData,
                borderColor: 'rgb(255, 193, 7)',
                backgroundColor: gradient,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Средний вес (кг)'
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });
}

// Вспомогательные функции
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('ru-RU', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatNumber(value, decimals) {
    return parseFloat(value).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('#alert-container').html(alertHtml);
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
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Загрузка при открытии страницы
$(document).ready(function() {
    loadSessionDetails();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

