(function () {
    'use strict';

    if (window.__feedsPageInitialized) {
        return;
    }
    window.__feedsPageInitialized = true;

    const config = window.feedsConfig || {};
    const baseUrl = config.baseUrl || '';
    const apiBase = new URL('.', baseUrl || window.location.href).toString();
    const tableTemplate = (config.tableTemplate || '').trim();

    let currentFeedId = null;
    let feedsTableBody = null;
    let feedModalInstance = null;
    let normUploadInput = null;
    const feedChartsRegistry = {};
    const feedChartStates = {};

    document.addEventListener('DOMContentLoaded', initPage);

    function initPage() {
        feedsTableBody = document.getElementById('feedsTableBody');
        normUploadInput = document.getElementById('feedNormUploadInput');
        if (normUploadInput) {
            normUploadInput.addEventListener('change', handleNormUpload);
        }
        document.querySelectorAll('[data-feed-template]').forEach(btn => {
            btn.addEventListener('click', handleInsertTemplate);
        });
        document.querySelectorAll('[data-copy-template]').forEach(btn => {
            btn.addEventListener('click', handleCopyTemplate);
        });
        feedModalInstance = bootstrap.Modal.getOrCreateInstance(document.getElementById('feedModal'));
        loadFeeds();
    }

    function handleInsertTemplate(event) {
        event.preventDefault();
        if (!tableTemplate) {
            showAlert('warning', 'Шаблон ещё не загружен');
            return;
        }
        const targetSelector = event.currentTarget.getAttribute('data-feed-template');
        const textarea = targetSelector ? document.querySelector(targetSelector) : null;
        if (!textarea) {
            return;
        }
        textarea.value = tableTemplate;
        textarea.dispatchEvent(new Event('input'));
        textarea.focus();
    }

    function handleCopyTemplate(event) {
        event.preventDefault();
        const sourceSelector = event.currentTarget.getAttribute('data-copy-template');
        const source = sourceSelector ? document.querySelector(sourceSelector) : null;
        const text = source ? (source.value || source.textContent || '') : tableTemplate;
        if (!text) {
            showAlert('warning', 'Нет данных для копирования');
            return;
        }

        const copyPromise = navigator.clipboard && navigator.clipboard.writeText
            ? navigator.clipboard.writeText(text)
            : fallbackCopy(text);

        Promise.resolve(copyPromise)
            .then(() => showAlert('success', 'Шаблон скопирован в буфер обмена'))
            .catch(() => showAlert('danger', 'Не удалось скопировать шаблон'));
    }

    function fallbackCopy(text) {
        return new Promise((resolve, reject) => {
            try {
                const temp = document.createElement('textarea');
                temp.style.position = 'fixed';
                temp.style.opacity = '0';
                temp.value = text;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                resolve();
            } catch (error) {
                reject(error);
            }
        });
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
                loadFeedCharts();
            })
            .catch(error => {
                console.error('loadFeeds error:', error);
                showAlert('danger', error.message || 'Ошибка при загрузке кормов');
            });
    }

    function loadFeedCharts() {
        const container = document.getElementById('feedsChartsContainer');
        const legend = document.getElementById('feedsChartsLegend');
        if (!container) {
            return;
        }

        container.innerHTML = `
            <div class="text-center text-muted py-4 w-100">
                <div class="spinner-border spinner-border-sm me-2" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
                Обновляем графики...
            </div>
        `;
        if (legend) {
            legend.textContent = '';
        }
        destroyFeedCharts();

        fetch(apiUrl('api/feeds.php?action=chart_data'))
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Не удалось получить данные графиков');
                }
                renderFeedCharts(data.data || []);
            })
            .catch(error => {
                console.error('loadFeedCharts error:', error);
                showAlert('danger', error.message || 'Ошибка при загрузке графиков кормов');
                container.innerHTML = `
                    <div class="alert alert-warning w-100 mb-0">
                        Не удалось загрузить графики кормов. Попробуйте обновить страницу.
                    </div>
                `;
            });
    }

    function renderFeedCharts(feeds) {
        const container = document.getElementById('feedsChartsContainer');
        const legend = document.getElementById('feedsChartsLegend');
        if (!container) {
            return;
        }

        container.innerHTML = '';
        if (!feeds.length) {
            container.innerHTML = `
                <div class="text-center text-muted py-4 w-100">
                    Корма с заполненными YAML-таблицами не найдены.
                </div>
            `;
            if (legend) {
                legend.textContent = '';
            }
            return;
        }

        if (legend) {
            legend.textContent = `Всего кормов на графиках: ${feeds.length}`;
        }

        feeds.forEach(feed => {
            const strategies = Array.isArray(feed.strategies) ? feed.strategies : [];
            if (!strategies.length) {
                return;
            }

            const card = document.createElement('div');
            card.className = 'feed-chart-card';

            const title = document.createElement('div');
            title.className = 'feed-chart-header';
            title.innerHTML = `
                <div class="feed-chart-title">${escapeHtml(feed.name || 'Корм')}</div>
                <div class="feed-chart-meta">
                    ${feed.manufacturer ? `Производитель: ${escapeHtml(feed.manufacturer)}` : 'Производитель не указан'}
                    ${feed.granule ? ` · Гранула: ${escapeHtml(feed.granule)}` : ''}
                </div>
            `;
            card.appendChild(title);

            const chartState = {
                feedId: feed.id,
                strategies,
                currentKey: strategies[0].key,
                buttons: {},
                unitElement: null,
            };

            const tabsWrapper = document.createElement('div');
            tabsWrapper.className = 'feed-chart-tabs';

            const tabsGroup = document.createElement('div');
            tabsGroup.className = 'btn-group btn-group-sm';

            strategies.forEach((strategy, index) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-outline-secondary feed-chart-tab-btn';
                if (index === 0) {
                    btn.classList.add('active');
                }
                btn.dataset.feedId = feed.id;
                btn.dataset.strategyKey = strategy.key;
                btn.textContent = strategy.label || `Стратегия ${index + 1}`;
                btn.addEventListener('click', handleStrategyTabClick);
                chartState.buttons[strategy.key] = btn;
                tabsGroup.appendChild(btn);
            });

            tabsWrapper.appendChild(tabsGroup);

            const unitBadge = document.createElement('span');
            unitBadge.className = 'feed-chart-unit-badge text-muted small';
            unitBadge.textContent = strategies[0].unit || 'Кг корма / 100 кг биомассы';
            chartState.unitElement = unitBadge;
            tabsWrapper.appendChild(unitBadge);

            card.appendChild(tabsWrapper);

            const canvasWrapper = document.createElement('div');
            canvasWrapper.className = 'feed-chart-canvas-wrapper';
            const canvas = document.createElement('canvas');
            canvas.id = `feedChart-${feed.id}`;
            canvas.className = 'feed-chart-canvas';
            canvasWrapper.appendChild(canvas);
            card.appendChild(canvasWrapper);

            feedChartStates[feed.id] = {
                ...chartState,
                canvasId: canvas.id,
            };

            container.appendChild(card);

            setTimeout(() => renderFeedChart(feed.id, strategies[0]), 0);
        });
    }

    function handleStrategyTabClick(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const feedId = Number(button.dataset.feedId);
        const strategyKey = button.dataset.strategyKey;
        if (!feedId || !strategyKey) {
            return;
        }

        const state = feedChartStates[feedId];
        if (!state || state.currentKey === strategyKey) {
            return;
        }

        const strategy = state.strategies.find(item => item.key === strategyKey);
        if (!strategy) {
            return;
        }

        state.currentKey = strategyKey;
        Object.entries(state.buttons).forEach(([key, btn]) => {
            if (!btn) {
                return;
            }
            btn.classList.toggle('active', key === strategyKey);
        });
        if (state.unitElement) {
            state.unitElement.textContent = strategy.unit || 'Кг корма / 100 кг биомассы';
        }

        renderFeedChart(feedId, strategy);
    }

    function renderFeedChart(feedId, strategy) {
        const state = feedChartStates[feedId];
        if (!state) {
            return;
        }

        const canvas = document.getElementById(state.canvasId);
        if (!canvas) {
            return;
        }
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js is not loaded');
            return;
        }

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        const wrapper = canvas.parentElement;
        if (wrapper) {
            const computedHeight = wrapper.clientHeight || 260;
            const computedWidth = wrapper.clientWidth || canvas.clientWidth || 320;
            canvas.height = computedHeight;
            canvas.width = computedWidth;
        }

        if (feedChartsRegistry[feedId]) {
            try {
                feedChartsRegistry[feedId].destroy();
            } catch (error) {
                console.warn('renderFeedChart destroy error:', error);
            }
            feedChartsRegistry[feedId] = null;
        }

        const palette = [
            '#f6ad55', '#68d391', '#63b3ed', '#fc8181', '#b794f4',
            '#fbd38d', '#81e6d9', '#90cdf4', '#f687b3', '#cbd5f5',
        ];

        const datasets = (strategy.datasets || []).map((dataset, index) => ({
            label: dataset.label || `Диапазон ${index + 1}`,
            data: dataset.data || [],
            borderColor: palette[index % palette.length],
            backgroundColor: palette[index % palette.length],
            fill: false,
            spanGaps: true,
            tension: 0.25,
            pointRadius: 3,
            borderWidth: 2,
        }));

        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: strategy.temperatures || [],
                datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'nearest',
                    intersect: false,
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Температура, °C',
                        },
                    },
                    y: {
                        title: {
                            display: true,
                            text: strategy.unit || 'Кг корма / 100 кг биомассы',
                        },
                        beginAtZero: true,
                    },
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                const value = context.parsed.y;
                                if (value === null || value === undefined) {
                                    return `${context.dataset.label}: данных нет`;
                                }
                                return `${context.dataset.label}: ${value.toFixed(2)}`;
                            },
                        },
                    },
                },
            },
        });

        feedChartsRegistry[feedId] = chart;
    }

    function destroyFeedCharts() {
        Object.keys(feedChartsRegistry).forEach(key => {
            try {
                feedChartsRegistry[key].destroy();
            } catch (error) {
                console.warn('destroyFeedCharts error:', error);
            }
            delete feedChartsRegistry[key];
        });
        Object.keys(feedChartStates).forEach(key => delete feedChartStates[key]);
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
                document.getElementById('feedFormulaNormal').value = feed.formula_normal || '';
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
            formula_normal: document.getElementById('feedFormulaNormal').value.trim(),
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

