/**
 * JavaScript для страницы отчета по отборам
 */

(function() {
    'use strict';

    const filtersForm = document.getElementById('reportFilters');
    const reportResults = document.getElementById('reportResults');
    const reportTableBody = document.getElementById('reportTableBody');
    const noDataMessage = document.getElementById('noDataMessage');
    const printReportBtn = document.getElementById('printReport');
    const totalWeightEl = document.getElementById('totalWeight');
    const totalFishCountEl = document.getElementById('totalFishCount');
    const totalAvgWeightEl = document.getElementById('totalAvgWeight');

    // Инициализация выпадающего списка с чекбоксами для контрагентов
    initCounterpartyDropdown();

    // Обработчик отправки формы фильтров
    filtersForm.addEventListener('submit', function(e) {
        e.preventDefault();
        generateReport();
    });

    // Обработчик кнопки печати
    printReportBtn.addEventListener('click', function() {
        printReport();
    });

    /**
     * Инициализирует выпадающий список с чекбоксами для контрагентов
     */
    function initCounterpartyDropdown() {
        const selectAllCheckbox = document.getElementById('counterpartySelectAll');
        const checkboxes = document.querySelectorAll('.counterparty-checkbox');
        const dropdownButton = document.getElementById('counterpartyDropdown');
        const dropdownText = dropdownButton.querySelector('.dropdown-text');

        // Обработчик "Выбрать все"
        selectAllCheckbox.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateDropdownText();
        });

        // Обработчики для отдельных чекбоксов
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                // Обновляем состояние "Выбрать все"
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                const noneChecked = Array.from(checkboxes).every(cb => !cb.checked);
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = !allChecked && !noneChecked;
                
                updateDropdownText();
            });
        });

        // Обновление текста кнопки
        function updateDropdownText() {
            const checkedBoxes = Array.from(checkboxes).filter(cb => cb.checked);
            if (checkedBoxes.length === 0) {
                dropdownText.textContent = 'Все контрагенты';
            } else if (checkedBoxes.length === checkboxes.length) {
                dropdownText.textContent = 'Все контрагенты';
            } else if (checkedBoxes.length === 1) {
                dropdownText.textContent = checkedBoxes[0].dataset.name;
            } else {
                dropdownText.textContent = `Выбрано: ${checkedBoxes.length}`;
            }
        }

        // Предотвращаем закрытие dropdown при клике внутри
        const dropdownMenu = document.getElementById('counterpartyDropdownMenu');
        dropdownMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    /**
     * Генерирует отчет на основе фильтров
     */
    function generateReport() {
        const formData = new FormData(filtersForm);
        const params = new URLSearchParams();
        
        // Обрабатываем контрагентов отдельно
        const counterpartyCheckboxes = document.querySelectorAll('.counterparty-checkbox:checked');
        counterpartyCheckboxes.forEach(checkbox => {
            params.append('counterparty_id[]', checkbox.value);
        });
        
        // Обрабатываем остальные поля формы
        for (const [key, value] of formData.entries()) {
            if (value && key !== 'counterparty_id[]') {
                params.append(key, value);
            }
        }

        // Показываем индикатор загрузки
        reportResults.style.display = 'none';
        noDataMessage.style.display = 'none';
        reportTableBody.innerHTML = '<tr><td colspan="7" class="text-center">Загрузка...</td></tr>';

        fetch(`${window.BASE_URL}api/reports.php?action=harvests&${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReport(data.data);
                } else {
                    showError(data.message || 'Ошибка при формировании отчета');
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке отчета:', error);
                showError('Произошла ошибка при загрузке отчета');
            });
    }

    /**
     * Отображает результаты отчета
     */
    function displayReport(data) {
        const harvests = data.harvests || [];
        const totals = data.totals || { weight: 0, fish_count: 0 };
        const filters = data.filters || {};

        if (harvests.length === 0) {
            reportResults.style.display = 'none';
            noDataMessage.style.display = 'block';
            printReportBtn.style.display = 'none';
            return;
        }

        // Очищаем таблицу
        reportTableBody.innerHTML = '';

        // Заполняем таблицу данными
        harvests.forEach(harvest => {
            const avgWeight = harvest.fish_count > 0 
                ? harvest.weight / harvest.fish_count 
                : 0;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${formatDate(harvest.recorded_at)}</td>
                <td>${escapeHtml(harvest.pool_name || '-')}</td>
                <td>${escapeHtml(harvest.planting_name || '-')}</td>
                <td>${escapeHtml(harvest.counterparty_name || '-')}</td>
                <td class="text-end">${formatNumber(harvest.weight, 2)}</td>
                <td class="text-end">${formatNumber(harvest.fish_count, 0)}</td>
                <td class="text-end">${formatNumber(avgWeight, 3)}</td>
            `;
            reportTableBody.appendChild(row);
        });

        // Обновляем итоги
        totalWeightEl.textContent = formatNumber(totals.weight, 2);
        totalFishCountEl.textContent = formatNumber(totals.fish_count, 0);
        
        // Вычисляем общую среднюю навеску
        const totalAvgWeight = totals.fish_count > 0 
            ? totals.weight / totals.fish_count 
            : 0;
        totalAvgWeightEl.textContent = formatNumber(totalAvgWeight, 3);

        // Сохраняем данные для печати
        window.reportData = data;

        // Показываем результаты
        reportResults.style.display = 'block';
        noDataMessage.style.display = 'none';
        printReportBtn.style.display = 'inline-block';
    }

    /**
     * Печатает отчет
     */
    function printReport() {
        if (!window.reportData) {
            return;
        }

        const data = window.reportData;
        const harvests = data.harvests || [];
        const totals = data.totals || { weight: 0, fish_count: 0 };
        const filters = data.filters || {};

        // Формируем заголовок отчета
        const dateFrom = filters.date_from ? formatDate(filters.date_from) : '';
        const dateTo = filters.date_to ? formatDate(filters.date_to) : '';
        const dateRange = dateFrom && dateTo ? `${dateFrom} - ${dateTo}` : (dateFrom || dateTo || 'Весь период');
        
        document.getElementById('printTitle').textContent = `Отчет по отгрузкам за ${dateRange}`;

        // Формируем информацию о фильтрах
        const filterInfo = [];
        if (filters.counterparty_names && filters.counterparty_names.length > 0) {
            filterInfo.push(`Контрагент: ${filters.counterparty_names.join(', ')}`);
        } else {
            filterInfo.push('Контрагент: Все');
        }
        if (filters.planting_name) {
            filterInfo.push(`Аквакультура: ${filters.planting_name}`);
        } else {
            filterInfo.push('Аквакультура: Все');
        }
        document.getElementById('printFilters').innerHTML = filterInfo.join('<br>');

        // Заполняем таблицу для печати
        const printTableBody = document.getElementById('printTableBody');
        printTableBody.innerHTML = '';

        harvests.forEach(harvest => {
            const avgWeight = harvest.fish_count > 0 
                ? harvest.weight / harvest.fish_count 
                : 0;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${formatDate(harvest.recorded_at)}</td>
                <td>${escapeHtml(harvest.pool_name || '-')}</td>
                <td>${escapeHtml(harvest.planting_name || '-')}</td>
                <td>${escapeHtml(harvest.counterparty_name || '-')}</td>
                <td class="text-end">${formatNumber(harvest.weight, 2)}</td>
                <td class="text-end">${formatNumber(harvest.fish_count, 0)}</td>
                <td class="text-end">${formatNumber(avgWeight, 3)}</td>
            `;
            printTableBody.appendChild(row);
        });

        // Обновляем итоги для печати
        document.getElementById('printTotalWeight').textContent = formatNumber(totals.weight, 2);
        document.getElementById('printTotalFishCount').textContent = formatNumber(totals.fish_count, 0);
        
        // Вычисляем общую среднюю навеску для печати
        const totalAvgWeight = totals.fish_count > 0 
            ? totals.weight / totals.fish_count 
            : 0;
        document.getElementById('printTotalAvgWeight').textContent = formatNumber(totalAvgWeight, 3);

        // Создаем новое окно для печати
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        
        if (!printWindow) {
            alert('Пожалуйста, разрешите всплывающие окна для печати отчета');
            return;
        }
        
        // Получаем контент для печати
        const printContent = document.getElementById('printContent');
        const printContentClone = printContent.cloneNode(true);
        printContentClone.style.display = 'block';
        
        // Исправляем пути к изображениям в клонированном элементе для абсолютных путей
        const logoImg = printContentClone.querySelector('.print-logo img');
        if (logoImg) {
            const logoSrc = logoImg.getAttribute('src');
            if (logoSrc) {
                // Преобразуем относительный путь в абсолютный
                let absoluteSrc = logoSrc;
                if (!logoSrc.startsWith('http') && !logoSrc.startsWith('//')) {
                    if (logoSrc.startsWith('/')) {
                        absoluteSrc = window.location.origin + logoSrc;
                    } else {
                        const baseUrl = window.BASE_URL || window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/');
                        absoluteSrc = baseUrl + logoSrc.replace(/^\.\.\//, '');
                    }
                }
                logoImg.setAttribute('src', absoluteSrc);
            }
        }
        
        // Формируем HTML для печати
        const printHTML = `
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчет по отгрузкам</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: black;
        }
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }
        .print-logo {
            margin-bottom: 20px;
        }
        .print-logo img {
            max-height: 80px;
            max-width: 200px;
        }
        .print-title {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
            font-family: 'Bitter', serif;
        }
        .print-filters {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        .print-table-wrapper {
            margin-top: 20px;
        }
        .print-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .print-table th,
        .print-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        .print-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .print-table tfoot .print-totals {
            font-weight: bold;
            background-color: #e0e0e0;
        }
        .print-table .text-end {
            text-align: right;
        }
        @media print {
            body {
                margin: 0;
                padding: 20px;
            }
            .print-header {
                page-break-after: avoid;
            }
            .print-table tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    ${printContentClone.innerHTML}
</body>
</html>`;
        
        // Записываем HTML в новое окно
        printWindow.document.write(printHTML);
        printWindow.document.close();
        
        // Ждем загрузки и печатаем
        printWindow.onload = function() {
            printWindow.print();
            printWindow.close();
        };
    }

    /**
     * Форматирует дату для отображения
     */
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('ru-RU', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    }

    /**
     * Форматирует число для отображения
     */
    function formatNumber(value, decimals) {
        return parseFloat(value).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    /**
     * Экранирует HTML
     */
    function escapeHtml(text) {
        if (text === null || text === undefined) return '-';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Показывает сообщение об ошибке
     */
    function showError(message) {
        reportResults.style.display = 'none';
        noDataMessage.style.display = 'block';
        noDataMessage.className = 'alert alert-danger';
        noDataMessage.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${escapeHtml(message)}`;
        printReportBtn.style.display = 'none';
    }
})();

