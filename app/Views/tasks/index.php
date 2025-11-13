<?php

use App\Support\View;

View::extends('layouts.app');

require_once __DIR__ . '/../../../includes/section_descriptions.php';
require_once __DIR__ . '/../../../templates/task_card.php';

$config = [
    'isAdmin' => !empty($isAdmin),
    'baseUrl' => $baseUrl ?? BASE_URL,
];
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1>Задачи</h1>
                <?php if (!empty($isAdmin)): ?>
                    <button type="button" class="btn btn-primary" onclick="openTaskModal()">
                        <i class="bi bi-plus-circle"></i> Создать задачу
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php renderSectionDescription('tasks'); ?>

    <div id="alert-container"></div>

    <ul class="nav nav-tabs mb-4" id="tasksTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="my-tasks-tab" data-bs-toggle="tab" data-bs-target="#my-tasks" type="button" role="tab">
                Мои задачи
            </button>
        </li>
        <?php if (!empty($isAdmin)): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="assigned-tasks-tab" data-bs-toggle="tab" data-bs-target="#assigned-tasks" type="button" role="tab">
                Я поставил
            </button>
        </li>
        <?php endif; ?>
    </ul>

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
        <?php if (!empty($isAdmin)): ?>
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

<?php if (!empty($isAdmin)): ?>
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
                        <div id="taskItemsList" class="task-items-list"></div>
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

<script>
    window.tasksConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>

