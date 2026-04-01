<?php
use App\Support\View;
View::extends('layouts.app');
$extra_styles = $extra_styles ?? [];
$extra_styles[] = 'assets/css/pages/reports.css';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Отчёт о дополнительных работах</h1>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form id="extraWorksReportFilters" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="extraWorksDateFrom" class="form-label">Дата с</label>
                    <input type="date" class="form-control" id="extraWorksDateFrom" name="date_from">
                </div>
                <div class="col-md-3">
                    <label for="extraWorksDateTo" class="form-label">Дата по</label>
                    <input type="date" class="form-control" id="extraWorksDateTo" name="date_to">
                </div>
                <div class="col-md-4">
                    <label for="extraWorksAssignedTo" class="form-label">Исполнитель</label>
                    <select class="form-select" id="extraWorksAssignedTo" name="assigned_to">
                        <option value="">Все исполнители</option>
                        <?php foreach (($executors ?? []) as $executor): ?>
                            <option value="<?php echo (int)$executor['id']; ?>">
                                <?php echo htmlspecialchars(($executor['full_name'] ?: $executor['login']) . ' (' . $executor['login'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-table"></i> Показать
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="extraWorksReportAlert" class="alert alert-info" style="display: none;"></div>

    <div class="card" id="extraWorksReportCard" style="display: none;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Дополнительные работы</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-success" id="extraWorksExportExcel">
                        <i class="bi bi-file-earmark-excel"></i> Excel
                    </button>
                    <button type="button" class="btn btn-danger" id="extraWorksExportPdf">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </button>
                    <button type="button" class="btn btn-secondary" id="extraWorksPrint">
                        <i class="bi bi-printer"></i> Печать
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0" id="extraWorksReportTable">
                    <thead class="table-light">
                        <tr>
                            <th>Дата</th>
                            <th>Название</th>
                            <th>Описание</th>
                            <th>Исполнитель</th>
                            <th class="text-end">Сумма, ₽</th>
                            <th>Оплачено</th>
                        </tr>
                    </thead>
                    <tbody id="extraWorksReportBody">
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="4" class="text-end">Итого:</td>
                            <td class="text-end" id="extraWorksReportTotal">0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
<script src="<?php echo asset_url('assets/js/pages/reports_extra_works.js'); ?>"></script>
