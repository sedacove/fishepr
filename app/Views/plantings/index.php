<?php

use App\Support\View;

View::extends('layouts.app');

$config = $plantingsConfig ?? [
    'baseUrl' => BASE_URL,
];

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Управление посадками</h1>
            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Добавить посадку
            </button>
        </div>
    </div>

    <?php renderSectionDescription('plantings'); ?>

    <div id="alert-container"></div>

    <ul class="nav nav-tabs mb-3" id="plantingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-plantings" type="button" role="tab" onclick="loadPlantings(0)">
                Действующие
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="archived-tab" data-bs-toggle="tab" data-bs-target="#archived-plantings" type="button" role="tab" onclick="loadPlantings(1)">
                Архивные
            </button>
        </li>
    </ul>

    <div class="tab-content" id="plantingsTabContent">
        <div class="tab-pane fade show active" id="active-plantings" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Порода рыбы</th>
                                    <th>Дата посадки</th>
                                    <th>Количество</th>
                                    <th>Вес (кг)</th>
                                    <th>Поставщик</th>
                                    <th>Цена</th>
                                    <th>Файлы</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="activePlantingsBody">
                                <tr>
                                    <td colspan="10" class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Загрузка...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="archived-plantings" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Порода рыбы</th>
                                    <th>Дата посадки</th>
                                    <th>Количество</th>
                                    <th>Вес (кг)</th>
                                    <th>Поставщик</th>
                                    <th>Цена</th>
                                    <th>Файлы</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="archivedPlantingsBody">
                                <tr>
                                    <td colspan="10" class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Загрузка...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="plantingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="plantingModalTitle">Добавить посадку</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="plantingForm">
                    <input type="hidden" id="plantingId" name="id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="plantingName" class="form-label">Название <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="plantingName" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="plantingFishBreed" class="form-label">Порода рыбы <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="plantingFishBreed" name="fish_breed" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="plantingHatchDate" class="form-label">Дата вылупа</label>
                            <input type="date" class="form-control" id="plantingHatchDate" name="hatch_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="plantingDate" class="form-label">Дата посадки <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="plantingDate" name="planting_date" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="plantingFishCount" class="form-label">Количество рыб (шт) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="plantingFishCount" name="fish_count" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="plantingBiomassWeight" class="form-label">Вес биомассы (кг)</label>
                            <input type="number" class="form-control" id="plantingBiomassWeight" name="biomass_weight" step="0.01" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="plantingSupplier" class="form-label">Поставщик</label>
                            <input type="text" class="form-control" id="plantingSupplier" name="supplier">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="plantingPrice" class="form-label">Цена (руб.)</label>
                            <input type="number" class="form-control" id="plantingPrice" name="price" step="0.01" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="plantingDeliveryCost" class="form-label">Стоимость доставки (руб.)</label>
                            <input type="number" class="form-control" id="plantingDeliveryCost" name="delivery_cost" step="0.01" min="0">
                        </div>
                    </div>
                </form>

                <div id="filesSection" class="mt-4" style="display: none;">
                    <label class="form-label">Сопроводительные документы</label>
                    <div id="dropZone" class="plantings-dropzone text-center border border-dashed rounded p-4">
                        <i class="bi bi-cloud-upload display-4"></i>
                        <p class="mt-2 mb-0">Перетащите файлы сюда или нажмите для выбора</p>
                        <small class="text-muted">Можно загрузить несколько файлов (максимум 10 МБ каждый)</small>
                        <input type="file" id="fileInput" multiple hidden>
                    </div>
                    <div id="filesList" class="mt-3"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="savePlanting()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.plantingsConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>


