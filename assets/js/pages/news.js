(function () {
    'use strict';

    if (window.__newsPageInitialized) {
        return;
    }
    window.__newsPageInitialized = true;

    const config = window.newsConfig || {};
    const baseUrl = config.baseUrl || '';
    const apiBase = new URL('.', baseUrl || window.location.href).toString();

    let newsModal = null;
    let isEditing = false;

    function apiUrl(path) {
        return new URL(path, apiBase).toString();
    }

    document.addEventListener('DOMContentLoaded', function () {
        newsModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('newsModal'));
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
                ['view', ['fullscreen', 'codeview']],
            ],
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
            url: apiUrl('api/news.php?action=list'),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderNewsTable(response.data || []);
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить новости');
                }
            },
            error: function (xhr, status, error) {
                console.error('loadNews error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке новостей');
            },
        });
    }

    function renderNewsTable(news) {
        const tbody = $('#newsTableBody');
        tbody.empty();

        if (!news.length) {
            tbody.html('<tr><td colspan="4" class="text-center text-muted py-4">Новостей пока нет</td></tr>');
            return;
        }

        news.forEach(function (item) {
            const author = item.author_full_name
                ? `${escapeHtml(item.author_full_name)} (${escapeHtml(item.author_login)})`
                : escapeHtml(item.author_login || '—');
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
            url: apiUrl(`api/news.php?action=get&id=${id}`),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
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
            error: function (xhr, status, error) {
                console.error('openEditModal error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке новости');
            },
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
            content: content,
        };
        if (isEditing) {
            payload.id = parseInt(id, 10);
        }

        const action = isEditing ? 'update' : 'create';

        $.ajax({
            url: apiUrl(`api/news.php?action=${action}`),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Новость сохранена');
                    newsModal.hide();
                    loadNews();
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить новость');
                }
            },
            error: function (xhr, status, error) {
                console.error('saveNews error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при сохранении новости');
            },
        });
    }

    function confirmDelete(id) {
        if (!confirm('Удалить эту новость?')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/news.php?action=delete'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: id }),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Новость удалена');
                    loadNews();
                } else {
                    showAlert('danger', response.message || 'Не удалось удалить новость');
                }
            },
            error: function (xhr, status, error) {
                console.error('confirmDelete error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при удалении новости');
            },
        });
    }

    function showAlert(type, message) {
        $('#alert-container').html(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDateTime(value) {
        if (!value) {
            return '';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
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

    window.openCreateModal = openCreateModal;
    window.openEditModal = openEditModal;
    window.saveNews = saveNews;
    window.confirmDelete = confirmDelete;
})();


