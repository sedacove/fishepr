<?php

use App\Support\View;

View::extends('layouts.app');

$extra_styles = $extra_styles ?? [];
$extra_styles[] = 'assets/css/pages/reports.css';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Отчет по отборам</h1>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="reportFilters" class="row g-3">
                <div class="col-md-3">
                    <label for="dateFrom" class="form-label">Дата с</label>
                    <input type="date" class="form-control" id="dateFrom" name="date_from">
                </div>
                <div class="col-md-3">
                    <label for="dateTo" class="form-label">Дата по</label>
                    <input type="date" class="form-control" id="dateTo" name="date_to">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Контрагент</label>
                    <div class="dropdown-checkbox-wrapper">
                        <button class="btn btn-outline-secondary form-control text-start dropdown-toggle" type="button" id="counterpartyDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="dropdown-text">Все контрагенты</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-checkbox" aria-labelledby="counterpartyDropdown" id="counterpartyDropdownMenu">
                            <li>
                                <label class="dropdown-item">
                                    <input type="checkbox" class="form-check-input me-2" id="counterpartySelectAll" value="all">
                                    <strong>Выбрать все</strong>
                                </label>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php foreach ($counterparties as $counterparty): ?>
                                <li>
                                    <label class="dropdown-item">
                                        <input type="checkbox" class="form-check-input me-2 counterparty-checkbox" 
                                               name="counterparty_id[]" 
                                               value="<?php echo htmlspecialchars($counterparty['id']); ?>"
                                               data-name="<?php echo htmlspecialchars($counterparty['name']); ?>">
                                        <?php echo htmlspecialchars($counterparty['name']); ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="plantingId" class="form-label">Аквакультура</label>
                    <select class="form-select" id="plantingId" name="planting_id">
                        <option value="">Все</option>
                        <?php foreach ($plantings as $planting): ?>
                            <option value="<?php echo htmlspecialchars($planting->id); ?>">
                                <?php echo htmlspecialchars($planting->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-file-earmark-text"></i> Сформировать отчет
                    </button>
                    <button type="button" class="btn btn-secondary" id="printReport" style="display: none;">
                        <i class="bi bi-printer"></i> Печать
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Результаты отчета -->
    <div id="reportResults" style="display: none;">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="reportTable">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Бассейн</th>
                                <th>Аквакультура</th>
                                <th>Контрагент</th>
                                <th class="text-end">Вес (кг)</th>
                                <th class="text-end">Количество (шт)</th>
                                <th class="text-end">Средняя навеска (кг/шт)</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody">
                        </tbody>
                        <tfoot>
                            <tr class="table-primary fw-bold">
                                <td colspan="4" class="text-end">Итого:</td>
                                <td class="text-end" id="totalWeight">0.00</td>
                                <td class="text-end" id="totalFishCount">0</td>
                                <td class="text-end" id="totalAvgWeight">0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Сообщение об отсутствии данных -->
    <div id="noDataMessage" class="alert alert-info" style="display: none;">
        <i class="bi bi-info-circle"></i> Нет данных для отображения по выбранным фильтрам.
    </div>
</div>

<!-- Печатная форма (скрыта на экране) -->
<div id="printContent" style="display: none;">
    <div class="print-header">
        <div class="print-logo">
            <img src="<?php echo asset_url('assets/images/logo.png'); ?>" alt="Логотип" onerror="this.style.display='none'">
        </div>
        <h2 class="print-title" id="printTitle">Отчет по отгрузкам</h2>
        <div class="print-filters" id="printFilters"></div>
    </div>
    <div class="print-table-wrapper">
        <table class="print-table" id="printTable">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Бассейн</th>
                    <th>Аквакультура</th>
                    <th>Контрагент</th>
                    <th class="text-end">Вес (кг)</th>
                    <th class="text-end">Количество (шт)</th>
                    <th class="text-end">Средняя навеска (кг/шт)</th>
                </tr>
            </thead>
            <tbody id="printTableBody">
            </tbody>
            <tfoot>
                <tr class="print-totals">
                    <td colspan="4" class="text-end">Итого:</td>
                    <td class="text-end" id="printTotalWeight">0.00</td>
                    <td class="text-end" id="printTotalFishCount">0</td>
                    <td class="text-end" id="printTotalAvgWeight">0.00</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script src="<?php echo asset_url('assets/js/pages/reports_harvests.js'); ?>"></script>

