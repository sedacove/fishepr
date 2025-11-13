(function() {
    'use strict';

    if (window.__tasksPageInitialized) {
        return;
    }
    window.__tasksPageInitialized = true;

    const config = window.tasksConfig || {};
    const baseUrl = config.baseUrl || '';
    const isAdmin = Boolean(config.isAdmin);

    let usersList = [];
    let taskFiles = [];
    let currentTab = 'my';
    let taskCardTemplateHtml = '';
    let taskDetailsSortable = null;
    let taskItemsSortable = null;
    let currentTaskId = null;

    function loadUsers() {
        if (!isAdmin) {
            return;
        }

        $.ajax({
            url: `${baseUrl}api/tasks.php?action=get_users`,
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

    function loadTasks(tab) {
        const container = tab === 'my' ? $('#myTasksList') : $('#assignedTasksList');
        currentTab = tab;

        $.ajax({
            url: `${baseUrl}api/tasks.php?action=list&tab=` + tab,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const tasksData = response.data ? response.data : response;
                    const tasks = Array.isArray(tasksData.tasks) ? tasksData.tasks : Array.isArray(tasksData) ? tasksData : [];
                    renderTasks(tasks, container);
                } else {
                    showAlert('danger', response.message || 'Ошибка при загрузке задач');
                    container.html('<div class="alert alert-warning">Ошибка загрузки задач</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('loadTasks error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке задач');
                container.html('<div class="alert alert-danger">Ошибка при загрузке данных</div>');
            }
        });
    }

    function renderTasks(tasks, container) {
        if (!tasks.length) {
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
        const dueDateClass = task.due_date && new Date(task.due_date) < new Date() && !task.is_completed ? 'text-danger' : 'text-muted';
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

        const editButtonHtml = isAdmin
            ? `<button type="button" class="btn btn-sm btn-outline-secondary" onclick="editTask(${task.id})" title="Редактировать"><i class="bi bi-pencil"></i></button>`
            : '';

        const actionsHtml = isAdmin
            ? `
                <div class="task-actions mt-2">
                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteTask(${task.id})">
                        <i class="bi bi-trash"></i> Удалить
                    </button>
                </div>
            `
            : '';

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

    function viewTask(taskId) {
        $.ajax({
            url: `${baseUrl}api/tasks.php?action=get&id=` + taskId,
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

    function showTaskDetails(data) {
        const task = data.task;
        const items = data.items || [];
        const files = data.files || [];

        currentTaskId = task.id;

        $('#taskDetailsTitle').text(escapeHtml(task.title));

        const descriptionContainer = $('#taskDetailsDescription');
        if (task.description && task.description.trim()) {
            descriptionContainer.html(escapeHtml(task.description).replace(/\n/g, '<br>'));
        } else {
            descriptionContainer.empty();
        }

        $('#taskDetailsAssigned').text(escapeHtml(task.assigned_to_name || task.assigned_to_login));
        $('#taskDetailsCreated').text(escapeHtml(task.created_by_name || task.created_by_login));
        $('#taskDetailsDueDate').html(task.due_date ? formatDate(task.due_date) : '<em class="text-muted">Без срока</em>');

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
                    onEnd: saveTaskItemsOrder
                });
            }
        } else {
            checklistContainer.html('<em class="text-muted">Нет элементов чеклиста</em>');
        }

        const filesContainer = $('#taskDetailsFiles');
        if (files.length > 0) {
            let filesHtml = '<ul class="list-unstyled">';
            files.forEach(function(file) {
                filesHtml += `
                    <li class="mb-2">
                        <a href="${baseUrl}api/download_task_file.php?id=${file.id}" target="_blank" class="text-decoration-none">
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

    function saveTaskItemsOrder() {
        if (!currentTaskId) {
            return;
        }

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
            url: `${baseUrl}api/tasks.php?action=update_items_order`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                task_id: currentTaskId,
                items: items
            }),
            dataType: 'json',
            success: function() {
                if (currentTab) {
                    loadTasks(currentTab);
                }
            },
            error: function() {
                if (currentTab) {
                    loadTasks(currentTab);
                }
            }
        });
    }

    function openTaskModal(taskId = null) {
        if (!isAdmin) {
            return;
        }

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
            addTaskItem();
        }

        const modal = new bootstrap.Modal(document.getElementById('taskModal'));
        modal.show();
    }

    function loadTaskForEdit(taskId) {
        $.ajax({
            url: `${baseUrl}api/tasks.php?action=get&id=` + taskId,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    showAlert('danger', response.message);
                    return;
                }

                const task = response.data.task;
                $('#taskTitle').val(task.title);
                $('#taskDescription').val(task.description || '');
                $('#taskAssignedTo').val(task.assigned_to);
                $('#taskDueDate').val(task.due_date || '');

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

                if (response.data.files && response.data.files.length > 0) {
                    response.data.files.forEach(function(file) {
                        addFileToList(file.original_name, file.file_size, file.id, true);
                    });
                }
            },
            error: function() {
                showAlert('danger', 'Ошибка при загрузке задачи');
            }
        });
    }

    function editTask(taskId) {
        openTaskModal(taskId);
    }

    function addTaskItem(title = '', isCompleted = false, itemId = null) {
        const index = $('#taskItemsList .task-item').length;
        const sanitizedTitle = escapeHtml(title);
        const itemHtml = `
            <div class="task-item mb-2" data-item-index="${index}"${itemId ? ` data-item-id="${itemId}"` : ''}>
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
        $('#taskItemsList').append($item);
        initTaskItemsSortable();
        return $item.find('.task-item-input');
    }

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

    function removeTaskItem(button) {
        $(button).closest('.task-item').remove();
    }

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
            setTimeout(function() {
                newInput.trigger('focus');
            }, 0);
        }
    });

    function handleFileSelect(event) {
        const files = Array.from(event.target.files || []);
        files.forEach(function(file) {
            addFileToList(file.name, file.size, null, false, file);
        });
    }

    function handleFileDrop(event) {
        event.preventDefault();
        event.stopPropagation();
        const target = event.currentTarget || event.target;
        $(target).removeClass('drag-over');

        const dataTransfer = event.dataTransfer || (event.originalEvent && event.originalEvent.dataTransfer);
        const files = Array.from((dataTransfer && dataTransfer.files) || []);
        files.forEach(function(file) {
            addFileToList(file.name, file.size, null, false, file);
        });
    }

    function handleDragOver(event) {
        event.preventDefault();
        event.stopPropagation();
        const target = event.currentTarget || event.target;
        $(target).addClass('drag-over');
    }

    function handleDragLeave(event) {
        event.preventDefault();
        event.stopPropagation();
        const target = event.currentTarget || event.target;
        $(target).removeClass('drag-over');
    }

    function addFileToList(fileName, fileSize, fileId, isExisting, file) {
        const removeButton = !isExisting
            ? '<button type="button" class="btn btn-sm btn-link text-danger" onclick="removeTaskFile(this)"><i class="bi bi-x"></i></button>'
            : '';
        const fileHtml = `
            <div class="task-file-item"${fileId ? ` data-file-id="${fileId}"` : ''}${isExisting ? ' data-existing="true"' : ''}>
                <i class="bi bi-file-earmark"></i> ${escapeHtml(fileName)}
                <small class="text-muted">(${formatFileSize(fileSize)})</small>
                ${removeButton}
            </div>
        `;
        $('#taskFilesList').append(fileHtml);

        if (file) {
            taskFiles.push(file);
        }
    }

    function removeTaskFile(button) {
        $(button).closest('.task-file-item').remove();
        // Дополнительная синхронизация taskFiles не реализована в оригинальной версии
    }

    $('#taskForm').on('submit', function(e) {
        e.preventDefault();

        const taskId = $('#taskId').val();
        const formData = {
            title: $('#taskTitle').val(),
            description: $('#taskDescription').val(),
            assigned_to: parseInt($('#taskAssignedTo').val(), 10),
            due_date: $('#taskDueDate').val() || null
        };

        const items = [];
        $('#taskItemsList .task-item').each(function(index) {
            const $item = $(this);
            const title = $item.find('.task-item-input').val().trim();
            if (!title) {
                return;
            }
            const itemIdRaw = $item.attr('data-item-id');
            const parsedId = itemIdRaw !== undefined ? parseInt(itemIdRaw, 10) : NaN;
            const itemId = Number.isNaN(parsedId) ? null : parsedId;
            const isCompletedItem = $item.find('.form-check-input').is(':checked');
            items.push({
                id: itemId,
                title: title,
                is_completed: isCompletedItem,
                sort_order: index
            });
        });
        formData.items = items;

        if (taskId) {
            formData.id = parseInt(taskId, 10);
        }

        $.ajax({
            url: `${baseUrl}api/tasks.php?action=` + (taskId ? 'update' : 'create'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    showAlert('danger', response.message);
                    return;
                }

                if (taskFiles.length > 0) {
                    const taskIdForFiles = taskId || (response.data && response.data.id);
                    uploadTaskFiles(taskIdForFiles);
                } else {
                    showAlert('success', response.message);
                    $('#taskModal').modal('hide');
                    loadTasks(currentTab);
                }
            },
        error: function(xhr, status, error) {
            console.error('save task error:', status, error, xhr.responseText);
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении задачи');
            }
        });
    });

    function uploadTaskFiles(taskId) {
        const formData = new FormData();
        taskFiles.forEach(function(file) {
            formData.append('files[]', file);
        });
        formData.append('task_id', taskId);

        $.ajax({
            url: `${baseUrl}api/tasks_upload.php`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', 'Задача и файлы успешно сохранены');
                } else {
                    showAlert('danger', response.message);
                }
                $('#taskModal').modal('hide');
                loadTasks(currentTab);
                taskFiles = [];
            },
            error: function() {
                showAlert('warning', 'Задача сохранена, но возникла ошибка при загрузке файлов');
                $('#taskModal').modal('hide');
                loadTasks(currentTab);
                taskFiles = [];
            }
        });
    }

    function toggleTaskComplete(taskId, isCompleted) {
        $.ajax({
            url: `${baseUrl}api/tasks.php?action=complete`,
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
        error: function(xhr, status, error) {
            console.error('toggle task error:', status, error, xhr.responseText);
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при обновлении задачи');
            }
        });
    }

    function toggleTaskItemComplete(itemId, isCompleted) {
        $.ajax({
            url: `${baseUrl}api/tasks.php?action=complete_item`,
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
        error: function(xhr, status, error) {
            console.error('toggle checklist item error:', status, error, xhr.responseText);
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при обновлении элемента');
            }
        });
    }

    function deleteTask(taskId) {
        if (!confirm('Вы уверены, что хотите удалить эту задачу?')) {
            return;
        }

        $.ajax({
            url: `${baseUrl}api/tasks.php?action=delete`,
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
        error: function(xhr, status, error) {
            console.error('delete task error:', status, error, xhr.responseText);
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении задачи');
            }
        });
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('ru-RU', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    function formatFileSize(bytes) {
        if (!bytes) {
            return '0 Bytes';
        }
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
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
        if (text === null || text === undefined) {
            return '';
        }
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    $(document).ready(function() {
        taskCardTemplateHtml = $('#taskCardTemplate').html();
        loadUsers();
        loadTasks('my');

        $('#tasksTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
            const target = $(e.target).data('bs-target');
            if (target === '#my-tasks') {
                loadTasks('my');
            } else if (target === '#assigned-tasks') {
                loadTasks('assigned');
            }
        });
    });

    window.openTaskModal = openTaskModal;
    window.editTask = editTask;
    window.deleteTask = deleteTask;
    window.addTaskItem = addTaskItem;
    window.removeTaskItem = removeTaskItem;
    window.handleFileSelect = handleFileSelect;
    window.handleFileDrop = handleFileDrop;
    window.handleDragOver = handleDragOver;
    window.handleDragLeave = handleDragLeave;
    window.removeTaskFile = removeTaskFile;
    window.viewTask = viewTask;
    window.toggleTaskComplete = toggleTaskComplete;
    window.toggleTaskItemComplete = toggleTaskItemComplete;
})();

