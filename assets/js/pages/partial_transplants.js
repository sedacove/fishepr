(function() {
    'use strict';

    if (window.__partialTransplantsPageInitialized) {
        return;
    }
    window.__partialTransplantsPageInitialized = true;

    const config = window.partialTransplantsConfig || {};
    const baseUrl = config.baseUrl || '';
    const apiBase = new URL('.', baseUrl || window.location.href).toString();

    let sessionsCache = [];

    function apiUrl(path) {
        return new URL(path, apiBase).toString();
    }

    document.addEventListener('DOMContentLoaded', function() {
        preloadSessions();
        loadTransplants();
    });

    function preloadSessions() {
        $.ajax({
            url: apiUrl('api/sessions.php?action=list&completed=0'),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    sessionsCache = response.data || [];
                    populateSessionSelects();
                }
            },
            error: function(xhr, status, error) {
                console.error('preloadSessions error:', status, error, xhr.responseText);
            }
        });
    }

    function populateSessionSelects() {
        const sourceSelect = $('#sourceSessionId');
        const recipientSelect = $('#recipientSessionId');
        
        sourceSelect.empty().append('<option value="">Выберите сессию...</option>');
        recipientSelect.empty().append('<option value="">Выберите сессию...</option>');

        sessionsCache.forEach(function(session) {
            const sessionName = session.name || `Сессия #${session.id}`;
            const poolName = session.pool_name ? ` (${escapeHtml(session.pool_name)})` : '';
            const displayText = `${escapeHtml(sessionName)}${poolName}`;
            const option = `<option value="${session.id}">${displayText}</option>`;
            sourceSelect.append(option);
            recipientSelect.append(option);
        });
    }

    function loadTransplants() {
        const tbody = $('#transplantsTableBody');
        tbody.html(`
            <tr>
                <td colspan="8" class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    <div>Загрузка...</div>
                </td>
            </tr>
        `);

        $.ajax({
            url: apiUrl('api/partial_transplants.php?action=list'),
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderTransplantsTable(response.data || []);
                } else {
                    showAlert('danger', response.message || 'Не удалось загрузить пересадки');
                    tbody.html('<tr><td colspan="8" class="text-center text-muted py-4">Ошибка при загрузке данных</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('loadTransplants error:', status, error, xhr.responseText);
                showAlert('danger', 'Ошибка при загрузке пересадок');
                tbody.html('<tr><td colspan="8" class="text-center text-muted py-4">Ошибка при загрузке данных</td></tr>');
            }
        });
    }

    function renderTransplantsTable(transplants) {
        const tbody = $('#transplantsTableBody');
        tbody.empty();

        if (transplants.length === 0) {
            tbody.html('<tr><td colspan="8" class="text-center text-muted py-4">Пересадки не найдены</td></tr>');
            return;
        }

        transplants.forEach(function(transplant) {
            const statusBadge = transplant.is_reverted
                ? '<span class="badge bg-secondary">Откатана</span>'
                : '<span class="badge bg-success">Активна</span>';

            const revertButton = transplant.is_reverted
                ? ''
                : `<button class="btn btn-sm btn-warning" onclick="confirmRevert(${transplant.id})" title="Откатить пересадку">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>`;

            const row = `
                <tr>
                    <td>${formatDate(transplant.transplant_date)}</td>
                    <td>${escapeHtml(transplant.source_session_name || `Сессия #${transplant.source_session_id}`)}</td>
                    <td>${escapeHtml(transplant.recipient_session_name || `Сессия #${transplant.recipient_session_id}`)}</td>
                    <td>${formatNumber(transplant.weight, 2)}</td>
                    <td>${formatNumber(transplant.fish_count, 0)}</td>
                    <td>${statusBadge}</td>
                    <td>${escapeHtml(transplant.created_by_name || transplant.created_by_login || '—')}</td>
                    <td class="text-end">${revertButton}</td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    window.openAddModal = function() {
        $('#transplantModalTitle').text('Добавить пересадку');
        $('#transplantForm')[0].reset();
        $('#transplantId').val('');
        $('#transplantDate').val(new Date().toISOString().split('T')[0]);
        const modal = new bootstrap.Modal(document.getElementById('transplantModal'));
        modal.show();
    };

    window.saveTransplant = function() {
        const form = $('#transplantForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const payload = {
            transplant_date: $('#transplantDate').val(),
            source_session_id: parseInt($('#sourceSessionId').val(), 10),
            recipient_session_id: parseInt($('#recipientSessionId').val(), 10),
            weight: parseFloat($('#transplantWeight').val()),
            fish_count: parseInt($('#transplantFishCount').val(), 10)
        };

        // Проверка, что сессии разные
        if (payload.source_session_id === payload.recipient_session_id) {
            showAlert('danger', 'Сессия отбора и сессия реципиент должны быть разными');
            return;
        }

        // Получаем предпросмотр для проверки необходимости создания микстовой посадки
        $.ajax({
            url: apiUrl(`api/partial_transplants.php?action=preview&source_session_id=${payload.source_session_id}&recipient_session_id=${payload.recipient_session_id}&weight=${payload.weight}&fish_count=${payload.fish_count}`),
            method: 'GET',
            dataType: 'json',
            success: function(previewResponse) {
                if (previewResponse.success && previewResponse.data.will_create_mixed_planting) {
                    // Показываем подтверждение с описанием действий
                    showMixedPlantingConfirmation(previewResponse.data, payload);
                } else {
                    // Обычная пересадка, выполняем сразу
                    performTransplant(payload);
                }
            },
            error: function() {
                // В случае ошибки preview выполняем пересадку как обычно
                performTransplant(payload);
            }
        });
    };

    function showMixedPlantingConfirmation(preview, payload) {
        const components = preview.mixed_planting_components || [];
        const componentsList = components.map(function(comp) {
            return `  • ${comp.planting_name}: ${comp.percentage.toFixed(2)}%`;
        }).join('\n');

        const message = `ВНИМАНИЕ! При выполнении пересадки будет создана микстовая посадка:\n\n` +
            `Название: ${preview.mixed_planting_name}\n\n` +
            `Компоненты:\n${componentsList}\n\n` +
            `Сессия "${preview.recipient_session.name}" будет обновлена для использования новой микстовой посадки.\n\n` +
            `Продолжить?`;

        if (!confirm(message)) {
            return;
        }

        performTransplant(payload);
    }

    function performTransplant(payload) {
        $.ajax({
            url: apiUrl('api/partial_transplants.php?action=create'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Пересадка успешно создана');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('transplantModal'));
                    if (modal) {
                        modal.hide();
                    }
                    loadTransplants();
                } else {
                    showAlert('danger', response.message || 'Не удалось создать пересадку');
                }
            },
            error: function(xhr, status, error) {
                console.error('saveTransplant error:', status, error, xhr.responseText);
                
                let errorMessage = 'Ошибка при создании пересадки';
                try {
                    if (xhr.responseText) {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.message) {
                            errorMessage = errorResponse.message;
                        }
                    }
                } catch (e) {
                    // Если не удалось распарсить JSON, используем общее сообщение
                }
                
                showAlert('danger', errorMessage);
            }
        });
    };

    window.confirmRevert = function(id) {
        if (!confirm('Вы уверены, что хотите откатить эту пересадку? Биомасса будет возвращена в сессию отбора.')) {
            return;
        }

        $.ajax({
            url: apiUrl('api/partial_transplants.php?action=revert'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: id }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message || 'Пересадка успешно откатана');
                    loadTransplants();
                } else {
                    showAlert('danger', response.message || 'Не удалось откатить пересадку');
                }
            },
            error: function(xhr, status, error) {
                console.error('revertTransplant error:', status, error, xhr.responseText);
                
                let errorMessage = 'Ошибка при откате пересадки';
                try {
                    if (xhr.responseText) {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.message) {
                            errorMessage = errorResponse.message;
                        }
                    }
                } catch (e) {
                    // Если не удалось распарсить JSON, используем общее сообщение
                }
                
                showAlert('danger', errorMessage);
            }
        });
    };

    function showAlert(type, message) {
        $('#alert-container').html(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateString) {
        if (!dateString) return '—';
        const date = new Date(dateString + 'T00:00:00');
        return date.toLocaleDateString('ru-RU');
    }

    function formatNumber(value, decimals) {
        if (value === null || value === undefined) return '—';
        return parseFloat(value).toFixed(decimals).replace('.', ',');
    }
})();

