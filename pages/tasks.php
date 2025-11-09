<?php
/**
 * Страница задач
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
// Устанавливаем заголовок страницы до вывода контента
$page_title = 'Задачи';

// Требуем авторизацию до вывода заголовков
requireAuth();

$isAdmin = isAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';
?>
<link rel="stylesheet" href="<?php echo asset_url('assets/css/tasks.css'); ?>">
<!-- SortableJS для drag-n-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1>Задачи</h1>
                <?php if ($isAdmin): ?>
                    <button type="button" class="btn btn-primary" onclick="openTaskModal()">
                        <i class="bi bi-plus-circle"></i> Создать задачу
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php renderSectionDescription('tasks'); ?>
    
    <div id="alert-container"></div>
    
    <!-- Табы -->
    <ul class="nav nav-tabs mb-4" id="tasksTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="my-tasks-tab" data-bs-toggle="tab" data-bs-target="#my-tasks" type="button" role="tab">
                Мои задачи
            </button>
        </li>
        <?php if ($isAdmin): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="assigned-tasks-tab" data-bs-toggle="tab" data-bs-target="#assigned-tasks" type="button" role="tab">
                Я поставил
            </button>
        </li>
        <?php endif; ?>
    </ul>
    
    <!-- Содержимое табов -->
    <div class="tab-content" id="tasksTabContent">
        <div class="tab-pane fade show active" id="my-tasks" role="tabpanel">
            <div id="myTasksList" class="tasks-list">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($isAdmin): ?>
        <div class="tab-pane fade" id="assigned-tasks" role="tabpanel">
            <div id="assignedTasksList" class="tasks-list">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/task_card.php'; ?>

<!-- Модальное окно для просмотра деталей задачи -->
<div class="modal fade" id="taskDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskDetailsTitle">Детали задачи</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="taskDetailsDescription" class="mb-3"></div>
                
                <div class="task-details-meta mb-3">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted d-block">Ответственный</small>
                            <span id="taskDetailsAssigned" class="task-meta-value"></span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Создал</small>
                            <span id="taskDetailsCreated" class="task-meta-value"></span>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <small class="text-muted d-block">Срок</small>
                            <span id="taskDetailsDueDate" class="task-meta-value"></span>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Чеклист</h6>
                        <div id="taskDetailsChecklist"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6>Файлы</h6>
                    <div id="taskDetailsFiles"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для создания/редактирования задачи -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalTitle">Создать задачу</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="taskForm">
                <div class="modal-body">
                    <input type="hidden" id="taskId" name="id">
                    
                    <div class="mb-3">
                        <label for="taskTitle" class="form-label">Название <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="taskTitle" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="taskDescription" class="form-label">Описание</label>
                        <textarea class="form-control" id="taskDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="taskAssignedTo" class="form-label">Ответственный <span class="text-danger">*</span></label>
                        <select class="form-select" id="taskAssignedTo" name="assigned_to" required>
                            <option value="">Выберите ответственного</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="taskDueDate" class="form-label">Срок</label>
                        <input type="date" class="form-control" id="taskDueDate" name="due_date">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Чеклист</label>
                        <div id="taskItemsList" class="task-items-list">
                            <!-- Элементы чеклиста будут здесь -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addTaskItem()">
                            <i class="bi bi-plus"></i> Добавить пункт
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Файлы</label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <input type="file" id="taskFiles" name="files[]" multiple style="display: none;" onchange="handleFileSelect(event)">
                            <div class="file-upload-dropzone" onclick="document.getElementById('taskFiles').click()" ondrop="handleFileDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                                <i class="bi bi-cloud-upload"></i>
                                <p class="mb-0">Перетащите файлы сюда или нажмите для выбора</p>
                            </div>
                            <div id="taskFilesList" class="task-files-list mt-2"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
let usersList = [];
let taskFiles = []; // Файлы для загрузки
let currentTab = 'my';
let taskCardTemplateHtml = '';

// Загрузка списка пользователей (для админов)
function loadUsers() {
    if (!isAdmin) return;
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks.php?action=get_users',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                usersList = response.data;
                const select = $('#taskAssignedTo');
                select.empty().append('<option value="">Выберите ответственного</option>');
                usersList.forEach(function(user) {
                    select.append(`<option value="${user.id}">${escapeHtml(user.full_name || user.login)}</option>`);
                });
            }
        }
    });
}

// Загрузка задач
function loadTasks(tab) {
    const container = tab === 'my' ? $('#myTasksList') : $('#assignedTasksList');
    currentTab = tab;
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks.php?action=list&tab=' + tab,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderTasks(response.data, container);
            } else {
                showAlert('danger', response.message);
                container.html('<div class="alert alert-warning">Ошибка загрузки задач</div>');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке задач');
            container.html('<div class="alert alert-danger">Ошибка при загрузке данных</div>');
        }
    });
}

// Отображение списка задач
function renderTasks(tasks, container) {
    if (tasks.length === 0) {
        container.html('<div class="alert alert-info">Нет задач</div>');
        return;
    }
    
    let html = '<div class="tasks-grid">';
    
    tasks.forEach(function(task) {
        html += renderTaskCard(task);
    });
    
    html += '</div>';
    container.html(html);
}

function renderTaskCard(task) {
    if (!taskCardTemplateHtml) {
        taskCardTemplateHtml = $('#taskCardTemplate').html();
    }
    
    let template = taskCardTemplateHtml;
    const completedClass = task.is_completed ? 'task-completed' : '';
    const checkboxChecked = task.is_completed ? 'checked' : '';
    const descriptionHtml = task.description ? `<div class="task-description">${escapeHtml(task.description)}</div>` : '';
    const assignedName = escapeHtml(task.assigned_to_name || task.assigned_to_login || '');
    const dueDateClass = task.due_date ? (new Date(task.due_date) < new Date() && !task.is_completed ? 'text-danger' : 'text-muted') : 'text-muted';
    const dueDateText = task.due_date ? formatDate(task.due_date) : 'Без срока';
    let progressHtml = '';
    
    if (task.items_count > 0) {
        const progress = Math.round((task.items_completed_count / task.items_count) * 100);
        progressHtml = `
            <div class="task-progress">
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar" role="progressbar" style="width: ${progress}%"></div>
                </div>
                <small class="text-muted">${task.items_completed_count} / ${task.items_count}</small>
            </div>
        `;
    }
    
    const editButtonHtml = isAdmin ? `<button type="button" class="btn btn-sm btn-outline-secondary" onclick="editTask(${task.id})" title="Редактировать"><i class="bi bi-pencil"></i></button>` : '';
    const actionsHtml = isAdmin ? `
        <div class="task-actions mt-2">
            <button type="button" class="btn btn-sm btn-danger" onclick="deleteTask(${task.id})">
                <i class="bi bi-trash"></i> Удалить
            </button>
        </div>
    ` : '';
    
    template = template
        .replace(/{{card_class}}/g, completedClass)
        .replace(/{{task_id}}/g, task.id)
        .replace(/{{checkbox_checked}}/g, checkboxChecked)
        .replace(/{{task_title}}/g, escapeHtml(task.title))
        .replace(/{{edit_button_html}}/g, editButtonHtml)
        .replace(/{{description_html}}/g, descriptionHtml)
        .replace(/{{assigned_name}}/g, assignedName)
        .replace(/{{due_date_class}}/g, dueDateClass)
        .replace(/{{due_date_text}}/g, dueDateText)
        .replace(/{{progress_html}}/g, progressHtml)
        .replace(/{{actions_html}}/g, actionsHtml);
    
    return template;
}

// Просмотр задачи
let taskDetailsSortable = null;
let currentTaskId = null;

function viewTask(taskId) {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks.php?action=get&id=' + taskId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showTaskDetails(response.data);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке задачи');
        }
    });
}

// Показать детали задачи
function showTaskDetails(data) {
    const task = data.task;
    const items = data.items || [];
    const files = data.files || [];
    
    currentTaskId = task.id;
    
    $('#taskDetailsTitle').text(escapeHtml(task.title));
    
    // Описание - просто текст, если есть
    const descriptionContainer = $('#taskDetailsDescription');
    if (task.description && task.description.trim()) {
        descriptionContainer.html(escapeHtml(task.description).replace(/\n/g, '<br>'));
    } else {
        descriptionContainer.html('');
    }
    
    $('#taskDetailsAssigned').text(escapeHtml(task.assigned_to_name || task.assigned_to_login));
    $('#taskDetailsCreated').text(escapeHtml(task.created_by_name || task.created_by_login));
    $('#taskDetailsDueDate').html(task.due_date ? formatDate(task.due_date) : '<em class="text-muted">Без срока</em>');
    
    // Чеклист
    const checklistContainer = $('#taskDetailsChecklist');
    if (items.length > 0) {
        let checklistHtml = '<ul class="task-checklist" id="taskDetailsChecklistList">';
        items.forEach(function(item) {
            checklistHtml += `
                <li class="task-checklist-item ${item.is_completed ? 'completed' : ''}" data-item-id="${item.id}">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-grip-vertical text-muted me-2" style="cursor: move;"></i>
                        <div class="form-check flex-grow-1">
                            <input class="form-check-input" type="checkbox" ${item.is_completed ? 'checked' : ''} 
                                   onchange="toggleTaskItemComplete(${item.id}, this.checked)">
                            <label class="form-check-label">${escapeHtml(item.title)}</label>
                        </div>
                    </div>
                </li>
            `;
        });
        checklistHtml += '</ul>';
        checklistContainer.html(checklistHtml);
        
        // Инициализация Sortable для чеклиста
        if (taskDetailsSortable) {
            taskDetailsSortable.destroy();
        }
        const checklistList = document.getElementById('taskDetailsChecklistList');
        if (checklistList) {
            taskDetailsSortable = new Sortable(checklistList, {
                handle: '.bi-grip-vertical',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    saveTaskItemsOrder();
                }
            });
        }
    } else {
        checklistContainer.html('<em class="text-muted">Нет элементов чеклиста</em>');
    }
    
    // Файлы
    const filesContainer = $('#taskDetailsFiles');
    if (files.length > 0) {
        let filesHtml = '<ul class="list-unstyled">';
        files.forEach(function(file) {
            filesHtml += `
                <li class="mb-2">
                    <a href="<?php echo BASE_URL; ?>api/download_task_file.php?id=${file.id}" target="_blank" class="text-decoration-none">
                        <i class="bi bi-file-earmark"></i> ${escapeHtml(file.original_name)}
                    </a>
                    <small class="text-muted ms-2">(${formatFileSize(file.file_size)})</small>
                </li>
            `;
        });
        filesHtml += '</ul>';
        filesContainer.html(filesHtml);
    } else {
        filesContainer.html('<em class="text-muted">Нет файлов</em>');
    }
    
    const modal = new bootstrap.Modal(document.getElementById('taskDetailsModal'));
    modal.show();
}

// Сохранение порядка элементов чеклиста
function saveTaskItemsOrder() {
    if (!currentTaskId) return;
    
    const items = [];
    $('#taskDetailsChecklistList .task-checklist-item').each(function(index) {
        const itemId = $(this).data('item-id');
        if (itemId) {
            items.push({
                id: itemId,
                sort_order: index
            });
        }
    });
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks.php?action=update_items_order',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            task_id: currentTaskId,
            items: items
        }),
        dataType: 'json',
        success: function(response) {
            // Обновляем список задач, если нужно
            if (currentTab) {
                loadTasks(currentTab);
            }
        },
        error: function() {
            // В случае ошибки просто перезагружаем задачи
            if (currentTab) {
                loadTasks(currentTab);
            }
        }
    });
}

// Открыть модальное окно для создания задачи
function openTaskModal(taskId = null) {
    if (!isAdmin) return;
    
    $('#taskForm')[0].reset();
    $('#taskId').val(taskId || '');
    $('#taskItemsList').empty();
    $('#taskFilesList').empty();
    taskFiles = [];
    
    if (taskItemsSortable) {
        taskItemsSortable.destroy();
        taskItemsSortable = null;
    }
    
    if (taskId) {
        $('#taskModalTitle').text('Редактировать задачу');
        loadTaskForEdit(taskId);
    } else {
        $('#taskModalTitle').text('Создать задачу');
        addTaskItem(); // Добавляем один пустой элемент чеклиста
    }
    
    const modal = new bootstrap.Modal(document.getElementById('taskModal'));
    modal.show();
}

// Загрузить задачу для редактирования
function loadTaskForEdit(taskId) {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks.php?action=get&id=' + taskId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const task = response.data.task;
                $('#taskTitle').val(task.title);
                $('#taskDescription').val(task.description || '');
                $('#taskAssignedTo').val(task.assigned_to);
                $('#taskDueDate').val(task.due_date || '');
                
                // Загружаем элементы чеклиста
                $('#taskItemsList').empty();
                if (taskItemsSortable) {
                    taskItemsSortable.destroy();
                    taskItemsSortable = null;
                }
                if (response.data.items && response.data.items.length > 0) {
                    response.data.items.forEach(function(item) {
                        addTaskItem(item.title, item.is_completed, item.id);
                    });
                } else {
                    addTaskItem();
                }
                
                // Показываем существующие файлы
                if (response.data.files && response.data.files.length > 0) {
                    response.data.files.forEach(function(file) {
                        addFileToList(file.original_name, file.file_size, file.id, true);
                    });
                }
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке задачи');
        }
    });
}

// Редактировать задачу
function editTask(taskId) {
    openTaskModal(taskId);
}

// Добавить элемент чеклиста
let taskItemsSortable = null;

function addTaskItem(title = '', isCompleted = false, itemId = null) {
    const index = $('#taskItemsList .task-item').length;
    const sanitizedTitle = escapeHtml(title);
    const itemHtml = `
        <div class="task-item mb-2" data-item-index="${index}">
            <div class="input-group">
                <span class="input-group-text" style="cursor: move;">
                    <i class="bi bi-grip-vertical text-muted"></i>
                </span>
                <div class="input-group-text">
                    <input class="form-check-input" type="checkbox" ${isCompleted ? 'checked' : ''} disabled>
                </div>
                <input type="text" class="form-control task-item-input" value="${sanitizedTitle}" placeholder="Название пункта">
                <button type="button" class="btn btn-sm btn-outline-secondary task-item-remove-btn" onclick="removeTaskItem(this)" title="Удалить пункт">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    `;
    const $item = $(itemHtml);
    if (itemId) {
        $item.attr('data-item-id', itemId);
    }
    $('#taskItemsList').append($item);
    initTaskItemsSortable();
    return $item.find('.task-item-input');
}

// Инициализация Sortable для элементов чеклиста в форме
function initTaskItemsSortable() {
    if (taskItemsSortable) {
        taskItemsSortable.destroy();
    }
    const itemsList = document.getElementById('taskItemsList');
    if (itemsList) {
        taskItemsSortable = new Sortable(itemsList, {
            handle: '.bi-grip-vertical',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag'
        });
    }
}

// Удалить элемент чеклиста
function removeTaskItem(button) {
    $(button).closest('.task-item').remove();
}

// Навигация по чеклисту с помощью Tab
$('#taskItemsList').on('keydown', '.task-item-input', function(event) {
    if (event.key !== 'Tab' || event.shiftKey) {
        return;
    }

    event.preventDefault();

    const inputs = $('#taskItemsList .task-item-input');
    const currentIndex = inputs.index(this);

    if (currentIndex >= 0 && currentIndex < inputs.length - 1) {
        inputs.eq(currentIndex + 1).focus();
    } else {
        const newInput = addTaskItem();
        // Фокусируемся на вновь созданном поле после добавления
        setTimeout(function() {
            newInput.trigger('focus');
        }, 0);
    }
});

// Обработка выбора файлов
function handleFileSelect(event) {
    const files = Array.from(event.target.files);
    files.forEach(function(file) {
        addFileToList(file.name, file.size, null, false, file);
    });
}

// Обработка drag-n-drop
function handleFileDrop(event) {
    event.preventDefault();
    event.stopPropagation();
    $(event.currentTarget).removeClass('drag-over');
    
    const files = Array.from(event.dataTransfer.files);
    files.forEach(function(file) {
        addFileToList(file.name, file.size, null, false, file);
    });
}

function handleDragOver(event) {
    event.preventDefault();
    event.stopPropagation();
    $(event.currentTarget).addClass('drag-over');
}

function handleDragLeave(event) {
    event.preventDefault();
    event.stopPropagation();
    $(event.currentTarget).removeClass('drag-over');
}

// Добавить файл в список
function addFileToList(fileName, fileSize, fileId, isExisting, file = null) {
    const fileIdAttr = fileId ? `data-file-id="${fileId}"` : '';
    const isExistingAttr = isExisting ? 'data-existing="true"' : '';
    const fileHtml = `
        <div class="task-file-item" ${fileIdAttr} ${isExistingAttr}>
            <i class="bi bi-file-earmark"></i> ${escapeHtml(fileName)}
            <small class="text-muted">(${formatFileSize(fileSize)})</small>
            ${!isExisting ? '<button type="button" class="btn btn-sm btn-link text-danger" onclick="removeTaskFile(this)"><i class="bi bi-x"></i></button>' : ''}
        </div>
    `;
    $('#taskFilesList').append(fileHtml);
    
    if (file) {
        taskFiles.push(file);
    }
}

// Удалить файл из списка
function removeTaskFile(button) {
    $(button).closest('.task-file-item').remove();
    // Также нужно удалить из taskFiles массива
}

// Сохранение задачи
$('#taskForm').on('submit', function(e) {
    e.preventDefault();
    
    const taskId = $('#taskId').val();
    const formData = {
        title: $('#taskTitle').val(),
        description: $('#taskDescription').val(),
        assigned_to: parseInt($('#taskAssignedTo').val()),
        due_date: $('#taskDueDate').val() || null
    };
    
    // Собираем элементы чеклиста
    const items = [];
    $('#taskItemsList .task-item').each(function(index) {
        const $item = $(this);
        const title = $item.find('.task-item-input').val().trim();
        if (!title) {
            return;
        }
        const itemIdRaw = $item.attr('data-item-id');
        const parsedId = itemIdRaw !== undefined ? parseInt(itemIdRaw, 10) : NaN;
        const itemId = isNaN(parsedId) ? null : parsedId;
        const isCompleted = $item.find('.form-check-input').is(':checked');
        items.push({
            id: itemId,
            title: title,
            is_completed: isCompleted,
            sort_order: index
        });
    });
    formData.items = items;
    
    if (taskId) {
        formData.id = parseInt(taskId);
    }
    
    // Отправка данных задачи
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks.php?action=' + (taskId ? 'update' : 'create'),
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Если есть файлы для загрузки, загружаем их
                if (taskFiles.length > 0) {
                    const taskIdForFiles = taskId || response.data.id;
                    uploadTaskFiles(taskIdForFiles);
                } else {
                    showAlert('success', response.message);
                    $('#taskModal').modal('hide');
                    loadTasks(currentTab);
                }
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении задачи');
        }
    });
});

// Загрузка файлов задачи
function uploadTaskFiles(taskId) {
    const formData = new FormData();
    taskFiles.forEach(function(file) {
        formData.append('files[]', file);
    });
    formData.append('task_id', taskId);
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks_upload.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', 'Задача и файлы успешно сохранены');
                $('#taskModal').modal('hide');
                loadTasks(currentTab);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('warning', 'Задача сохранена, но возникла ошибка при загрузке файлов');
            $('#taskModal').modal('hide');
            loadTasks(currentTab);
        }
    });
}

// Переключить статус задачи
function toggleTaskComplete(taskId, isCompleted) {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks.php?action=complete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            id: taskId,
            is_completed: isCompleted
        }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadTasks(currentTab);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при обновлении задачи');
        }
    });
}

// Переключить статус элемента чеклиста
function toggleTaskItemComplete(itemId, isCompleted) {
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks.php?action=complete_item',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            id: itemId,
            is_completed: isCompleted
        }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadTasks(currentTab);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при обновлении элемента');
        }
    });
}

// Удалить задачу
function deleteTask(taskId) {
    if (!confirm('Вы уверены, что хотите удалить эту задачу?')) {
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>api/tasks.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id: taskId }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadTasks(currentTab);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении задачи');
        }
    });
}

// Вспомогательные функции
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

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
    taskCardTemplateHtml = $('#taskCardTemplate').html();
    loadUsers();
    loadTasks('my');
    
    // Обработка переключения табов
    $('#tasksTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        const target = $(e.target).data('bs-target');
        if (target === '#my-tasks') {
            loadTasks('my');
        } else if (target === '#assigned-tasks') {
            loadTasks('assigned');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

