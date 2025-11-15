(function () {
    'use strict';

    if (window.__feedsPageInitialized) {
        return;
    }
    window.__feedsPageInitialized = true;

    const config = window.feedsConfig || {};
    const baseUrl = config.baseUrl || '';
    const apiBase = new URL('.', baseUrl || window.location.href).toString();

    let currentFeedId = null;
    let feedsTableBody = null;
    let feedModalInstance = null;
    let normUploadInput = null;

    document.addEventListener('DOMContentLoaded', initPage);

    function initPage() {
        feedsTableBody = document.getElementById('feedsTableBody');
        normUploadInput = document.getElementById('feedNormUploadInput');
        if (normUploadInput) {
            normUploadInput.addEventListener('change', handleNormUpload);
        }
        feedModalInstance = bootstrap.Modal.getOrCreateInstance(document.getElementById('feedModal'));
        loadFeeds();
    }

    function apiUrl(path) {
        return new URL(path, apiBase).toString();
    }

    function loadFeeds() {
        if (!feedsTableBody) {
            return;
        }
        feedsTableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </td>
            </tr>
        `;

        fetch(apiUrl('api/feeds.php?action=list'))
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Не удалось загрузить корма');
                }
                renderFeedsTable(data.data || []);
            })
            .catch(error => {
                console.error('loadFeeds error:', error);
                showAlert('danger', error.message || 'Ошибка при загрузке кормов');
            });
    }

    function renderFeedsTable(feeds) {
        feedsTableBody.innerHTML = '';

        if (!feeds.length) {
            feedsTableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Корма не найдены</td></tr>';
            return;
        }

        feeds.forEach(feed => {
            const updated = feed.updated_at ? escapeHtml(feed.updated_at) : '-';
            const norms = feed.images_count ? `${feed.images_count} шт.` : '—';
            feedsTableBody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td>${feed.id}</td>
                    <td>
                        <div class="fw-semibold">${escapeHtml(feed.name)}</div>
                        ${feed.description ? `<div class="text-muted small">${escapeHtml(feed.description)}</div>` : ''}
                    </td>
                    <td>${feed.granule ? escapeHtml(feed.granule) : '—'}</td>
                    <td>${feed.manufacturer ? escapeHtml(feed.manufacturer) : '—'}</td>
                    <td>${norms}</td>
                    <td>${updated}</td>
                    <td>
                        <button class="btn btn-sm btn-primary me-2" onclick="openFeedModal(${feed.id})" title="Редактировать">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteFeed(${feed.id})" title="Удалить">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    function openFeedModal(id = null) {
        const form = document.getElementById('feedForm');
        if (!form) {
            return;
        }
        form.reset();
        currentFeedId = null;
        toggleNormsSection(false, []);
        document.getElementById('feedModalTitle').textContent = id ? 'Редактировать корм' : 'Добавить корм';

        if (!id) {
            feedModalInstance.show();
            return;
        }

        fetch(apiUrl(`api/feeds.php?action=get&id=${id}`))
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.data) {
                    throw new Error(data.message || 'Не удалось получить данные корма');
                }
                const feed = data.data;
                currentFeedId = feed.id;
                document.getElementById('feedId').value = feed.id;
                document.getElementById('feedName').value = feed.name || '';
                document.getElementById('feedManufacturer').value = feed.manufacturer || '';
                document.getElementById('feedGranule').value = feed.granule || '';
                document.getElementById('feedDescription').value = feed.description || '';
                document.getElementById('feedFormulaEconom').value = feed.formula_econom || '';
                document.getElementById('feedFormulaNormal').value = feed.formula_normal || '';
                document.getElementById('feedFormulaGrowth').value = feed.formula_growth || '';
                toggleNormsSection(true, feed.norm_images || []);
                feedModalInstance.show();
            })
            .catch(error => {
                console.error('openFeedModal error:', error);
                showAlert('danger', error.message || 'Ошибка при загрузке данных корма');
            });
    }

    function toggleNormsSection(available, images) {
        const hint = document.getElementById('feedNormsUnavailable');
        const section = document.getElementById('feedNormsSection');
        const gallery = document.getElementById('feedNormsGallery');
        if (!hint || !section || !gallery) {
            return;
        }

        if (available) {
            hint.classList.add('d-none');
            section.classList.remove('d-none');
            renderNormImages(images);
        } else {
            hint.classList.remove('d-none');
            section.classList.add('d-none');
            gallery.innerHTML = '<div class="text-muted small">Изображения ещё не добавлены</div>';
        }
    }

    function renderNormImages(images) {
        const gallery = document.getElementById('feedNormsGallery');
        if (!gallery) {
            return;
        }
        if (!images || !images.length) {
            gallery.innerHTML = '<div class="text-muted small">Изображения ещё не добавлены</div>';
            return;
        }
        gallery.innerHTML = '';
        images.forEach(image => {
            const sizeKb = image.file_size ? `${Math.round(image.file_size / 1024)} КБ` : '';
            gallery.insertAdjacentHTML('beforeend', `
                <div class="feed-norm-card">
                    <img src="${escapeHtml(image.url)}" alt="${escapeHtml(image.original_name || '')}">
                    <div class="feed-norm-name" title="${escapeHtml(image.original_name || '')}">
                        ${image.original_name ? escapeHtml(image.original_name) : 'Изображение'}
                    </div>
                    <div class="d-flex justify-content-between align-items-center text-muted small">
                        <span>${sizeKb}</span>
                        <button class="btn btn-sm btn-outline-danger" type="button" onclick="deleteFeedImage(${image.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `);
        });
    }

    function saveFeed() {
        const form = document.getElementById('feedForm');
        if (!form) {
            return;
        }
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const payload = {
            name: document.getElementById('feedName').value.trim(),
            manufacturer: document.getElementById('feedManufacturer').value.trim(),
            granule: document.getElementById('feedGranule').value.trim(),
            description: document.getElementById('feedDescription').value.trim(),
            formula_econom: document.getElementById('feedFormulaEconom').value.trim(),
            formula_normal: document.getElementById('feedFormulaNormal').value.trim(),
            formula_growth: document.getElementById('feedFormulaGrowth').value.trim(),
        };

        if (currentFeedId) {
            payload.id = currentFeedId;
        }

        const action = currentFeedId ? 'update' : 'create';

        fetch(apiUrl(`api/feeds.php?action=${action}`), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Не удалось сохранить корм');
                }
                showAlert('success', data.message || 'Изменения сохранены');
                feedModalInstance.hide();
                loadFeeds();
            })
            .catch(error => {
                console.error('saveFeed error:', error);
                showAlert('danger', error.message || 'Ошибка при сохранении');
            });
    }

    function deleteFeed(id) {
        if (!confirm('Удалить этот корм? Действие нельзя отменить.')) {
            return;
        }
        fetch(apiUrl('api/feeds.php?action=delete'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Не удалось удалить корм');
                }
                showAlert('success', data.message || 'Корм удалён');
                loadFeeds();
            })
            .catch(error => {
                console.error('deleteFeed error:', error);
                showAlert('danger', error.message || 'Ошибка при удалении');
            });
    }

    function handleNormUpload(event) {
        const files = Array.from(event.target.files || []);
        event.target.value = '';
        if (!files.length || !currentFeedId) {
            return;
        }

        const formData = new FormData();
        formData.append('feed_id', currentFeedId);
        files.forEach(file => formData.append('files[]', file));

        fetch(apiUrl('api/feeds.php?action=upload_norms'), {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Не удалось загрузить изображения');
                }
                showAlert('success', data.message || 'Изображения загружены');
                loadFeedImages(currentFeedId);
            })
            .catch(error => {
                console.error('handleNormUpload error:', error);
                showAlert('danger', error.message || 'Ошибка при загрузке изображений');
            });
    }

    function loadFeedImages(feedId) {
        if (!feedId) {
            return;
        }
        fetch(apiUrl(`api/feeds.php?action=get&id=${feedId}`))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderNormImages(data.data.norm_images || []);
                }
            })
            .catch(error => console.error('loadFeedImages error:', error));
    }

    function deleteFeedImage(imageId) {
        if (!confirm('Удалить изображение?')) {
            return;
        }
        fetch(apiUrl('api/feeds.php?action=delete_image'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: imageId }),
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Не удалось удалить изображение');
                }
                showAlert('success', data.message || 'Изображение удалено');
                loadFeedImages(currentFeedId);
            })
            .catch(error => {
                console.error('deleteFeedImage error:', error);
                showAlert('danger', error.message || 'Ошибка при удалении изображения');
            });
    }

    function showAlert(type, message) {
        const container = document.getElementById('feedsAlertContainer');
        if (!container) {
            return;
        }
        container.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        setTimeout(() => {
            const alert = container.querySelector('.alert');
            if (alert) {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            }
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
            "'": '&#039;',
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    window.openFeedModal = openFeedModal;
    window.deleteFeed = deleteFeed;
    window.saveFeed = saveFeed;
    window.deleteFeedImage = deleteFeedImage;
})();

