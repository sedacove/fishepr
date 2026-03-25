<?php
use App\Support\View;
View::extends('layouts.app');
$extra_styles = $extra_styles ?? [];
$extra_styles[] = 'assets/css/pages/reports.css';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Падежи в разрезе дежурных</h1>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form id="mortalityByDutyFilters" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="mortalityDateFrom" class="form-label">Дата с</label>
                    <input type="date" class="form-control" id="mortalityDateFrom" name="date_from">
                </div>
                <div class="col-md-3">
                    <label for="mortalityDateTo" class="form-label">Дата по</label>
                    <input type="date" class="form-control" id="mortalityDateTo" name="date_to">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-bar-chart-line"></i> Показать
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="mortalityByDutyAlert" class="alert alert-info" style="display:none;"></div>

    <div id="mortalityByDutyResults" style="display:none;">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 justify-content-end mb-2">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Метрика графика">
                        <input type="radio" class="btn-check" name="mortalityMetric" id="mortalityMetricCount" value="count" autocomplete="off" checked>
                        <label class="btn btn-outline-secondary" for="mortalityMetricCount">Шт</label>

                        <input type="radio" class="btn-check" name="mortalityMetric" id="mortalityMetricKg" value="kg" autocomplete="off">
                        <label class="btn btn-outline-secondary" for="mortalityMetricKg">Кг</label>
                    </div>
                </div>
                <div style="height:380px;">
                    <canvas id="mortalityByDutyChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0" id="mortalityByDutyTable">
                        <thead class="table-light">
                            <tr>
                                <th>Дежурный</th>
                                <th class="text-end">Дежурств</th>
                                <th class="text-end">В среднем за дежурство</th>
                                <th class="text-end">Падеж, кг</th>
                                <th class="text-end">Падеж, шт</th>
                            </tr>
                        </thead>
                        <tbody id="mortalityByDutyBody"></tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td class="text-end">Итого:</td>
                                <td class="text-end" id="mortalityByDutyTotalShifts">0</td>
                                <td class="text-end" id="mortalityByDutyTotalAvgPerShift">—</td>
                                <td class="text-end" id="mortalityByDutyTotalKg">0</td>
                                <td class="text-end" id="mortalityByDutyTotalCount">0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo asset_url('assets/js/pages/reports_mortality_by_duty.js'); ?>"></script>
