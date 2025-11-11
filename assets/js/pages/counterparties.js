(function() {
    'use strict';

    if (window.__counterpartiesPageInitialized) {
        return;
    }
    window.__counterpartiesPageInitialized = true;

    const config = window.counterpartiesConfig || {};
    const baseUrl = normalizeBaseUrl(config.baseUrl);
    const apiBase = new URL('.', baseUrl || window.location.href).toString();

    let counterpartyPalette = [];
    let currentCounterpartyId = null;
    let counterpartyModalInstance = null;
    const phoneInputSelectors = ['#counterpartyPhone'];

    function normalizeBaseUrl(value) {
        if (!value) {
            return '/';
        }
        return value.endsWith('/') ? value : `${value}/`;
    }

    function apiUrl(path) {
        return new URL(path, apiBase).toString();
    }

    function init() {
        $.when(loadPalette()).then(function() {
            loadCounterparties();
        });
        initializePhoneMasks();
        $('#documentUploadInput').on('change', handleDocumentUpload);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function loadPalette() {
        return $.ajax({
            url: apiUrl('api/counterparties.php?action=palette'),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    counterpartyPalette = response.data || [];
                    renderColorPalette();
                } else {
                    showAlert('warning', response.message || 'Не удалось загрузить палитру цветов');
                }
            },
            error: function(xhr, status, error) {
                console.error('loadPalette error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке палитры цветов');
            }
        });
    }

    function renderColorPalette(selectedValue = null) {
        const container = $('#colorPaletteContainer');
        container.empty();

        if (!counterpartyPalette.length) {
            container.html('<span class="text-muted">Палитра недоступна</span>');
            return;
        }

        counterpartyPalette.forEach(function(color, index) {
            const value = color.value;
            const label = color.label;
            const inputId = `counterpartyColor_${index}`;
            const isChecked = selectedValue ? selectedValue === value : index === 0;

            const colorOption = `
                <div class="form-check form-check-inline align-items-center">
                    <input class="form-check-input" type="radio" name="counterpartyColor" id="${inputId}" value="${value}" ${isChecked ? 'checked' : ''}>
                    <label class="form-check-label d-flex align-items-center" for="${inputId}">
                        <span class="d-inline-block rounded-circle me-2" style="width: 18px; height: 18px; background-color: ${value}; border: 1px solid rgba(0,0,0,0.15);"></span>
                        ${escapeHtml(label)}
                    </label>
                </div>
            `;

            container.append(colorOption);
        });
    }

    function loadCounterparties() {
        $.ajax({
            url: apiUrl('api/counterparties.php?action=list'),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderCounterpartiesTable(response.data || []);
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить контрагентов');
                }
            },
            error: function(xhr, status, error) {
                console.error('loadCounterparties error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке контрагентов');
            }
        });
    }

    function renderCounterpartiesTable(counterparties) {
        const tbody = $('#counterpartiesTableBody');
        tbody.empty();

        if (!counterparties.length) {
            tbody.html('<tr><td colspan="8" class="text-center text-muted">Контрагенты не найдены</td></tr>');
            return;
        }

        counterparties.forEach(function(counterparty) {
            const colorBadge = counterparty.color
                ? `<span class="d-inline-block rounded-circle" style="width: 16px; height: 16px; background-color: ${escapeHtml(counterparty.color)}; border: 1px solid rgba(0,0,0,0.1);" title="Цвет"></span>`
                : '';
            const inn = counterparty.inn ? escapeHtml(counterparty.inn) : '—';
            const phone = formatPhone(counterparty.phone);
            const email = counterparty.email ? `<a href="mailto:${escapeHtml(counterparty.email)}">${escapeHtml(counterparty.email)}</a>` : '—';
            const documents = counterparty.documents_count > 0
                ? `<span class="badge bg-info text-dark">${counterparty.documents_count}</span>`
                : '<span class="badge bg-light text-muted">0</span>';
            const updatedAt = counterparty.updated_at ? escapeHtml(counterparty.updated_at) : '—';

            const row = `
                <tr>
                    <td class="text-center align-middle">${colorBadge}</td>
                    <td class="align-middle">${escapeHtml(counterparty.name)}</td>
                    <td class="align-middle">${inn}</td>
                    <td class="align-middle">${phone}</td>
                    <td class="align-middle">${email}</td>
                    <td class="align-middle">${documents}</td>
                    <td class="align-middle">${updatedAt}</td>
                    <td class="align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-sm btn-primary" onclick="openEditModal(${counterparty.id})" title="Редактировать">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteCounterparty(${counterparty.id})" title="Удалить">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;

            tbody.append(row);
        });
    }

    function openAddModal() {
        currentCounterpartyId = null;
        $('#counterpartyModalTitle').text('Добавить контрагента');
        $('#counterpartyForm')[0].reset();
        $('#counterpartyId').val('');
        renderColorPalette();
        setPhoneInputValue('#counterpartyPhone', '');
        $('#documentsSection').hide();
        $('#documentsSectionHint').show();
        $('#documentsTableBody').html('<tr><td colspan="4" class="text-muted text-center">Документы отсутствуют</td></tr>');

        counterpartyModalInstance = bootstrap.Modal.getOrCreateInstance(document.getElementById('counterpartyModal'));
        counterpartyModalInstance.show();
    }

    function openEditModal(id) {
        currentCounterpartyId = id;
        $('#counterpartyModalTitle').text('Редактировать контрагента');
        $('#counterpartyForm')[0].reset();

        $.ajax({
            url: apiUrl(`api/counterparties.php?action=get&id=${id}`),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    showAlert('danger', response.message || 'Не удалось загрузить данные контрагента');
                    return;
                }

                const counterparty = response.data || {};
                $('#counterpartyId').val(counterparty.id || '');
                $('#counterpartyName').val(counterparty.name || '');
                $('#counterpartyInn').val(counterparty.inn || '');
                $('#counterpartyEmail').val(counterparty.email || '');
                $('#counterpartyDescription').val(counterparty.description || '');

                setPhoneInputValue('#counterpartyPhone', counterparty.phone || '');
                renderColorPalette(counterparty.color || null);
                renderDocuments(counterparty.documents || []);

                $('#documentsSectionHint').hide();
                $('#documentsSection').show();

                counterpartyModalInstance = bootstrap.Modal.getOrCreateInstance(document.getElementById('counterpartyModal'));
                counterpartyModalInstance.show();
            },
            error: function(xhr, status, error) {
                console.error('openEditModal error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке данных контрагента');
            }
        });
    }

    function renderDocuments(documents) {
        const tbody = $('#documentsTableBody');
        tbody.empty();

        if (!documents.length) {
            tbody.html('<tr><td colspan="4" class="text-muted text-center">Документы отсутствуют</td></tr>');
            return;
        }

        documents.forEach(function(doc) {
            const size = formatBytes(doc.file_size || 0);
            const uploadedBy = doc.uploaded_by_name
                ? escapeHtml(doc.uploaded_by_name)
                : (doc.uploaded_by_login ? escapeHtml(doc.uploaded_by_login) : '—');
            const uploadedAt = doc.uploaded_at ? escapeHtml(doc.uploaded_at) : '—';
            const downloadUrl = apiUrl(`api/download_counterparty_file.php?id=${doc.id}`);

            const row = `
                <tr>
                    <td>${escapeHtml(doc.original_name || '—')}</td>
                    <td>${size}</td>
                    <td>
                        <div>${uploadedAt}</div>
                        <div class="text-muted small">${uploadedBy}</div>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a class="btn btn-sm btn-outline-secondary" href="${downloadUrl}" title="Скачать" target="_blank">
                                <i class="bi bi-download"></i>
                            </a>
                            <button class="btn btn-sm btn-danger" onclick="deleteDocument(${doc.id})" title="Удалить">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function clearFormErrors() {
        $('#counterpartyForm .is-invalid').removeClass('is-invalid');
        $('#counterpartyForm .invalid-feedback').remove();
    }

    function showFieldError(selector, message) {
        const input = $(selector);
        if (!input.length) {
            showAlert('danger', message);
            return;
        }
        if (!input.hasClass('is-invalid')) {
            input.addClass('is-invalid');
            input.after(`<div class="invalid-feedback">${escapeHtml(message)}</div>`);
        }
        if (typeof input.focus === 'function') {
            input.focus();
        }
    }

    function saveCounterparty() {
        const form = document.getElementById('counterpartyForm');
        clearFormErrors();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const payload = {
            name: $('#counterpartyName').val(),
            description: $('#counterpartyDescription').val(),
            inn: $('#counterpartyInn').val(),
            phone: $('#counterpartyPhone').val(),
            email: $('#counterpartyEmail').val(),
            color: $('input[name="counterpartyColor"]:checked').val() || null,
        };

        let action = 'create';
        if (currentCounterpartyId) {
            payload.id = currentCounterpartyId;
            action = 'update';
        }

        $.ajax({
            url: apiUrl(`api/counterparties.php?action=${action}`),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Контрагент сохранён');
                    if (counterpartyModalInstance) {
                        counterpartyModalInstance.hide();
                    }
                    loadCounterparties();
                } else {
                    handleFormError(response.message || 'Ошибка при сохранении контрагента', response.field || null);
                }
            },
            error: function(xhr, status, error) {
                console.error('saveCounterparty error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                handleFormError(response.message || 'Ошибка при сохранении контрагента', response.field || null);
            }
        });
    }

    function deleteCounterparty(id) {
        if (!confirm('Удалить контрагента? Действие нельзя отменить.')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/counterparties.php?action=delete'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Контрагент удалён');
                    loadCounterparties();
                } else {
                    showAlert('danger', response.message || 'Ошибка при удалении контрагента');
                }
            },
            error: function(xhr, status, error) {
                console.error('deleteCounterparty error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при удалении контрагента');
            }
        });
    }

    function deleteDocument(id) {
        if (!currentCounterpartyId) {
            return;
        }

        if (!confirm('Удалить документ?')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/counterparties.php?action=delete_document'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Документ удалён');
                    openEditModal(currentCounterpartyId);
                } else {
                    showAlert('danger', response.message || 'Ошибка при удалении документа');
                }
            },
            error: function(xhr, status, error) {
                console.error('deleteDocument error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при удалении документа');
            }
        });
    }

    function handleDocumentUpload(event) {
        if (!currentCounterpartyId) {
            showAlert('warning', 'Сначала сохраните контрагента, затем добавьте документы');
            event.target.value = '';
            return;
        }

        const files = event.target.files;
        if (!files || !files.length) {
            return;
        }

        const formData = new FormData();
        formData.append('counterparty_id', currentCounterpartyId);
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        $.ajax({
            url: apiUrl('api/counterparties.php?action=upload_document'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Документы загружены');
                    event.target.value = '';
                    openEditModal(currentCounterpartyId);
                } else {
                    showAlert('danger', response.message || 'Ошибка при загрузке документов');
                }
            },
            error: function(xhr, status, error) {
                console.error('uploadDocument error:', status, error, xhr.responseText);
                const response = xhr.responseJSON || {};
                showAlert('danger', response.message || 'Ошибка при загрузке документов');
            }
        });
    }

    function handleFormError(message, field) {
        const fieldMap = {
            name: '#counterpartyName',
            inn: '#counterpartyInn',
            phone: '#counterpartyPhone',
            email: '#counterpartyEmail',
            color: 'input[name="counterpartyColor"]'
        };

        if (field && fieldMap[field]) {
            showFieldError(fieldMap[field], message);
            return;
        }

        const lower = message ? message.toLowerCase() : '';
        if (lower.includes('название')) {
            showFieldError('#counterpartyName', message);
        } else if (lower.includes('инн')) {
            showFieldError('#counterpartyInn', message);
        } else if (lower.includes('телефон')) {
            showFieldError('#counterpartyPhone', message);
        } else if (lower.includes('email')) {
            showFieldError('#counterpartyEmail', message);
        } else if (lower.includes('цвет')) {
            showFieldError('input[name="counterpartyColor"]', message);
        } else {
            showAlert('danger', message);
        }
    }

    function formatBytes(bytes) {
        if (!bytes || isNaN(bytes)) {
            return '—';
        }
        const units = ['Б', 'КБ', 'МБ', 'ГБ'];
        let unitIndex = 0;
        let value = bytes;

        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex++;
        }

        const fractionDigits = value < 10 && unitIndex > 0 ? 1 : 0;
        return `${value.toFixed(fractionDigits)} ${units[unitIndex]}`;
    }

    function formatPhone(value) {
        if (!value) {
            return '—';
        }
        const digits = value.replace(/\D/g, '');
        if (digits.length !== 11 || digits[0] !== '7') {
            return escapeHtml(value);
        }
        const formatted = `+7 (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9)}`;
        return escapeHtml(formatted);
    }

    function normalizePhoneDigits(value) {
        if (!value) {
            return '';
        }
        let digits = String(value).replace(/\D/g, '');
        if (!digits.length) {
            return '';
        }
        if (digits[0] === '8') {
            digits = '7' + digits.slice(1);
        }
        if (digits[0] !== '7') {
            digits = '7' + digits;
        }
        return digits.slice(0, 11);
    }

    function formatPhoneMaskValue(value) {
        const digits = normalizePhoneDigits(value);
        if (!digits) {
            return '';
        }
        let result = '+7';
        const tail = digits.slice(1);
        if (!tail.length) {
            return result;
        }
        result += ' (' + tail.slice(0, Math.min(3, tail.length));
        if (tail.length >= 3) {
            result += ')';
        }
        if (tail.length > 3) {
            result += ' ' + tail.slice(3, Math.min(6, tail.length));
        }
        if (tail.length > 6) {
            result += '-' + tail.slice(6, Math.min(8, tail.length));
        }
        if (tail.length > 8) {
            result += '-' + tail.slice(8, Math.min(10, tail.length));
        }
        return result;
    }

    function handlePhoneInput(event) {
        const input = event.target;
        const formatted = formatPhoneMaskValue(input.value);
        input.value = formatted || '';
    }

    function handlePhoneBlur(event) {
        const input = event.target;
        const digits = normalizePhoneDigits(input.value);
        if (!digits || digits.length === 1) {
            input.value = '';
        } else {
            input.value = formatPhoneMaskValue(digits);
        }
    }

    function initializePhoneMasks() {
        phoneInputSelectors.forEach(function(selector) {
            const input = document.querySelector(selector);
            if (!input || input.dataset.maskInitialized === 'true') {
                return;
            }
            input.addEventListener('input', handlePhoneInput);
            input.addEventListener('focus', handlePhoneInput);
            input.addEventListener('blur', handlePhoneBlur);
            if (input.value) {
                input.value = formatPhoneMaskValue(input.value);
            }
            input.dataset.maskInitialized = 'true';
        });
    }

    function setPhoneInputValue(selector, value) {
        const input = document.querySelector(selector);
        if (!input) {
            return;
        }
        input.value = formatPhoneMaskValue(value);
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
        return String(text).replace(/[&<>"']/g, function(m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[m];
        });
    }

    window.openAddModal = openAddModal;
    window.openEditModal = openEditModal;
    window.saveCounterparty = saveCounterparty;
    window.deleteCounterparty = deleteCounterparty;
    window.deleteDocument = deleteDocument;
})();
