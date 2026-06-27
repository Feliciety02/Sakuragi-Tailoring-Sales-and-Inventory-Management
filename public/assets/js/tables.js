function filterTableBySearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const tableInput = document.getElementById(tableId + '-search');
    const target = tableInput || input;

    if (target && input && target !== input) {
        target.value = input.value;
    }

    target?.dispatchEvent(new Event('input', { bubbles: true }));
}

function filterTableByStatus(selectId, tableId) {
    const source = document.getElementById(selectId);
    const wrapper = document.getElementById(tableId)?.closest('.data-table-wrapper');
    const columnSelect = wrapper?.querySelector('.dt-filter-column');
    const valueSelect = wrapper?.querySelector('.dt-filter-value');

    if (!source || !columnSelect || !valueSelect) {
        return;
    }

    if (!columnSelect.value) {
        const statusHeader = Array.from(document.querySelectorAll('#' + tableId + ' thead th')).find(function (th) {
            return (th.textContent || '').toLowerCase().includes('status');
        });
        if (statusHeader) {
            columnSelect.value = statusHeader.dataset.columnIndex || '';
            columnSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    valueSelect.value = source.value;
    valueSelect.dispatchEvent(new Event('change', { bubbles: true }));
}

function sortTableByColumn(tableId, columnIndex) {
    const header = document.querySelector('#' + tableId + ' thead th[data-column-index="' + columnIndex + '"]');
    header?.click();
}

function exportTableToCSV(tableId, filename = 'export.csv') {
    if (window.SakuragiDataTable) {
        window.SakuragiDataTable.exportTable(tableId, 'csv');
        return;
    }

    const rows = document.querySelectorAll(`#${tableId} tr`);
    const csv = [];

    rows.forEach(row => {
        const cols = row.querySelectorAll('th, td');
        const rowData = Array.from(cols).map(col => `"${(col.innerText || '').trim().replace(/"/g, '""')}"`);
        csv.push(rowData.join(','));
    });

    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}
