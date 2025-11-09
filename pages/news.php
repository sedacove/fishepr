<?php
/**
 * Управление новостями
 * Доступно только администраторам
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

$page_title = 'Новости';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">

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

<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script>
let newsModal;
let isEditing = false;

document.addEventListener('DOMContentLoaded', function() {
    newsModal = new bootstrap.Modal(document.getElementById('newsModal'));
    initEditor();
    loadNews();
});

function initEditor() {
    $('#newsContent').summernote({
        placeholder: 'Введите текст новости',
        tabsize: 2,
        height: 250,
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'clear']],
            ['font', ['strikethrough', 'superscript', 'subscript']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link']],
            ['view', ['fullscreen', 'codeview']]
        ]
    });
}

function loadNews() {
    const tbody = $('#newsTableBody');
    tbody.html(`
        <tr>
            <td colspan="4" class="text-center py-4 text-muted">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <div>Загрузка новостей...</div>
            </td>
        </tr>
    `);

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/news.php?action=list',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderNewsTable(response.data || []);
            } else {
                showAlert('danger', response.message || 'Не удалось загрузить новости');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке новостей');
        }
    });
}

function renderNewsTable(news) {
    const tbody = $('#newsTableBody');
    tbody.empty();

    if (!news.length) {
        tbody.html('<tr><td colspan="4" class="text-center text-muted py-4">Новостей пока нет</td></tr>');
        return;
    }

    news.forEach(function(item) {
        const author = item.author_full_name ? `${escapeHtml(item.author_full_name)} (${escapeHtml(item.author_login)})` : escapeHtml(item.author_login || '—');
        const date = formatDateTime(item.published_at);

        const row = `
            <tr>
                <td>${date}</td>
                <td>${escapeHtml(item.title)}</td>
                <td>${author}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-primary me-2" onclick="openEditModal(${item.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="confirmDelete(${item.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        tbody.append(row);
    });
}

function openCreateModal() {
    isEditing = false;
    $('#newsModalTitle').text('Добавить новость');
    $('#newsForm')[0].reset();
    $('#newsId').val('');
    const now = new Date();
    $('#newsPublishedAt').val(convertDateToInputValue(now));
    $('#newsContent').summernote('code', '');
    newsModal.show();
}

function openEditModal(id) {
    isEditing = true;
    $('#newsModalTitle').text('Редактировать новость');

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/news.php?action=get&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                const news = response.data;
                $('#newsId').val(news.id);
                $('#newsTitle').val(news.title);
                $('#newsPublishedAt').val(convertDateToInputValue(news.published_at));
                $('#newsContent').summernote('code', news.content || '');
                newsModal.show();
            } else {
                showAlert('danger', response.message || 'Не удалось получить данные новости');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке новости');
        }
    });
}

function saveNews() {
    const id = $('#newsId').val();
    const title = $('#newsTitle').val().trim();
    const publishedAt = $('#newsPublishedAt').val();
    const content = $('#newsContent').summernote('code');

    if (!title) {
        showAlert('warning', 'Введите заголовок');
        return;
    }
    if (!publishedAt) {
        showAlert('warning', 'Укажите дату публикации');
        return;
    }
    if (!content || $('#newsContent').summernote('isEmpty')) {
        showAlert('warning', 'Введите текст новости');
        return;
    }

    const payload = {
        title: title,
        published_at: publishedAt,
        content: content
    };
    if (isEditing) {
        payload.id = parseInt(id, 10);
    }

    const action = isEditing ? 'update' : 'create';

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/news.php?action=' + action,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message || 'Новость сохранена');
                newsModal.hide();
                loadNews();
            } else {
                showAlert('danger', response.message || 'Не удалось сохранить новость');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при сохранении новости');
        }
    });
}

function confirmDelete(id) {
    if (!confirm('Удалить эту новость?')) {
        return;
    }

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/news.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id: id }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message || 'Новость удалена');
                loadNews();
            } else {
                showAlert('danger', response.message || 'Не удалось удалить новость');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при удалении новости');
        }
    });
}

function showAlert(type, message) {
    $('#alert-container').html(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    return date.toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function convertDateToInputValue(value) {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

