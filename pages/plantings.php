<?php
/**
 * Страница управления посадками
 * Доступна только администраторам
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';

$page_title = 'Управление посадками';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Управление посадками</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#plantingModal" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Добавить посадку
            </button>
        </div>
    </div>
    
    <?php renderSectionDescription('plantings'); ?>
    
    <div id="alert-container"></div>
    
    <!-- Табы -->
    <ul class="nav nav-tabs mb-3" id="plantingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" onclick="loadPlantings(0)">
                Действующие
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="archived-tab" data-bs-toggle="tab" data-bs-target="#archived" type="button" role="tab" onclick="loadPlantings(1)">
                Архивные
            </button>
        </li>
    </ul>
    
    <!-- Содержимое табов -->
    <div class="tab-content" id="plantingsTabContent">
        <!-- Действующие посадки -->
        <div class="tab-pane fade show active" id="active" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="activePlantingsTable">
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
        
        <!-- Архивные посадки -->
        <div class="tab-pane fade" id="archived" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="archivedPlantingsTable">
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

<!-- Модальное окно для добавления/редактирования посадки -->
<div class="modal fade" id="plantingModal" tabindex="-1">
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
                            <label for="plantingFishCount" class="form-label">Количество рыб <span class="text-danger">*</span></label>
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
                    
                    <!-- Загрузка файлов (только при редактировании) -->
                    <div id="filesSection" style="display: none;">
                        <label class="form-label">Сопроводительные документы</label>
                        
                        <!-- Область для drag-n-drop -->
                        <div id="dropZone" class="border border-dashed rounded p-4 text-center mb-3" style="min-height: 150px; cursor: pointer;">
                            <i class="bi bi-cloud-upload" style="font-size: 3rem;"></i>
                            <p class="mt-2 mb-0">Перетащите файлы сюда или нажмите для выбора</p>
                            <small class="text-muted">Можно загрузить несколько файлов (максимум 10 МБ каждый)</small>
                            <input type="file" id="fileInput" multiple style="display: none;">
                        </div>
                        
                        <!-- Список загруженных файлов -->
                        <div id="filesList" class="mb-3"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="savePlanting()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentEditId = null;
let currentFiles = [];
let currentTab = 0;

// Загрузка посадок
function loadPlantings(archived = 0) {
    currentTab = archived;
    const bodyId = archived ? 'archivedPlantingsBody' : 'activePlantingsBody';
    const tbody = $('#' + bodyId);
    
    tbody.html('<tr><td colspan="10" class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Загрузка...</span></div></td></tr>');
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/plantings.php?action=list&archived=' + archived,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderPlantingsTable(response.data, bodyId);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке посадок');
        }
    });
}

// Отображение таблицы посадок
function renderPlantingsTable(plantings, bodyId) {
    const tbody = $('#' + bodyId);
    tbody.empty();
    
    if (plantings.length === 0) {
        tbody.html('<tr><td colspan="10" class="text-center text-muted">Посадки не найдены</td></tr>');
        return;
    }
    
    plantings.forEach(function(planting) {
        const price = planting.price ? formatCurrency(planting.price) : '-';
        const filesCount = planting.files_count || 0;
        const filesBadge = filesCount > 0 
            ? `<span class="badge bg-info">${filesCount} файл${filesCount > 1 ? 'ов' : ''}</span>`
            : '<span class="text-muted">-</span>';
        
        const row = `
            <tr>
                <td>${planting.id}</td>
                <td>${escapeHtml(planting.name)}</td>
                <td>${escapeHtml(planting.fish_breed)}</td>
                <td>${planting.planting_date}</td>
                <td>${planting.fish_count}</td>
                <td>${planting.biomass_weight || '-'}</td>
                <td>${escapeHtml(planting.supplier || '-')}</td>
                <td>${price}</td>
                <td>${filesBadge}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="openEditModal(${planting.id})" title="Редактировать">
                        <i class="bi bi-pencil"></i>
                    </button>
                    ${currentTab === 0 
                        ? `<button class="btn btn-sm btn-warning" onclick="archivePlanting(${planting.id}, 1)" title="Архивировать">
                            <i class="bi bi-archive"></i>
                        </button>`
                        : `<button class="btn btn-sm btn-success" onclick="archivePlanting(${planting.id}, 0)" title="Разархивировать">
                            <i class="bi bi-archive"></i>
                        </button>`
                    }
                    <button class="btn btn-sm btn-danger" onclick="deletePlanting(${planting.id})" title="Удалить">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

// Открыть модальное окно для добавления
function openAddModal() {
    currentEditId = null;
    currentFiles = [];
    $('#plantingModalTitle').text('Добавить посадку');
    $('#plantingForm')[0].reset();
    $('#plantingId').val('');
    $('#filesSection').hide();
    $('#filesList').empty();
}

// Открыть модальное окно для редактирования
function openEditModal(id) {
    currentEditId = id;
    currentFiles = [];
    $('#plantingModalTitle').text('Редактировать посадку');
    $('#filesSection').show();
    $('#filesList').empty();
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/plantings.php?action=get&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const planting = response.data;
                $('#plantingId').val(planting.id);
                $('#plantingName').val(planting.name);
                $('#plantingFishBreed').val(planting.fish_breed);
                $('#plantingHatchDate').val(planting.hatch_date || '');
                $('#plantingDate').val(planting.planting_date);
                $('#plantingFishCount').val(planting.fish_count);
                $('#plantingBiomassWeight').val(planting.biomass_weight || '');
                $('#plantingSupplier').val(planting.supplier || '');
                $('#plantingPrice').val(planting.price || '');
                $('#plantingDeliveryCost').val(planting.delivery_cost || '');
                
                // Отображение файлов
                if (planting.files && planting.files.length > 0) {
                    currentFiles = planting.files;
                    renderFilesList(planting.files);
                }
                
                const modal = new bootstrap.Modal(document.getElementById('plantingModal'));
                modal.show();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке данных посадки');
        }
    });
}

// Отображение списка файлов
function renderFilesList(files) {
    const filesList = $('#filesList');
    filesList.empty();
    
    if (files.length === 0) {
        return;
    }
    
    files.forEach(function(file) {
        const fileSize = formatFileSize(file.file_size);
        const fileItem = `
            <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2" data-file-id="${file.id}">
                <div>
                    <i class="bi bi-file-earmark"></i>
                    <a href="<?php echo BASE_URL; ?>api/download_file.php?id=${file.id}" target="_blank" class="text-decoration-none ms-1">
                        ${escapeHtml(file.original_name)}
                    </a>
                    <small class="text-muted">(${fileSize})</small>
                </div>
                <button class="btn btn-sm btn-danger" onclick="deleteFile(${file.id}, this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        filesList.append(fileItem);
    });
}

// Сохранить посадку
function savePlanting() {
    const form = $('#plantingForm')[0];
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = {
        name: $('#plantingName').val().trim(),
        fish_breed: $('#plantingFishBreed').val().trim(),
        hatch_date: $('#plantingHatchDate').val() || null,
        planting_date: $('#plantingDate').val(),
        fish_count: parseInt($('#plantingFishCount').val()),
        biomass_weight: $('#plantingBiomassWeight').val() ? parseFloat($('#plantingBiomassWeight').val()) : null,
        supplier: $('#plantingSupplier').val().trim() || null,
        price: $('#plantingPrice').val() ? parseFloat($('#plantingPrice').val()) : null,
        delivery_cost: $('#plantingDeliveryCost').val() ? parseFloat($('#plantingDeliveryCost').val()) : null
    };
    
    if (currentEditId) {
        formData.id = currentEditId;
    }
    
    const action = currentEditId ? 'update' : 'create';
    const url = '<?php echo BASE_URL; ?>api/plantings.php?action=' + action;
    
    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#plantingModal').modal('hide');
                loadPlantings(currentTab);
                
                // Если это создание, открываем редактирование для загрузки файлов
                if (!currentEditId && response.id) {
                    setTimeout(() => {
                        openEditModal(response.id);
                    }, 500);
                }
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении посадки');
        }
    });
}

// Удалить посадку
function deletePlanting(id) {
    if (!confirm('Вы уверены, что хотите удалить эту посадку? Все связанные файлы также будут удалены.')) {
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/plantings.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({id: id}),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadPlantings(currentTab);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении посадки');
        }
    });
}

// Архивировать/разархивировать посадку
function archivePlanting(id, isArchived) {
    const action = isArchived ? 'архивировать' : 'разархивировать';
    if (!confirm(`Вы уверены, что хотите ${action} эту посадку?`)) {
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/plantings.php?action=archive',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({id: id, is_archived: isArchived}),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadPlantings(currentTab);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при изменении статуса посадки');
        }
    });
}

// Удалить файл
function deleteFile(fileId, button) {
    if (!confirm('Вы уверены, что хотите удалить этот файл?')) {
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/plantings.php?action=delete_file',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({file_id: fileId}),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $(button).closest('[data-file-id]').remove();
                showAlert('success', response.message);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении файла');
        }
    });
}

// Drag-n-drop для файлов
$(document).ready(function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    
    if (!dropZone || !fileInput) return;
    
    // Клик по области
    dropZone.addEventListener('click', () => {
        if (currentEditId) {
            fileInput.click();
        }
    });
    
    // Drag events
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-primary');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-primary');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-primary');
        
        if (!currentEditId) {
            showAlert('warning', 'Сначала сохраните посадку, затем загрузите файлы');
            return;
        }
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            uploadFiles(files);
        }
    });
    
    // Выбор файлов через input
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            uploadFiles(e.target.files);
        }
    });
});

// Загрузка файлов
function uploadFiles(files) {
    if (!currentEditId) {
        showAlert('warning', 'Сначала сохраните посадку');
        return;
    }
    
    const formData = new FormData();
    formData.append('planting_id', currentEditId);
    
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/plantings_upload.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (response.uploaded && response.uploaded.length > 0) {
                    // Обновляем список файлов
                    openEditModal(currentEditId);
                    showAlert('success', `Загружено файлов: ${response.uploaded.length}`);
                }
                if (response.errors && response.errors.length > 0) {
                    showAlert('warning', response.errors.join('<br>'));
                }
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при загрузке файлов');
        }
    });
}

// Форматирование валюты
function formatCurrency(amount) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0
    }).format(amount);
}

// Форматирование размера файла
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Показать уведомление
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
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
    loadPlantings(0);
});
</script>

<style>
#dropZone {
    transition: all 0.3s;
}

#dropZone:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

[data-theme="dark"] #dropZone {
    border-color: #404040 !important;
}

[data-theme="dark"] #dropZone:hover {
    background-color: rgba(102, 126, 234, 0.1);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
