<?php

use App\Support\View;

View::extends('layouts.app');

$config = $sessionsConfig ?? [
    'baseUrl' => BASE_URL,
];

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Управление сессиями</h1>
            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Добавить сессию
            </button>
        </div>
    </div>

    <?php renderSectionDescription('sessions'); ?>

    <div id="alert-container"></div>

    <ul class="nav nav-tabs mb-3" id="sessionsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="active-sessions-tab" data-bs-toggle="tab" data-bs-target="#active-sessions" type="button" role="tab" onclick="loadSessions(0)">
                Действующие
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="completed-sessions-tab" data-bs-toggle="tab" data-bs-target="#completed-sessions" type="button" role="tab" onclick="loadSessions(1)">
                Завершенные
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="active-sessions" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Бассейн</th>
                                    <th>Посадка</th>
                                    <th>Корм</th>
                                    <th>Стратегия</th>
                                    <th>Кормлений/день</th>
                                    <th>Дата начала</th>
                                    <th>Масса (кг)</th>
                                    <th>Количество (шт)</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="activeSessionsBody">
                                <tr>
                                    <td colspan="11" class="text-center">
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

        <div class="tab-pane fade" id="completed-sessions" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Бассейн</th>
                                    <th>Посадка</th>
                                    <th>Корм</th>
                                    <th>Стратегия</th>
                                    <th>Кормлений/день</th>
                                    <th>Дата начала</th>
                                    <th>Дата окончания</th>
                                    <th>Масса нач. (кг)</th>
                                    <th>Масса кон. (кг)</th>
                                    <th>Корма (кг)</th>
                                    <th>FCR</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="completedSessionsBody">
                                <tr>
                                    <td colspan="14" class="text-center">
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

<div class="modal fade" id="sessionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sessionModalTitle">Добавить сессию</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="sessionForm">
                    <input type="hidden" id="sessionId" name="id">

                    <div class="mb-3">
                        <label for="sessionName" class="form-label">Название <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sessionName" name="name" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sessionPool" class="form-label">Бассейн <span class="text-danger">*</span></label>
                            <select class="form-select" id="sessionPool" name="pool_id" required>
                                <option value="">Выберите бассейн</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sessionPlanting" class="form-label">Посадка <span class="text-danger">*</span></label>
                            <select class="form-select" id="sessionPlanting" name="planting_id" required>
                                <option value="">Выберите посадку</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="sessionStartDate" class="form-label">Дата начала <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="sessionStartDate" name="start_date" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sessionStartMass" class="form-label">Масса посадки (кг) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="sessionStartMass" name="start_mass" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sessionStartFishCount" class="form-label">Количество рыб (шт) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="sessionStartFishCount" name="start_fish_count" min="1" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="sessionPreviousFcr" class="form-label">Прошлый FCR</label>
                            <input type="number" class="form-control" id="sessionPreviousFcr" name="previous_fcr" step="0.0001" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sessionDailyFeedings" class="form-label">Кормёжек в день <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="sessionDailyFeedings" name="daily_feedings" min="1" value="3" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sessionFeedingStrategy" class="form-label">Стратегия кормления</label>
                            <select class="form-select" id="sessionFeedingStrategy" name="feeding_strategy">
                                <option value="econom">Эконом</option>
                                <option value="normal" selected>Норма</option>
                                <option value="growth">Рост</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sessionFeed" class="form-label">Корм <span class="text-danger">*</span></label>
                            <select class="form-select" id="sessionFeed" name="feed_id" required>
                                <option value="">Выберите корм</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveSession()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Завершить сессию</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="completeForm">
                    <input type="hidden" id="completeSessionId" name="id">

                    <div class="mb-3">
                        <label for="completeEndDate" class="form-label">Дата окончания <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="completeEndDate" name="end_date" required>
                    </div>

                    <div class="mb-3">
                        <label for="completeEndMass" class="form-label">Масса в конце (кг) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="completeEndMass" name="end_mass" step="0.01" min="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label for="completeFeedAmount" class="form-label">Внесено корма (кг) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="completeFeedAmount" name="feed_amount" step="0.01" min="0" required>
                    </div>

                    <div class="alert alert-info">
                        <strong>FCR</strong> будет вычислен автоматически: внесено корма / (масса в конце - масса в начале)
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-success" onclick="completeSession()">Завершить сессию</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.sessionsConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>


