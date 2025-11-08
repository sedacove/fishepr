<?php
/**
 * Страница просмотра логов действий
 * Доступна только администраторам
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';

$page_title = 'Логи действий';
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

<script>
let currentPage = 1;
let totalPages = 1;
let currentFilters = {};

// Загрузка логов
function loadLogs(page = 1) {
    currentPage = page;
    
    const params = new URLSearchParams({
        page: page,
        per_page: 50,
        ...currentFilters
    });
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/logs.php?' + params.toString(),
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderLogsTable(response.data);
                renderPagination(response.pagination);
                
                // Заполнение фильтров
                if (response.filters) {
                    fillFilterOptions(response.filters);
                }
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке логов');
        }
    });
}

// Отображение таблицы логов
function renderLogsTable(logs) {
    const tbody = $('#logsTableBody');
    tbody.empty();
    
    if (logs.length === 0) {
        tbody.html('<tr><td colspan="8" class="text-center text-muted">Логи не найдены</td></tr>');
        return;
    }
    
    logs.forEach(function(log) {
        const userBadge = log.user_type === 'admin'
            ? '<span class="badge bg-danger">Админ</span> '
            : '';
        
        const userInfo = log.user_full_name 
            ? `${userBadge}${escapeHtml(log.user_full_name)} (${escapeHtml(log.user_login)})`
            : `${userBadge}${escapeHtml(log.user_login || 'Неизвестно')}`;
        
        const actionBadge = getActionBadge(log.action);
        const entityBadge = getEntityBadge(log.entity_type);
        
        const hasChanges = log.changes && log.changes.trim() !== '';
        const row = `
            <tr class="${hasChanges ? 'log-row-clickable' : ''}" ${hasChanges ? `onclick="showLogChanges(${log.id})" style="cursor: pointer;"` : ''} data-log-id="${log.id}" data-changes="${hasChanges ? escapeHtml(log.changes).replace(/"/g, '&quot;') : ''}">
                <td>${log.id}</td>
                <td>
                    <div>${log.date}</div>
                    <small class="text-muted">${log.time}</small>
                </td>
                <td>${userInfo}</td>
                <td>${actionBadge}</td>
                <td>${entityBadge}</td>
                <td>${log.entity_id || '-'}</td>
                <td>${escapeHtml(log.description || '-')}${hasChanges ? ' <i class="bi bi-info-circle text-primary" title="Есть данные об изменениях"></i>' : ''}</td>
                <td><small class="text-muted">${escapeHtml(log.ip_address || '-')}</small></td>
            </tr>
        `;
        tbody.append(row);
    });
}

// Получить badge для действия
function getActionBadge(action) {
    const badges = {
        'create': '<span class="badge bg-success">Создание</span>',
        'update': '<span class="badge bg-primary">Обновление</span>',
        'delete': '<span class="badge bg-danger">Удаление</span>',
        'login': '<span class="badge bg-info">Вход</span>',
        'logout': '<span class="badge bg-secondary">Выход</span>'
    };
    return badges[action] || `<span class="badge bg-secondary">${escapeHtml(action)}</span>`;
}

// Получить badge для типа сущности
function getEntityBadge(entityType) {
    const badges = {
        'pool': '<span class="badge bg-info">Бассейн</span>',
        'user': '<span class="badge bg-warning">Пользователь</span>',
        'planting': '<span class="badge bg-success">Посадка</span>',
        'planting_file': '<span class="badge bg-secondary">Файл посадки</span>',
        'session': '<span class="badge bg-primary">Сессия</span>',
        'measurement': '<span class="badge bg-info">Замер</span>',
        'setting': '<span class="badge bg-secondary">Настройка</span>',
        'mortality': '<span class="badge bg-danger">Падеж</span>',
        'harvest': '<span class="badge bg-warning">Отбор</span>'
    };
    return badges[entityType] || `<span class="badge bg-secondary">${escapeHtml(entityType || '-')}</span>`;
}

// Отображение пагинации
function renderPagination(pagination) {
    totalPages = pagination.total_pages;
    const paginationEl = $('#pagination');
    paginationEl.empty();
    
    if (totalPages <= 1) {
        $('#paginationContainer').hide();
        return;
    }
    
    $('#paginationContainer').show();
    
    const page = pagination.page;
    const total = pagination.total;
    
    // Информация о записях
    const start = (page - 1) * pagination.per_page + 1;
    const end = Math.min(page * pagination.per_page, total);
    
    paginationEl.append(`
        <li class="page-item disabled">
            <span class="page-link">Показано ${start}-${end} из ${total}</span>
        </li>
    `);
    
    // Кнопка "Предыдущая"
    paginationEl.append(`
        <li class="page-item ${page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadLogs(${page - 1}); return false;">Предыдущая</a>
        </li>
    `);
    
    // Номера страниц
    const maxVisible = 5;
    let startPage = Math.max(1, page - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    if (startPage > 1) {
        paginationEl.append(`
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadLogs(1); return false;">1</a>
            </li>
        `);
        if (startPage > 2) {
            paginationEl.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        paginationEl.append(`
            <li class="page-item ${i === page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadLogs(${i}); return false;">${i}</a>
            </li>
        `);
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationEl.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
        }
        paginationEl.append(`
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadLogs(${totalPages}); return false;">${totalPages}</a>
            </li>
        `);
    }
    
    // Кнопка "Следующая"
    paginationEl.append(`
        <li class="page-item ${page === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadLogs(${page + 1}); return false;">Следующая</a>
        </li>
    `);
}

// Заполнение опций фильтров
function fillFilterOptions(filters) {
    const actionSelect = $('#filterAction');
    const entitySelect = $('#filterEntityType');
    
    if (filters.actions && actionSelect.find('option').length === 1) {
        filters.actions.forEach(function(action) {
            actionSelect.append(`<option value="${escapeHtml(action)}">${escapeHtml(action)}</option>`);
        });
    }
    
    if (filters.entity_types && entitySelect.find('option').length === 1) {
        filters.entity_types.forEach(function(entityType) {
            entitySelect.append(`<option value="${escapeHtml(entityType)}">${escapeHtml(entityType)}</option>`);
        });
    }
}

// Применить фильтры
function applyFilters() {
    currentFilters = {};
    
    const dateFrom = $('#dateFrom').val();
    const dateTo = $('#dateTo').val();
    const action = $('#filterAction').val();
    const entityType = $('#filterEntityType').val();
    
    if (dateFrom) currentFilters.date_from = dateFrom;
    if (dateTo) currentFilters.date_to = dateTo;
    if (action) currentFilters.action = action;
    if (entityType) currentFilters.entity_type = entityType;
    
    loadLogs(1);
}

// Сбросить фильтры
function clearFilters() {
    $('#filterForm')[0].reset();
    currentFilters = {};
    loadLogs(1);
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

// Показать изменения в модальном окне
function showLogChanges(logId) {
    const row = document.querySelector(`tr[data-log-id="${logId}"]`);
    if (!row) return;
    
    const changesJson = row.getAttribute('data-changes');
    if (!changesJson) return;
    
    // Декодируем HTML-сущности
    const decoded = changesJson
        .replace(/&quot;/g, '"')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>');
    
    try {
        // Пытаемся распарсить JSON для красивого отображения
        const parsed = JSON.parse(decoded);
        const formatted = JSON.stringify(parsed, null, 2);
        document.getElementById('changesContent').textContent = formatted;
    } catch (e) {
        // Если не JSON, показываем как есть
        document.getElementById('changesContent').textContent = decoded;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('changesModal'));
    modal.show();
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
    loadLogs(1);
    
    // Применение фильтров по Enter
    $('#filterForm input, #filterForm select').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            applyFilters();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
