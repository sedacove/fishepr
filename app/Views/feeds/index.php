<?php

use App\Support\FeedTableParser;
use App\Support\View;

View::extends('layouts.app');

$config = $feedsConfig ?? [
    'baseUrl' => BASE_URL,
];
$templateYaml = $feedTableTemplate ?? FeedTableParser::getTemplate();

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="mb-0">Корма</h1>
            <button type="button" class="btn btn-primary" onclick="openFeedModal()">
                <i class="bi bi-plus-circle"></i> Добавить корм
            </button>
        </div>
    </div>

    <?php renderSectionDescription('feeds'); ?>

    <div id="feedsAlertContainer"></div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="feedsTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Название</th>
                            <th>Гранула</th>
                            <th>Производитель</th>
                            <th>Нормы</th>
                            <th>Обновлён</th>
                            <th style="width: 150px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody id="feedsTableBody">
                        <tr>
                            <td colspan="7" class="text-center py-4">
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

    <div class="card mt-4" id="feedsChartsWrapper">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h4 mb-1">Графики температур и веса</h2>
                    <p class="text-muted mb-0 small">По данным из YAML-таблиц каждого корма (значение – кг корма на 100 кг биомассы в сутки).</p>
                </div>
                <div class="text-muted small" id="feedsChartsLegend"></div>
            </div>
            <div id="feedsChartsContainer" class="feed-charts-grid">
                <div class="text-center text-muted py-4 w-100">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    Загружаем данные графиков...
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="feedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="feedModalTitle">Добавить корм</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="feedForm" novalidate>
                    <input type="hidden" id="feedId">

                    <div class="mb-3">
                        <label for="feedName" class="form-label">Название <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="feedName" name="name" maxlength="255" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="feedManufacturer" class="form-label">Производитель</label>
                            <input type="text" class="form-control" id="feedManufacturer" name="manufacturer" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label for="feedGranule" class="form-label">Размер гранулы / тип</label>
                            <input type="text" class="form-control" id="feedGranule" name="granule" maxlength="255">
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label for="feedDescription" class="form-label">Описание</label>
                        <textarea class="form-control" id="feedDescription" name="description" rows="3"></textarea>
                    </div>

                    <div class="alert alert-light border mb-4">
                        <strong class="d-block mb-2">Таблица коэффициентов кормления</strong>
                        <p class="mb-2 small text-muted">
                            Заполните YAML-таблицу, где указаны диапазоны веса (в граммах), допустимые температуры и значения коэффициентов.
                            Значение показывает, сколько килограммов корма требуется на 100 кг биомассы в сутки.
                        </p>
                        <div class="feed-template-preview">
                            <textarea class="form-control feed-template-textarea font-monospace" id="feedTemplateExample" rows="14" readonly><?php echo htmlspecialchars($templateYaml, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
                            <div class="text-end mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-copy-template="#feedTemplateExample">
                                    Скопировать шаблон
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-label d-flex justify-content-between align-items-center mb-2">
                            <span>Таблица коэффициентов кормления <span class="text-danger">*</span></span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-feed-template="#feedFormulaNormal">
                                Вставить шаблон
                            </button>
                        </div>
                        <textarea class="form-control feed-table-textarea font-monospace" id="feedFormulaNormal" name="formula_normal" rows="16" required></textarea>
                        <div class="form-text">
                            Эта таблица используется для расчета всех трех стратегий кормления (Эконом, Норма, Рост).
                            <br>
                            <strong>Эконом:</strong> выбирается меньший коэффициент из двух соседних температурных значений.
                            <br>
                            <strong>Норма:</strong> вычисляется интерполяция между двумя соседними значениями пропорционально температуре.
                            <br>
                            <strong>Рост:</strong> выбирается больший коэффициент из двух соседних температурных значений.
                        </div>
                    </div>

                    <hr class="my-4">

                    <div id="feedNormsUnavailable" class="alert alert-secondary">
                        Чтобы добавить нормы кормления, сначала сохраните карточку корма.
                    </div>

                    <div id="feedNormsSection" class="d-none">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="mb-0">Нормы кормления (изображения)</h5>
                            <label class="btn btn-outline-primary mb-0">
                                <i class="bi bi-upload"></i> Загрузить изображения
                                <input type="file" id="feedNormUploadInput" multiple accept="image/*" hidden>
                            </label>
                        </div>
                        <div id="feedNormsGallery" class="feed-norms-grid">
                            <div class="text-muted small">Изображения ещё не добавлены</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveFeed()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.feedsConfig = <?php echo json_encode(
        array_merge($config, ['tableTemplate' => $templateYaml]),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ); ?>;
</script>

