<?php
/**
 * Страница управления сессиями
 * Доступна только администраторам
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';

$page_title = 'Управление сессиями';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Управление сессиями</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sessionModal" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Добавить сессию
            </button>
        </div>
    </div>
    
    <?php renderSectionDescription('sessions'); ?>
    
    <div id="alert-container"></div>
    
    <!-- Табы -->
    <ul class="nav nav-tabs mb-3" id="sessionsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" onclick="loadSessions(0)">
                Действующие
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" onclick="loadSessions(1)">
                Завершенные
            </button>
        </li>
    </ul>
    
    <!-- Содержимое табов -->
    <div class="tab-content" id="sessionsTabContent">
        <!-- Действующие сессии -->
        <div class="tab-pane fade show active" id="active" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="activeSessionsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Бассейн</th>
                                    <th>Посадка</th>
                                    <th>Дата начала</th>
                                    <th>Масса (кг)</th>
                                    <th>Количество (шт)</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="activeSessionsBody">
                                <tr>
                                    <td colspan="8" class="text-center">
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
        
        <!-- Завершенные сессии -->
        <div class="tab-pane fade" id="completed" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="completedSessionsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Бассейн</th>
                                    <th>Посадка</th>
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
    </div>
</div>

<!-- Модальное окно для добавления/редактирования сессии -->
<div class="modal fade" id="sessionModal" tabindex="-1">
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

<!-- Модальное окно для завершения сессии -->
<div class="modal fade" id="completeModal" tabindex="-1">
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
let currentEditId = null;
let currentTab = 0;
let poolsList = [];
let plantingsList = [];

// Загрузка списков для выпадающих списков
function loadSelectOptions() {
    // Загрузка бассейнов
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/sessions.php?action=get_pools',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                poolsList = response.data;
                const select = $('#sessionPool');
                select.empty().append('<option value="">Выберите бассейн</option>');
                response.data.forEach(function(pool) {
                    select.append(`<option value="${pool.id}">${escapeHtml(pool.name)}</option>`);
                });
            }
        }
    });
    
    // Загрузка посадок
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/sessions.php?action=get_plantings',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                plantingsList = response.data;
                const select = $('#sessionPlanting');
                select.empty().append('<option value="">Выберите посадку</option>');
                response.data.forEach(function(planting) {
                    select.append(`<option value="${planting.id}">${escapeHtml(planting.name)} (${escapeHtml(planting.fish_breed)})</option>`);
                });
            }
        }
    });
}

// Загрузка сессий
function loadSessions(completed = 0) {
    currentTab = completed;
    const bodyId = completed ? 'completedSessionsBody' : 'activeSessionsBody';
    const tbody = $('#' + bodyId);
    
    tbody.html('<tr><td colspan="' + (completed ? '11' : '8') + '" class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Загрузка...</span></div></td></tr>');
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/sessions.php?action=list&completed=' + completed,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderSessionsTable(response.data, bodyId, completed);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке сессий');
        }
    });
}

// Отображение таблицы сессий
function renderSessionsTable(sessions, bodyId, isCompleted) {
    const tbody = $('#' + bodyId);
    tbody.empty();
    
    if (sessions.length === 0) {
        const colspan = isCompleted ? 11 : 8;
        tbody.html(`<tr><td colspan="${colspan}" class="text-center text-muted">Сессии не найдены</td></tr>`);
        return;
    }
    
    sessions.forEach(function(session) {
        let row;
        
        if (isCompleted) {
            const fcrDisplay = session.fcr ? session.fcr.toFixed(4) : '-';
            row = `
                <tr>
                    <td>${session.id}</td>
                    <td>${escapeHtml(session.name)}</td>
                    <td>${escapeHtml(session.pool_name)}</td>
                    <td>${escapeHtml(session.planting_name)}</td>
                    <td>${session.start_date}</td>
                    <td>${session.end_date || '-'}</td>
                    <td>${session.start_mass}</td>
                    <td>${session.end_mass || '-'}</td>
                    <td>${session.feed_amount || '-'}</td>
                    <td>${fcrDisplay}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="openEditModal(${session.id}, true)" title="Редактировать">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteSession(${session.id})" title="Удалить">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        } else {
            row = `
                <tr>
                    <td>${session.id}</td>
                    <td>${escapeHtml(session.name)}</td>
                    <td>${escapeHtml(session.pool_name)}</td>
                    <td>${escapeHtml(session.planting_name)}</td>
                    <td>${session.start_date}</td>
                    <td>${session.start_mass}</td>
                    <td>${session.start_fish_count}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="openEditModal(${session.id})" title="Редактировать">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-success" onclick="openCompleteModal(${session.id})" title="Завершить">
                            <i class="bi bi-check-circle"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteSession(${session.id})" title="Удалить">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }
        
        tbody.append(row);
    });
}

// Открыть модальное окно для добавления
function openAddModal() {
    currentEditId = null;
    $('#sessionModalTitle').text('Добавить сессию');
    $('#sessionForm')[0].reset();
    $('#sessionId').val('');
    $('#sessionStartDate').val(new Date().toISOString().split('T')[0]);
    $('#sessionPreviousFcr').val('');
    loadSelectOptions();
}

// Открыть модальное окно для редактирования
function openEditModal(id, isCompleted = false) {
    currentEditId = id;
    $('#sessionModalTitle').text('Редактировать сессию');
    loadSelectOptions();
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/sessions.php?action=get&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const session = response.data;
                $('#sessionId').val(session.id);
                $('#sessionName').val(session.name);
                $('#sessionPool').val(session.pool_id);
                $('#sessionPlanting').val(session.planting_id);
                $('#sessionStartDate').val(session.start_date);
                $('#sessionStartMass').val(session.start_mass);
                $('#sessionStartFishCount').val(session.start_fish_count);
                $('#sessionPreviousFcr').val(session.previous_fcr || '');
                
                // Если сессия завершена, показываем модальное окно завершения
                if (isCompleted) {
                    $('#completeSessionId').val(session.id);
                    $('#completeEndDate').val(session.end_date || new Date().toISOString().split('T')[0]);
                    $('#completeEndMass').val(session.end_mass || '');
                    $('#completeFeedAmount').val(session.feed_amount || '');
                    const completeModal = new bootstrap.Modal(document.getElementById('completeModal'));
                    completeModal.show();
                } else {
                    const modal = new bootstrap.Modal(document.getElementById('sessionModal'));
                    modal.show();
                }
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке данных сессии');
        }
    });
}

// Открыть модальное окно для завершения сессии
function openCompleteModal(id) {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/sessions.php?action=get&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const session = response.data;
                $('#completeSessionId').val(session.id);
                $('#completeEndDate').val(new Date().toISOString().split('T')[0]);
                $('#completeEndMass').val('');
                $('#completeFeedAmount').val('');
                
                const modal = new bootstrap.Modal(document.getElementById('completeModal'));
                modal.show();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке данных сессии');
        }
    });
}

// Сохранить сессию
function saveSession() {
    const form = $('#sessionForm')[0];
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = {
        name: $('#sessionName').val().trim(),
        pool_id: parseInt($('#sessionPool').val()),
        planting_id: parseInt($('#sessionPlanting').val()),
        start_date: $('#sessionStartDate').val(),
        start_mass: parseFloat($('#sessionStartMass').val()),
        start_fish_count: parseInt($('#sessionStartFishCount').val()),
        previous_fcr: $('#sessionPreviousFcr').val() ? parseFloat($('#sessionPreviousFcr').val()) : null
    };
    
    if (currentEditId) {
        formData.id = currentEditId;
    }
    
    const action = currentEditId ? 'update' : 'create';
    const url = '<?php echo BASE_URL; ?>api/sessions.php?action=' + action;
    
    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#sessionModal').modal('hide');
                loadSessions(currentTab);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении сессии');
        }
    });
}

// Завершить сессию
function completeSession() {
    const form = $('#completeForm')[0];
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = {
        id: parseInt($('#completeSessionId').val()),
        end_date: $('#completeEndDate').val(),
        end_mass: parseFloat($('#completeEndMass').val()),
        feed_amount: parseFloat($('#completeFeedAmount').val())
    };
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/sessions.php?action=complete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message + (response.fcr ? ` (FCR: ${response.fcr.toFixed(4)})` : ''));
                $('#completeModal').modal('hide');
                loadSessions(0); // Перезагружаем действующие
                // Переключаемся на завершенные
                setTimeout(() => {
                    document.getElementById('completed-tab').click();
                    loadSessions(1);
                }, 500);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при завершении сессии');
        }
    });
}

// Удалить сессию
function deleteSession(id) {
    if (!confirm('Вы уверены, что хотите удалить эту сессию?')) {
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/sessions.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({id: id}),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadSessions(currentTab);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении сессии');
        }
    });
}

// Показать уведомление
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('#alert-container').html(alertHtml);
    
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
}

// Экранирование HTML
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Загрузка при открытии страницы
$(document).ready(function() {
    loadSelectOptions();
    loadSessions(0);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
