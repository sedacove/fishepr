<?php

use App\Support\View;

View::extends('layouts.app');

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Логи действий</h1>
            <?php renderSectionDescription('logs'); ?>
        </div>
    </div>
    
    <div id="alert-container"></div>
    
    <!-- Фильтры -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Фильтры</h5>
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label for="dateFrom" class="form-label">Дата от</label>
                    <input type="date" class="form-control" id="dateFrom" name="date_from">
                </div>
                <div class="col-md-3">
                    <label for="dateTo" class="form-label">Дата до</label>
                    <input type="date" class="form-control" id="dateTo" name="date_to">
                </div>
                <div class="col-md-2">
                    <label for="filterAction" class="form-label">Действие</label>
                    <select class="form-select" id="filterAction" name="action">
                        <option value="">Все</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filterEntityType" class="form-label">Тип сущности</label>
                    <select class="form-select" id="filterEntityType" name="entity_type">
                        <option value="">Все</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" onclick="applyFilters()">
                        <i class="bi bi-funnel"></i> Применить
                    </button>
                </div>
            </form>
            <div class="mt-2">
                <button type="button" class="btn btn-sm btn-secondary" onclick="clearFilters()">
                    <i class="bi bi-x-circle"></i> Сбросить фильтры
                </button>
            </div>
        </div>
    </div>
    
    <!-- Таблица логов -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover logs-table" id="logsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Дата и время</th>
                            <th>Пользователь</th>
                            <th>Действие</th>
                            <th>Тип сущности</th>
                            <th>ID сущности</th>
                            <th>Описание</th>
                            <th>IP адрес</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <tr>
                            <td colspan="8" class="text-center">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Загрузка...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Пагинация -->
            <nav aria-label="Навигация по страницам" id="paginationContainer">
                <ul class="pagination justify-content-center mt-3" id="pagination">
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Модальное окно для отображения изменений -->
<div class="modal fade" id="changesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Измененные данные</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="changesContent" class="bg-light p-3 rounded" style="max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo asset_url('assets/js/pages/logs.js'); ?>"></script>

