<?php

use App\Support\View;

View::extends('layouts.app');

$sessionDetailsConfig = [
    'sessionId' => $sessionId,
    'isAdmin' => !empty($isAdmin),
    'baseUrl' => $baseUrl ?? BASE_URL,
];
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <a href="<?php echo $sessionDetailsConfig['baseUrl']; ?>work" class="btn btn-outline-secondary mb-3">
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
        <!-- График прогноза роста рыбы -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Прогноз роста рыбы (идеальная кривая)</h4>
            </div>
            <div class="card-body">
                <canvas id="growthForecastChart"></canvas>
            </div>
        </div>

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
                    <?php if (!empty($isAdmin)): ?>
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
                        <canvas id="counterpartyHarvestsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Общие отборы</h5>
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
    window.sessionDetailsConfig = <?php echo json_encode($sessionDetailsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
