<?php

use App\Support\View;

View::extends('layouts.app');

$config = $poolsConfig ?? [
    'baseUrl' => BASE_URL,
];

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Управление бассейнами</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#poolModal" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Добавить бассейн
            </button>
        </div>
    </div>

    <?php renderSectionDescription('pools'); ?>

    <div id="alert-container"></div>

    <div class="card">
        <div class="card-body">
            <p class="text-muted mb-3">
                <i class="bi bi-info-circle"></i> Перетащите бассейны для изменения порядка сортировки
            </p>
            <div id="poolsList" class="list-group">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="poolModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="poolModalTitle">Добавить бассейн</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="poolForm">
                    <input type="hidden" id="poolId" name="id">

                    <div class="mb-3">
                        <label for="poolName" class="form-label">Название бассейна <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="poolName" name="name" required maxlength="255">
                    </div>

                    <div class="mb-3" id="isActiveContainer" style="display: none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="poolIsActive" name="is_active" value="1">
                            <label class="form-check-label" for="poolIsActive">
                                Активен
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="savePool()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.poolsConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>


