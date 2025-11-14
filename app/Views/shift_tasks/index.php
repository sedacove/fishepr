<?php

use App\Support\View;

require_once __DIR__ . '/../../../includes/duty_helpers.php';

View::extends('layouts.app');
?>

<div class="container mt-4 mb-5">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="mb-1">Задания смены</h1>
            <p class="text-muted mb-0">Настройка повторяющихся чек-листов и контроль выполнения в текущей смене</p>
        </div>
        <button class="btn btn-primary" id="createTemplateBtn">
            <i class="bi bi-plus-lg me-2"></i>Добавить шаблон
        </button>
    </div>

    <div id="shiftTasksAlerts" class="mb-3"></div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Шаблоны заданий</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle" id="shiftTemplatesTable">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Повторение</th>
                                    <th>Время</th>
                                    <th class="text-end">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Загрузка шаблонов...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-0">Чек-лист текущей смены</h5>
                        <small class="text-muted" id="shiftTasksDateLabel"></small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-secondary btn-sm" id="refreshShiftTasks">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="shiftTasksChecklist" class="shift-tasks-list">
                        <div class="text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                            Загрузка заданий смены...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модал для создания/редактирования шаблона -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="templateForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="templateModalTitle">Новый шаблон</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Название</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Время выполнения</label>
                            <input type="time" class="form-control" name="due_time" value="12:00" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Описание</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Дополнительные инструкции"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Повторение</label>
                            <select class="form-select" name="frequency" id="frequencySelect" required>
                                <option value="daily">Каждый день</option>
                                <option value="weekly">Раз в неделю</option>
                                <option value="biweekly">Раз в две недели</option>
                                <option value="monthly">Раз в месяц</option>
                            </select>
                        </div>
                        <div class="col-md-4 frequency-dependent" data-frequency="weekly biweekly">
                            <label class="form-label">День недели</label>
                            <select class="form-select" name="week_day">
                                <option value="1">Понедельник</option>
                                <option value="2">Вторник</option>
                                <option value="3">Среда</option>
                                <option value="4">Четверг</option>
                                <option value="5">Пятница</option>
                                <option value="6">Суббота</option>
                                <option value="0">Воскресенье</option>
                            </select>
                        </div>
                        <div class="col-md-4 frequency-dependent" data-frequency="monthly">
                            <label class="form-label">День месяца</label>
                            <input type="number" class="form-control" name="day_of_month" min="1" max="31" value="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Начать с</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars(getTodayDutyDate()); ?>">
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_active" id="templateActiveCheckbox" checked>
                                <label class="form-check-label" for="templateActiveCheckbox">
                                    Шаблон активен
                                </label>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="id" id="templateIdField">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модал для удаления -->
<div class="modal fade" id="deleteTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Удалить шаблон</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Вы уверены, что хотите удалить шаблон <strong id="deleteTemplateTitle"></strong>?<br>
                История выполнений останется, но новые задания создаваться не будут.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteTemplate">Удалить</button>
            </div>
        </div>
    </div>
</div>

