<?php
use App\Support\View;
View::extends('layouts.app');
$extra_styles = $extra_styles ?? [];
$extra_styles[] = 'assets/css/pages/reports.css';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Отчёт о затратах</h1>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form id="expensesReportFilters" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="expensesDateFrom" class="form-label">Дата с</label>
                    <input type="date" class="form-control" id="expensesDateFrom" name="date_from">
                </div>
                <div class="col-md-3">
                    <label for="expensesDateTo" class="form-label">Дата по</label>
                    <input type="date" class="form-control" id="expensesDateTo" name="date_to">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-table"></i> Показать
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="expensesReportAlert" class="alert alert-info" style="display: none;"></div>

    <div class="card" id="expensesReportCard" style="display: none;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Затраты</h5>
                <button type="button" class="btn btn-success btn-sm" id="expensesExportExcel">
                    <i class="bi bi-file-earmark-excel"></i> Выгрузить в Excel
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0" id="expensesReportTable">
                    <thead class="table-light">
                        <tr>
                            <th>Дата</th>
                            <th>Цель расхода</th>
                            <th class="text-end">Сумма, ₽</th>
                            <th>Комментарий</th>
                        </tr>
                    </thead>
                    <tbody id="expensesReportBody">
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="2" class="text-end">Итого:</td>
                            <td class="text-end" id="expensesReportTotal">0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<script src="<?php echo asset_url('assets/js/pages/reports_expenses.js'); ?>"></script>
