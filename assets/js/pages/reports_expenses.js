(function() {
    'use strict';
    var form = document.getElementById('expensesReportFilters');
    var alertBox = document.getElementById('expensesReportAlert');
    var card = document.getElementById('expensesReportCard');
    var tbody = document.getElementById('expensesReportBody');
    var totalEl = document.getElementById('expensesReportTotal');
    var exportBtn = document.getElementById('expensesExportExcel');

    if (!form || !tbody) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        loadReport();
    });
    if (exportBtn) exportBtn.addEventListener('click', exportToExcel);

    function showAlert(msg, type) {
        if (!alertBox) return;
        alertBox.className = 'alert alert-' + (type || 'info');
        alertBox.textContent = msg;
        alertBox.style.display = 'block';
    }
    function hideAlert() {
        if (alertBox) alertBox.style.display = 'none';
    }

    function loadReport() {
        hideAlert();
        card.style.display = 'none';
        var dateFrom = document.getElementById('expensesDateFrom').value.trim() || null;
        var dateTo = document.getElementById('expensesDateTo').value.trim() || null;
        var params = new URLSearchParams();
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        showAlert('Загрузка...', 'info');
        fetch((window.BASE_URL || '/') + 'api/reports.php?action=expenses&' + params.toString())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                hideAlert();
                if (!data || !data.success) {
                    showAlert(data && data.message ? data.message : 'Ошибка загрузки', 'danger');
                    return;
                }
                renderTable(data.data.items || [], data.data.total);
                card.style.display = 'block';
            })
            .catch(function() {
                showAlert('Ошибка при загрузке отчёта', 'danger');
            });
    }

    function formatDate(s) {
        if (!s) return '—';
        var d = new Date(s);
        return isNaN(d.getTime()) ? s : d.toLocaleDateString('ru-RU');
    }
    function formatMoney(n) {
        return Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
    function escapeHtml(t) {
        if (t == null) return '';
        var div = document.createElement('div');
        div.textContent = t;
        return div.innerHTML;
    }

    function renderTable(items, total) {
        tbody.innerHTML = '';
        items.forEach(function(row) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + formatDate(row.record_date) + '</td><td>' + escapeHtml(row.title) + '</td>' +
                '<td class="text-end">' + formatMoney(row.amount) + '</td><td>' + escapeHtml(row.comment || '') + '</td>';
            tbody.appendChild(tr);
        });
        if (totalEl) totalEl.textContent = formatMoney(total || 0);
    }

    function exportToExcel() {
        if (typeof XLSX === 'undefined') {
            alert('Библиотека Excel не загружена');
            return;
        }
        var table = document.getElementById('expensesReportTable');
        if (!table || !tbody || !tbody.rows.length) {
            alert('Нет данных для выгрузки. Сначала нажмите «Показать».');
            return;
        }
        var rows = [];
        var headerCells = table.querySelector('thead tr').cells;
        var headerRow = [];
        for (var i = 0; i < headerCells.length; i++) headerRow.push(headerCells[i].textContent.trim());
        rows.push(headerRow);
        for (var r = 0; r < tbody.rows.length; r++) {
            var cells = tbody.rows[r].cells;
            var arr = [];
            for (var c = 0; c < cells.length; c++) arr.push(cells[c].textContent.trim());
            rows.push(arr);
        }
        var totalRow = ['', 'Итого:', (totalEl && totalEl.textContent) || '0', ''];
        rows.push(totalRow);
        var ws = XLSX.utils.aoa_to_sheet(rows);
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Затраты');
        XLSX.writeFile(wb, 'otchet_zatraty_' + (new Date().toISOString().slice(0, 10)) + '.xlsx');
    }
})();
