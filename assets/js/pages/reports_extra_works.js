(function() {
    'use strict';
    var form = document.getElementById('extraWorksReportFilters');
    var alertBox = document.getElementById('extraWorksReportAlert');
    var card = document.getElementById('extraWorksReportCard');
    var tbody = document.getElementById('extraWorksReportBody');
    var totalEl = document.getElementById('extraWorksReportTotal');
    var exportBtn = document.getElementById('extraWorksExportExcel');
    var exportPdfBtn = document.getElementById('extraWorksExportPdf');
    var printBtn = document.getElementById('extraWorksPrint');

    if (!form || !tbody) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        loadReport();
    });
    if (exportBtn) exportBtn.addEventListener('click', exportToExcel);
    if (exportPdfBtn) exportPdfBtn.addEventListener('click', exportToPdf);
    if (printBtn) printBtn.addEventListener('click', printReport);

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
        var dateFrom = document.getElementById('extraWorksDateFrom').value.trim() || null;
        var dateTo = document.getElementById('extraWorksDateTo').value.trim() || null;
        var assignedTo = document.getElementById('extraWorksAssignedTo').value.trim() || null;
        var params = new URLSearchParams();
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        if (assignedTo) params.set('assigned_to', assignedTo);
        showAlert('Загрузка...', 'info');
        fetch((window.BASE_URL || '/') + 'api/reports.php?action=extra_works&' + params.toString())
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
            var paid = row.is_paid ? 'Да' : 'Нет';
            tr.innerHTML = '<td>' + formatDate(row.work_date) + '</td><td>' + escapeHtml(row.title) + '</td>' +
                '<td>' + escapeHtml(row.description || '') + '</td><td>' + escapeHtml(row.assigned_name || '—') + '</td>' +
                '<td class="text-end">' + formatMoney(row.amount) + '</td><td>' + paid + '</td>';
            tbody.appendChild(tr);
        });
        if (totalEl) totalEl.textContent = formatMoney(total || 0);
    }

    function exportToExcel() {
        if (typeof XLSX === 'undefined') {
            alert('Библиотека Excel не загружена');
            return;
        }
        var table = document.getElementById('extraWorksReportTable');
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
        var totalRow = ['', '', '', 'Итого:', (totalEl && totalEl.textContent) || '0', ''];
        rows.push(totalRow);
        var ws = XLSX.utils.aoa_to_sheet(rows);
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Доп. работы');
        XLSX.writeFile(wb, 'otchet_dop_raboty_' + (new Date().toISOString().slice(0, 10)) + '.xlsx');
    }

    function exportToPdf() {
        if (!tbody || !tbody.rows.length) {
            alert('Нет данных для выгрузки. Сначала нажмите «Показать».');
            return;
        }
        if (typeof window.jspdf === 'undefined' || typeof window.jspdf.jsPDF === 'undefined') {
            alert('Библиотека PDF не загружена');
            return;
        }

        var jsPDF = window.jspdf.jsPDF;
        var doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

        var headers = [['Дата', 'Название', 'Описание', 'Исполнитель', 'Сумма, ₽', 'Оплачено']];
        var rows = [];
        for (var r = 0; r < tbody.rows.length; r++) {
            var cells = tbody.rows[r].cells;
            rows.push([
                cells[0].textContent.trim(),
                cells[1].textContent.trim(),
                cells[2].textContent.trim(),
                cells[3].textContent.trim(),
                cells[4].textContent.trim(),
                cells[5].textContent.trim()
            ]);
        }
        rows.push(['', '', '', 'Итого:', (totalEl && totalEl.textContent) || '0', '']);

        doc.setFontSize(12);
        doc.text('Отчёт о дополнительных работах', 40, 30);
        doc.autoTable({
            head: headers,
            body: rows,
            startY: 45,
            styles: { fontSize: 9, cellPadding: 4 },
            headStyles: { fillColor: [52, 58, 64] }
        });
        doc.save('otchet_dop_raboty_' + (new Date().toISOString().slice(0, 10)) + '.pdf');
    }

    function printReport() {
        if (!tbody || !tbody.rows.length) {
            alert('Нет данных для печати. Сначала нажмите «Показать».');
            return;
        }
        var tableHtml = document.getElementById('extraWorksReportTable').outerHTML;
        var html = '<!doctype html><html><head><meta charset="utf-8"><title>Отчёт о дополнительных работах</title>' +
            '<style>body{font-family:Arial,sans-serif;padding:20px}h2{margin-bottom:12px}' +
            'table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #777;padding:6px}' +
            'th{background:#eee}.text-end{text-align:right}</style></head><body>' +
            '<h2>Отчёт о дополнительных работах</h2>' + tableHtml + '</body></html>';
        var w = window.open('', '_blank');
        if (!w) {
            alert('Разрешите всплывающие окна для печати');
            return;
        }
        w.document.open();
        w.document.write(html);
        w.document.close();
        w.focus();
        w.print();
    }
})();
