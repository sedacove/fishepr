(function () {
    if (typeof window.DEBUG_STATS === 'undefined') {
        return;
    }

    const ajaxDebugRegistry = Array.isArray(window.DEBUG_STATS.ajax)
        ? [...window.DEBUG_STATS.ajax]
        : [];
    window.DEBUG_STATS.ajax = ajaxDebugRegistry;

    document.addEventListener('DOMContentLoaded', () => {
        const dot = document.getElementById('debugInfoDot');
        const overlay = document.getElementById('debugInfoOverlay');
        if (!dot || !overlay) {
            return;
        }

        const closeBtn = overlay.querySelector('[data-debug-close]');
        const summaryList = overlay.querySelector('[data-debug-summary]');
        const queriesContainer = overlay.querySelector('[data-debug-queries]');
        const ajaxContainer = overlay.querySelector('[data-debug-ajax]');

        function formatSummaryRow(label, value) {
            return `<li><span>${label}</span><strong>${value}</strong></li>`;
        }

        function renderPanel() {
            const stats = window.DEBUG_STATS || {};
            const summaryHtml = [
                formatSummaryRow('Время генерации', `${stats.execution_time_ms ?? 0} ms`),
                formatSummaryRow('SQL-запросы', `${stats.queries_count ?? 0} шт (${stats.queries_time_ms ?? 0} ms)`),
                formatSummaryRow('Память', `${stats.memory_usage_mb ?? 0} MB`),
                formatSummaryRow('Пик памяти', `${stats.peak_memory_mb ?? 0} MB`),
                formatSummaryRow('PHP', stats.php_version || ''),
                formatSummaryRow('URI', stats.request_uri || ''),
                formatSummaryRow('Сгенерировано', stats.generated_at || '')
            ].join('');
            summaryList.innerHTML = summaryHtml;

            renderAjaxList(stats, ajaxContainer);

            const queries = Array.isArray(stats.queries) ? stats.queries : [];
            if (!queries.length) {
                queriesContainer.innerHTML = '<p class="text-muted mb-0">Запросы не выполнялись</p>';
                return;
            }

            queriesContainer.innerHTML = queries.map((query, index) => {
                const params = query.params && Object.keys(query.params).length
                    ? `<div class="text-muted small mb-1">Параметры: ${JSON.stringify(query.params)}</div>`
                    : '';
                return `
                    <div class="debug-query-item">
                        <div class="small text-muted mb-1">#${index + 1} • ${query.duration_ms} ms</div>
                        <code>${escapeHtml(query.sql || '')}</code>
                        ${params}
                    </div>
                `;
            }).join('');
        }

        function showPanel() {
            renderPanel();
            overlay.classList.add('show');
        }

        function hidePanel() {
            overlay.classList.remove('show');
        }

        dot.addEventListener('click', showPanel);
        closeBtn?.addEventListener('click', hidePanel);
        overlay.addEventListener('click', event => {
            if (event.target === overlay) {
                hidePanel();
            }
        });
    });

    instrumentFetchForDebug(ajaxDebugRegistry);
    instrumentJqueryAjax(ajaxDebugRegistry);

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function renderAjaxList(stats, container) {
        const ajaxContainer = container || document.querySelector('[data-debug-ajax]');
        if (!ajaxContainer) {
            return;
        }
        const ajaxEntries = (stats && stats.ajax) || [];
        if (!ajaxEntries.length) {
            ajaxContainer.innerHTML = '<p class="text-muted mb-0">Запросов не зафиксировано</p>';
            return;
        }
        ajaxContainer.innerHTML = ajaxEntries.slice(-10).reverse().map(entry => {
            const summary = entry.stats || {};
            return `
                <div class="debug-query-item">
                    <div class="small text-muted mb-1">
                        ${entry.method || 'GET'} ${escapeHtml(entry.url || '')}
                    </div>
                    <div class="small mb-1">
                        <strong>${entry.duration_ms ?? 0} ms</strong> • ${entry.status || '-'}
                    </div>
                    <div class="small text-muted">
                        SQL: ${summary.queries_count ?? 0} шт (${summary.queries_time_ms ?? 0} ms)
                    </div>
                </div>
            `;
        }).join('');
    }

    function instrumentFetchForDebug(registry) {
        if (window.__fetchInstrumented || typeof window.fetch !== 'function') {
            return;
        }
        window.__fetchInstrumented = true;
        const originalFetch = window.fetch;
        window.fetch = function(resource, config = {}) {
            const start = performance.now();
            return originalFetch(resource, config).then(async response => {
                const elapsed = performance.now() - start;
                try {
                    const cloned = response.clone();
                    const data = await cloned.json().catch(() => null);
                    if (data && data._debug) {
                        recordAjaxDebugEntry(registry, {
                            url: typeof resource === 'string' ? resource : (resource && resource.url) || '',
                            method: (config.method || 'GET').toUpperCase(),
                            status: response.status,
                            duration_ms: Math.round(elapsed),
                            stats: data._debug,
                        });
                    }
                } catch (err) {
                    console.warn('Debug fetch instrumentation error:', err);
                }
                return response;
            });
        };
    }

    function instrumentJqueryAjax(registry) {
        if (!window.jQuery || window.__jqueryDebugInstrumented) {
            return;
        }
        window.__jqueryDebugInstrumented = true;
        const $ = window.jQuery;
        const originalAjax = $.ajax;

        $.ajax = function(url, options) {
            let settings = {};
            let requestUrl = '';

            if (typeof url === 'string') {
                requestUrl = url;
                settings = options || {};
            } else {
                settings = url || {};
                requestUrl = settings.url || '';
            }

            const method = (settings.type || settings.method || 'GET').toUpperCase();
            const start = performance.now();
            const jqXHR = originalAjax.apply($, arguments);

            jqXHR.always(function(data, textStatus, jqXHRRef) {
                const elapsed = performance.now() - start;
                try {
                    const response = jqXHRRef || jqXHR;
                    const payload = response && response.responseJSON
                        ? response.responseJSON
                        : safeParseJson(response && response.responseText);

                    if (payload && payload._debug) {
                        recordAjaxDebugEntry(registry, {
                            url: requestUrl || (response && response.responseURL) || '',
                            method,
                            status: response ? response.status : (jqXHR && jqXHR.status) || '',
                            duration_ms: Math.round(elapsed),
                            stats: payload._debug,
                        });
                    }
                } catch (error) {
                    console.warn('Debug ajax instrumentation error:', error);
                }
            });

            return jqXHR;
        };
    }

    function recordAjaxDebugEntry(registry, entry) {
        if (!Array.isArray(registry)) {
            return;
        }
        registry.push(entry);
        if (registry.length > 50) {
            registry.shift();
        }
        window.DEBUG_STATS.ajax = [...registry];
    }

    function safeParseJson(text) {
        if (!text || typeof text !== 'string') {
            return null;
        }
        try {
            return JSON.parse(text);
        } catch {
            return null;
        }
    }
})();
