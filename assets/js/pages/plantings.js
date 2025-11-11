(function () {
    'use strict';

    if (window.__plantingsPageInitialized) {
        return;
    }
    window.__plantingsPageInitialized = true;

    const config = window.plantingsConfig || {};
    const baseUrl = config.baseUrl || '';
    const apiBase = new URL('.', baseUrl || window.location.href).toString();

    let currentEditId = null;
    let currentTab = 0;

    function apiUrl(path) {
        return new URL(path, apiBase).toString();
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupFileUploadHandlers();
        loadPlantings(0);
    });

    function loadPlantings(archived) {
        currentTab = archived;
        const bodyId = archived ? '#archivedPlantingsBody' : '#activePlantingsBody';
        const colspan = 10;
        const tbody = $(bodyId);
        tbody.html(`
            <tr>
                <td colspan="${colspan}" class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </td>
            </tr>
        `);

        $.ajax({
            url: apiUrl(`api/plantings.php?action=list&archived=${archived ? 1 : 0}`),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderPlantingsTable(response.data || [], tbody, archived);
                } else {
                    showAlert('danger', response.message || 'Ошибка при загрузке посадок');
                }
            },
            error: function (xhr, status, error) {
                console.error('loadPlantings error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке посадок');
            },
        });
    }

    function renderPlantingsTable(plantings, tbody, isArchived) {
        tbody.empty();

        if (!plantings.length) {
            tbody.html(`<tr><td colspan="10" class="text-center text-muted">Посадки не найдены</td></tr>`);
            return;
        }

        plantings.forEach(function (planting) {
            const price = planting.price !== null && planting.price !== undefined ? formatCurrency(planting.price) : '-';
            const filesCount = planting.files_count || 0;
            const filesBadge = filesCount > 0
                ? `<span class="badge bg-info">${filesCount} файл${filesCount > 1 ? 'ов' : ''}</span>`
                : '<span class="text-muted">-</span>';

            const actions = [
                `<button class="btn btn-sm btn-primary me-1" onclick="openEditModal(${planting.id})" title="Редактировать">
                    <i class="bi bi-pencil"></i>
                </button>`,
                isArchived
                    ? `<button class="btn btn-sm btn-success me-1" onclick="archivePlanting(${planting.id}, 0)" title="Разархивировать">
                            <i class="bi bi-archive"></i>
                       </button>`
                    : `<button class="btn btn-sm btn-warning me-1" onclick="archivePlanting(${planting.id}, 1)" title="Архивировать">
                            <i class="bi bi-archive"></i>
                       </button>`,
                `<button class="btn btn-sm btn-danger" onclick="deletePlanting(${planting.id})" title="Удалить">
                    <i class="bi bi-trash"></i>
                </button>`,
            ].join('');

            const row = `
                <tr>
                    <td>${planting.id}</td>
                    <td>${escapeHtml(planting.name)}</td>
                    <td>${escapeHtml(planting.fish_breed)}</td>
                    <td>${escapeHtml(planting.planting_date)}</td>
                    <td>${planting.fish_count}</td>
                    <td>${planting.biomass_weight ?? '-'}</td>
                    <td>${escapeHtml(planting.supplier ?? '-')}</td>
                    <td>${price}</td>
                    <td>${filesBadge}</td>
                    <td>${actions}</td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function openAddModal() {
        currentEditId = null;
        $('#plantingModalTitle').text('Добавить посадку');
        $('#plantingForm')[0].reset();
        $('#plantingId').val('');
        $('#filesSection').hide();
        $('#filesList').empty();
        setCurrentDate('#plantingDate');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('plantingModal')).show();
    }

    function openEditModal(id) {
        currentEditId = id;
        $('#plantingModalTitle').text('Редактировать посадку');
        $('#filesSection').show();
        $('#filesList').empty();

        $.ajax({
            url: apiUrl(`api/plantings.php?action=get&id=${id}`),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
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

                    renderFilesList(planting.files || []);

                    bootstrap.Modal.getOrCreateInstance(document.getElementById('plantingModal')).show();
                } else {
                    showAlert('danger', response.message || 'Не удалось получить данные посадки');
                }
            },
            error: function (xhr, status, error) {
                console.error('openEditModal error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке данных посадки');
            },
        });
    }

    function renderFilesList(files) {
        const list = $('#filesList');
        list.empty();

        if (!files.length) {
            return;
        }

        files.forEach(function (file) {
            const fileSize = formatFileSize(file.file_size || 0);
            const item = `
                <div class="plantings-file-item" data-file-id="${file.id}">
                    <div>
                        <i class="bi bi-file-earmark"></i>
                        <a href="${apiUrl(`api/download_file.php?id=${file.id}`)}" class="text-decoration-none ms-1" target="_blank">
                            ${escapeHtml(file.original_name)}
                        </a>
                        <small class="text-muted">(${fileSize})</small>
                    </div>
                    <button class="btn btn-sm btn-danger" onclick="deleteFile(${file.id}, this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
            list.append(item);
        });
    }

    function savePlanting() {
        const form = $('#plantingForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const payload = {
            name: $('#plantingName').val().trim(),
            fish_breed: $('#plantingFishBreed').val().trim(),
            hatch_date: $('#plantingHatchDate').val() || null,
            planting_date: $('#plantingDate').val(),
            fish_count: parseInt($('#plantingFishCount').val(), 10),
            biomass_weight: toNullableNumber('#plantingBiomassWeight'),
            supplier: $('#plantingSupplier').val().trim() || null,
            price: toNullableNumber('#plantingPrice'),
            delivery_cost: toNullableNumber('#plantingDeliveryCost'),
        };

        if (currentEditId) {
            payload.id = currentEditId;
        }

        const action = currentEditId ? 'update' : 'create';

        $.ajax({
            url: apiUrl(`api/plantings.php?action=${action}`),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Изменения сохранены');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('plantingModal')).hide();
                    loadPlantings(currentTab);

                    if (!currentEditId && response.id) {
                        setTimeout(() => openEditModal(response.id), 400);
                    }
                } else {
                    showAlert('danger', response.message || 'Не удалось сохранить посадку');
                }
            },
            error: function (xhr, status, error) {
                console.error('savePlanting error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при сохранении посадки');
            },
        });
    }

    function deletePlanting(id) {
        if (!confirm('Вы уверены, что хотите удалить эту посадку? Все связанные файлы также будут удалены.')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/plantings.php?action=delete'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id }),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Посадка удалена');
                    loadPlantings(currentTab);
                } else {
                    showAlert('danger', response.message || 'Не удалось удалить посадку');
                }
            },
            error: function (xhr, status, error) {
                console.error('deletePlanting error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при удалении посадки');
            },
        });
    }

    function archivePlanting(id, archiveFlag) {
        const actionText = archiveFlag ? 'архивировать' : 'разархивировать';
        if (!confirm(`Вы уверены, что хотите ${actionText} эту посадку?`)) {
            return;
        }

        $.ajax({
            url: apiUrl('api/plantings.php?action=archive'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id, is_archived: archiveFlag }),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Статус обновлен');
                    loadPlantings(currentTab);
                } else {
                    showAlert('danger', response.message || 'Не удалось изменить статус посадки');
                }
            },
            error: function (xhr, status, error) {
                console.error('archivePlanting error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при изменении статуса посадки');
            },
        });
    }

    function deleteFile(fileId, button) {
        if (!confirm('Вы уверены, что хотите удалить этот файл?')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/plantings.php?action=delete_file'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ file_id: fileId }),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $(button).closest('[data-file-id]').remove();
                    showAlert('success', response.message || 'Файл удалён');
                } else {
                    showAlert('danger', response.message || 'Не удалось удалить файл');
                }
            },
            error: function (xhr, status, error) {
                console.error('deleteFile error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при удалении файла');
            },
        });
    }

    function uploadFiles(files) {
        if (!currentEditId) {
            showAlert('warning', 'Сначала сохраните посадку, затем загрузите файлы');
            return;
        }

        const formData = new FormData();
        formData.append('planting_id', currentEditId);

        Array.from(files).forEach(function (file) {
            formData.append('files[]', file);
        });

        $.ajax({
            url: apiUrl('api/plantings_upload.php'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    if (Array.isArray(response.uploaded) && response.uploaded.length > 0) {
                        openEditModal(currentEditId);
                        showAlert('success', `Загружено файлов: ${response.uploaded.length}`);
                    }
                    if (Array.isArray(response.errors) && response.errors.length > 0) {
                        showAlert('warning', response.errors.join('<br>'));
                    }
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить файлы');
                }
            },
            error: function (xhr, status, error) {
                console.error('uploadFiles error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при загрузке файлов');
            },
        });
    }

    function setupFileUploadHandlers() {
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');

        if (!dropZone || !fileInput) {
            return;
        }

        dropZone.addEventListener('click', function () {
            if (currentEditId) {
                fileInput.click();
            } else {
                showAlert('info', 'Сначала сохраните посадку, затем загрузите файлы');
            }
        });

        dropZone.addEventListener('dragover', function (event) {
            event.preventDefault();
            dropZone.classList.add('border-primary');
        });

        dropZone.addEventListener('dragleave', function () {
            dropZone.classList.remove('border-primary');
        });

        dropZone.addEventListener('drop', function (event) {
            event.preventDefault();
            dropZone.classList.remove('border-primary');
            if (!currentEditId) {
                showAlert('info', 'Сначала сохраните посадку, затем загрузите файлы');
                return;
            }
            const files = event.dataTransfer.files;
            if (files && files.length) {
                uploadFiles(files);
            }
        });

        fileInput.addEventListener('change', function (event) {
            if (event.target.files && event.target.files.length) {
                uploadFiles(event.target.files);
                fileInput.value = '';
            }
        });
    }

    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('#alert-container').html(alertHtml);
        setTimeout(function () {
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
            "'": '&#039;',
        };
        return String(text).replace(/[&<>"']/g, function (m) {
            return map[m];
        });
    }

    function formatCurrency(value) {
        return new Intl.NumberFormat('ru-RU', {
            style: 'currency',
            currency: 'RUB',
            minimumFractionDigits: 0,
        }).format(value);
    }

    function formatFileSize(bytes) {
        if (!bytes) {
            return '0 Bytes';
        }
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        const result = bytes / Math.pow(1024, i);
        return `${Math.round(result * 100) / 100} ${sizes[i]}`;
    }

    function toNullableNumber(selector) {
        const value = $(selector).val();
        if (value === null || value === undefined || value === '') {
            return null;
        }
        const number = parseFloat(value);
        return Number.isNaN(number) ? null : number;
    }

    function setCurrentDate(selector) {
        const input = document.querySelector(selector);
        if (!input) {
            return;
        }
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        input.value = `${year}-${month}-${day}`;
    }

    window.openAddModal = openAddModal;
    window.openEditModal = openEditModal;
    window.savePlanting = savePlanting;
    window.deletePlanting = deletePlanting;
    window.archivePlanting = archivePlanting;
    window.deleteFile = deleteFile;
    window.loadPlantings = loadPlantings;
})();


