<?php

use App\Support\View;

View::extends('layouts.app');

$configJson = json_encode($dashboardConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                <h1 class="mb-0">Добро пожаловать, <?php echo htmlspecialchars($userName); ?>!</h1>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleEditBtn">
                    <i class="bi bi-pencil"></i> Редактировать
                </button>
            </div>

            <div id="alert-container"></div>

            <div id="dashboardWidgets" class="dashboard-columns">
                <div class="dashboard-column" data-column-index="0"></div>
                <div class="dashboard-column" data-column-index="1"></div>
            </div>

            <div class="modal fade" id="addWidgetModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Добавить виджет</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                        </div>
                        <div class="modal-body">
                            <div id="availableWidgetsList" class="list-group"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.dashboardConfig = <?php echo $configJson; ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="<?php echo asset_url('assets/js/pages/dashboard.js'); ?>"></script>

