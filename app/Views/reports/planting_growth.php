<?php

use App\Support\View;

View::extends('layouts.app');

$extra_styles = $extra_styles ?? [];
$extra_styles[] = 'assets/css/pages/reports.css';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Отчет по росту посадок</h1>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form id="plantingGrowthFilters" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="growthPlantingId" class="form-label">Посадка</label>
                    <select class="form-select" id="growthPlantingId" name="planting_id" required>
                        <option value="">Выберите посадку...</option>
                        <?php foreach ($plantings as $planting): ?>
                            <option value="<?php echo htmlspecialchars($planting->id); ?>">
                                <?php echo htmlspecialchars($planting->name); ?>
                                <?php if (!empty($planting->fish_breed)): ?>
                                    (<?php echo htmlspecialchars($planting->fish_breed); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="growthDateFrom" class="form-label">Дата с (опционально)</label>
                    <input type="date" class="form-control" id="growthDateFrom" name="date_from">
                </div>
                <div class="col-md-3">
                    <label for="growthDateTo" class="form-label">Дата по (опционально)</label>
                    <input type="date" class="form-control" id="growthDateTo" name="date_to">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-bar-chart-line"></i> Показать
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="plantingGrowthAlert" class="alert alert-info" style="display: none;"></div>

    <!-- Основные данные над графиком -->
    <div id="plantingGrowthSummary" class="planting-growth-summary mb-4" style="display: none;">
        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="planting-growth-days">
                    Дней с момента посадки: <span id="plantingGrowthDaysValue">—</span>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="planting-growth-stat">
                    <span class="planting-growth-stat-label">Биомасса на сегодня, кг</span>
                    <span class="planting-growth-stat-value" id="summaryCurrentBiomass">—</span>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="planting-growth-stat">
                    <span class="planting-growth-stat-label">Средняя навеска на сегодня, г/шт</span>
                    <span class="planting-growth-stat-value" id="summaryCurrentAvg">—</span>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="planting-growth-stat">
                    <span class="planting-growth-stat-label">Первоначальный вес посадки, кг</span>
                    <span class="planting-growth-stat-value" id="summaryInitialWeight">—</span>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="planting-growth-stat">
                    <span class="planting-growth-stat-label">Первоначальная навеска, г/шт</span>
                    <span class="planting-growth-stat-value" id="summaryInitialAvg">—</span>
                </div>
            </div>
        </div>
        <div class="planting-growth-extra-card card border-secondary">
            <div class="card-body py-3">
                <div class="row g-3 text-center">
                    <div class="col-md-6">
                        <span class="planting-growth-extra-label">Всего отгрузок по данной посадке</span>
                        <div class="planting-growth-extra-value" id="summaryHarvest">— кг / — шт</div>
                    </div>
                    <div class="col-md-6">
                        <span class="planting-growth-extra-label">Падеж по данной посадке</span>
                        <div class="planting-growth-extra-value" id="summaryMortality">— кг / — шт</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" id="plantingGrowthCard" style="display: none;">
        <div class="card-body">
            <h5 class="card-title" id="plantingGrowthTitle"></h5>
            <p class="text-muted" id="plantingGrowthSubtitle"></p>
            <div style="height: 400px;">
                <canvas id="plantingGrowthChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo asset_url('assets/js/pages/reports_planting_growth.js'); ?>"></script>

