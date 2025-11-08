<?php
/**
 * Страница управления контрагентами
 * Доступна только администраторам
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

$page_title = 'Контрагенты';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Контрагенты</h1>
            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Добавить контрагента
            </button>
        </div>
    </div>

    <?php renderSectionDescription('counterparties'); ?>

    <div id="alert-container"></div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="counterpartiesTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Название</th>
                            <th>ИНН</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Документы</th>
                            <th>Обновлён</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="counterpartiesTableBody">
                        <tr>
                            <td colspan="8" class="text-center">
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

<!-- Модальное окно добавления/редактирования -->
<div class="modal fade" id="counterpartyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="counterpartyModalTitle">Добавить контрагента</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="counterpartyForm" novalidate>
                    <input type="hidden" id="counterpartyId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="counterpartyName" class="form-label">Название <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="counterpartyName" name="name" maxlength="255" required>
                        </div>
                        <div class="col-md-6">
                            <label for="counterpartyInn" class="form-label">ИНН</label>
                            <input type="text" class="form-control" id="counterpartyInn" name="inn" placeholder="10 или 12 цифр" maxlength="12">
                        </div>
                        <div class="col-md-6">
                            <label for="counterpartyPhone" class="form-label">Телефон</label>
                            <input type="tel" class="form-control" id="counterpartyPhone" name="phone" placeholder="+7 (___) ___-__-__">
                            <small class="text-muted">Формат: +7 (XXX) XXX-XX-XX</small>
                        </div>
                        <div class="col-md-6">
                            <label for="counterpartyEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="counterpartyEmail" name="email" maxlength="255" placeholder="name@example.com">
                        </div>
                        <div class="col-12">
                            <label for="counterpartyDescription" class="form-label">Описание</label>
                            <textarea class="form-control" id="counterpartyDescription" name="description" rows="3" placeholder="Необязательное описание"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Цвет из палитры</label>
                            <div id="colorPaletteContainer" class="d-flex flex-wrap gap-2"></div>
                            <small class="text-muted">Цвет используется для визуальной идентификации контрагента.</small>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div id="documentsSection" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">Документы</h5>
                            <label class="btn btn-outline-secondary mb-0">
                                <i class="bi bi-upload"></i> Загрузить файлы
                                <input type="file" id="documentUploadInput" multiple hidden>
                            </label>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Название</th>
                                        <th>Размер</th>
                                        <th>Загружено</th>
                                        <th style="width: 120px;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody id="documentsTableBody">
                                    <tr>
                                        <td colspan="4" class="text-muted text-center">Документы отсутствуют</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div id="documentsSectionHint" class="alert alert-light border" role="alert">
                        После сохранения контрагента вы сможете добавить документы.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveCounterparty()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
let counterpartyPalette = [];
let currentCounterpartyId = null;
let counterpartyModalInstance = null;
const phoneInputSelectors = ['#counterpartyPhone'];

function loadPalette() {
    return $.ajax({
        url: '<?php echo BASE_URL; ?>api/counterparties.php?action=palette',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                counterpartyPalette = response.data;
                renderColorPalette();
            } else {
                showAlert('warning', response.message || 'Не удалось загрузить палитру цветов');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке палитры цветов');
        }
    });
}

function renderColorPalette(selectedValue = null) {
    const container = $('#colorPaletteContainer');
    container.empty();

    if (!counterpartyPalette.length) {
        container.html('<span class="text-muted">Палитра не доступна</span>');
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
        url: '<?php echo BASE_URL; ?>api/counterparties.php?action=list',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderCounterpartiesTable(response.data);
            } else {
                showAlert('danger', response.message || 'Не удалось загрузить контрагентов');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при загрузке контрагентов');
        }
    });
}

function renderCounterpartiesTable(counterparties) {
    const tbody = $('#counterpartiesTableBody');
    tbody.empty();

    if (!counterparties || counterparties.length === 0) {
        tbody.html('<tr><td colspan="8" class="text-center text-muted">Контрагенты не найдены</td></tr>');
        return;
    }

    counterparties.forEach(function(counterparty) {
        const colorBadge = counterparty.color
            ? `<span class="d-inline-block rounded-circle" style="width: 16px; height: 16px; background-color: ${escapeHtml(counterparty.color)}; border: 1px solid rgba(0,0,0,0.1);" title="Цвет"></span>`
            : '';

        const inn = counterparty.inn ? escapeHtml(counterparty.inn) : '—';
        const phone = counterparty.phone ? formatPhone(counterparty.phone) : '—';
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

    if (!counterpartyModalInstance) {
        counterpartyModalInstance = new bootstrap.Modal(document.getElementById('counterpartyModal'));
    }
    counterpartyModalInstance.show();
}

function openEditModal(id) {
    currentCounterpartyId = id;
    $('#counterpartyModalTitle').text('Редактировать контрагента');
    $('#counterpartyForm')[0].reset();

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/counterparties.php?action=get&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (!response.success) {
                showAlert('danger', response.message || 'Не удалось загрузить данные контрагента');
                return;
            }

            const counterparty = response.data;
            $('#counterpartyId').val(counterparty.id);
            $('#counterpartyName').val(counterparty.name);
            $('#counterpartyInn').val(counterparty.inn || '');
            $('#counterpartyEmail').val(counterparty.email || '');
            $('#counterpartyDescription').val(counterparty.description || '');

            setPhoneInputValue('#counterpartyPhone', counterparty.phone || '');
            renderColorPalette(counterparty.color || null);
            renderDocuments(counterparty.documents || []);

            $('#documentsSectionHint').hide();
            $('#documentsSection').show();

            if (!counterpartyModalInstance) {
                counterpartyModalInstance = new bootstrap.Modal(document.getElementById('counterpartyModal'));
            }
            counterpartyModalInstance.show();
        },
        error: function() {
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

        const row = `
            <tr>
                <td>${escapeHtml(doc.original_name)}</td>
                <td>${size}</td>
                <td>
                    <div>${uploadedAt}</div>
                    <div class="text-muted small">${uploadedBy}</div>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo BASE_URL; ?>api/download_counterparty_file.php?id=${doc.id}" title="Скачать" target="_blank">
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

function saveCounterparty() {
    const form = document.getElementById('counterpartyForm');
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
        url: '<?php echo BASE_URL; ?>api/counterparties.php?action=' + action,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                if (counterpartyModalInstance) {
                    counterpartyModalInstance.hide();
                }
                loadCounterparties();
            } else {
                showAlert('danger', response.message || 'Ошибка при сохранении контрагента');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при сохранении контрагента');
        }
    });
}

function deleteCounterparty(id) {
    if (!confirm('Удалить контрагента? Действие нельзя отменить.')) {
        return;
    }

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/counterparties.php?action=delete',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadCounterparties();
            } else {
                showAlert('danger', response.message || 'Ошибка при удалении контрагента');
            }
        },
        error: function(xhr) {
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
        url: '<?php echo BASE_URL; ?>api/counterparties.php?action=delete_document',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                openEditModal(currentCounterpartyId);
                showAlert('success', response.message);
            } else {
                showAlert('danger', response.message || 'Ошибка при удалении документа');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при удалении документа');
        }
    });
}

$('#documentUploadInput').on('change', function() {
    if (!currentCounterpartyId) {
        showAlert('warning', 'Сначала сохраните контрагента, затем добавьте документы');
        return;
    }

    const files = this.files;
    if (!files.length) {
        return;
    }

    const formData = new FormData();
    formData.append('counterparty_id', currentCounterpartyId);
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }

    $.ajax({
        url: '<?php echo BASE_URL; ?>api/counterparties.php?action=upload_document',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#documentUploadInput').val('');
                openEditModal(currentCounterpartyId);
            } else {
                showAlert('danger', response.message || 'Ошибка при загрузке документов');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('danger', response.message || 'Ошибка при загрузке документов');
        }
    });
});

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

    return `${value.toFixed(value < 10 && unitIndex > 0 ? 1 : 0)} ${units[unitIndex]}`;
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
    if (formatted) {
        input.value = formatted;
    } else {
        input.value = '';
    }
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
    const formatted = formatPhoneMaskValue(value);
    input.value = formatted;
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

$(document).ready(function() {
    $.when(loadPalette()).then(function() {
        loadCounterparties();
    });
    initializePhoneMasks();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

