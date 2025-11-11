<?php

use App\Support\View;

View::extends('layouts.app');

$config = $newsConfig ?? [
    'baseUrl' => BASE_URL,
];

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="mb-0">Новости</h1>
                    <?php renderSectionDescription('news'); ?>
                </div>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="bi bi-plus-circle"></i> Добавить новость
                </button>
            </div>
            <div id="alert-container"></div>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Дата публикации</th>
                                    <th>Заголовок</th>
                                    <th>Автор</th>
                                    <th class="text-end">Действия</th>
                                </tr>
                            </thead>
                            <tbody id="newsTableBody">
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <div class="spinner-border spinner-border-sm" role="status"></div>
                                        <div>Загрузка новостей...</div>
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

<div class="modal fade" id="newsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newsModalTitle">Добавить новость</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="newsForm">
                    <input type="hidden" id="newsId">
                    <div class="mb-3">
                        <label for="newsTitle" class="form-label">Заголовок</label>
                        <input type="text" class="form-control" id="newsTitle" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="newsPublishedAt" class="form-label">Дата публикации</label>
                        <input type="datetime-local" class="form-control" id="newsPublishedAt" required>
                    </div>
                    <div class="mb-3">
                        <label for="newsContent" class="form-label">Текст</label>
                        <textarea id="newsContent" class="form-control" rows="8"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveNews()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.newsConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>


