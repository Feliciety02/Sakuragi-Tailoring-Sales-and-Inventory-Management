(function () {
  'use strict';

  var managers = {};

  function normalizeText(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function lowerText(value) {
    return normalizeText(value).toLowerCase();
  }

  function parseSortable(value) {
    var text = normalizeText(value);
    var dateValue = Date.parse(text);
    if (!Number.isNaN(dateValue) && /[-/,:]/.test(text)) {
      return { type: 'number', value: dateValue };
    }

    var numeric = parseFloat(text.replace(/[^0-9.\-]/g, ''));
    if (!Number.isNaN(numeric) && /[0-9]/.test(text)) {
      return { type: 'number', value: numeric };
    }

    return { type: 'string', value: text.toLowerCase() };
  }

  function exportRows(table, rows, type) {
    var headers = Array.from(table.querySelectorAll('thead th')).map(function (th) {
      return '"' + normalizeText(th.textContent).replace(/"/g, '""') + '"';
    });

    var csvLines = [headers.join(',')];
    rows.forEach(function (row) {
      var cols = Array.from(row.querySelectorAll('td')).map(function (td) {
        return '"' + normalizeText(td.textContent).replace(/"/g, '""') + '"';
      });
      csvLines.push(cols.join(','));
    });

    if (type === 'excel') {
      var html = '<table><thead><tr>' + Array.from(table.querySelectorAll('thead th')).map(function (th) {
        return '<th>' + normalizeText(th.textContent) + '</th>';
      }).join('') + '</tr></thead><tbody>' + rows.map(function (row) {
        return '<tr>' + Array.from(row.querySelectorAll('td')).map(function (td) {
          return '<td>' + normalizeText(td.textContent) + '</td>';
        }).join('') + '</tr>';
      }).join('') + '</tbody></table>';
      var excelBlob = new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
      downloadBlob(excelBlob, (table.id || 'table') + '.xls');
      return;
    }

    var csvBlob = new Blob(['\ufeff' + csvLines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    downloadBlob(csvBlob, (table.id || 'table') + '.csv');
  }

  function downloadBlob(blob, filename) {
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
  }

  function initTable(table) {
    if (!table || table.dataset.dtReady === 'true') {
      return;
    }

    var wrapper = table.closest('.data-table-wrapper');
    if (!wrapper) {
      return;
    }

    table.dataset.dtReady = 'true';

    var tbody = table.querySelector('tbody');
    var allRows = Array.from(tbody.querySelectorAll('tr'));
    var searchInput = wrapper.querySelector('.search-bar-input');
    var filterColumn = wrapper.querySelector('.dt-filter-column');
    var filterValue = wrapper.querySelector('.dt-filter-value');
    var pageSizeSelect = wrapper.querySelector('.dt-page-size-select');
    var paginationEl = wrapper.querySelector('.dt-pagination');
    var infoEl = wrapper.querySelector('.dt-info');
    var emptyState = wrapper.querySelector('.data-table-empty-state');
    var loadingEl = wrapper.querySelector('.data-table-loading');
    var exportButtons = wrapper.querySelectorAll('.dt-export-btn');
    var pageSize = parseInt(table.dataset.pageSize || '10', 10);

    var state = {
      search: '',
      sortIndex: null,
      sortDir: 'asc',
      filterIndex: '',
      filterValue: '',
      page: 1,
      pageSize: Number.isNaN(pageSize) ? 10 : pageSize
    };

    function getFilteredRows() {
      return allRows.filter(function (row) {
        var searchPass = !state.search || lowerText(row.textContent).indexOf(state.search) !== -1;
        if (!searchPass) {
          return false;
        }

        if (state.filterIndex === '' || state.filterValue === '') {
          return true;
        }

        var cell = row.querySelector('td[data-column-index="' + state.filterIndex + '"]');
        return cell && normalizeText(cell.dataset.filterValue || cell.textContent) === state.filterValue;
      });
    }

    function sortRows(rows) {
      if (state.sortIndex === null) {
        return rows;
      }

      return rows.slice().sort(function (rowA, rowB) {
        var cellA = rowA.querySelector('td[data-column-index="' + state.sortIndex + '"]');
        var cellB = rowB.querySelector('td[data-column-index="' + state.sortIndex + '"]');
        var valueA = parseSortable(cellA ? (cellA.dataset.sortValue || cellA.textContent) : '');
        var valueB = parseSortable(cellB ? (cellB.dataset.sortValue || cellB.textContent) : '');

        if (valueA.type === 'number' && valueB.type === 'number') {
          return state.sortDir === 'asc' ? valueA.value - valueB.value : valueB.value - valueA.value;
        }

        return state.sortDir === 'asc'
          ? String(valueA.value).localeCompare(String(valueB.value))
          : String(valueB.value).localeCompare(String(valueA.value));
      });
    }

    function updateSortIcons() {
      table.querySelectorAll('thead th .sort-icon').forEach(function (icon) {
        icon.className = 'fas fa-sort sort-icon';
      });

      if (state.sortIndex === null) {
        return;
      }

      var th = table.querySelector('thead th[data-column-index="' + state.sortIndex + '"]');
      var icon = th ? th.querySelector('.sort-icon') : null;
      if (!icon) {
        return;
      }

      icon.className = state.sortDir === 'asc' ? 'fas fa-sort-up sort-icon sort-active' : 'fas fa-sort-down sort-icon sort-active';
    }

    function updateFilterOptions() {
      if (!filterColumn || !filterValue) {
        return;
      }

      var selectedIndex = filterColumn.value;
      filterValue.innerHTML = '<option value="">All values</option>';
      filterValue.disabled = selectedIndex === '';

      if (selectedIndex === '') {
        state.filterValue = '';
        return;
      }

      var seen = {};
      allRows.forEach(function (row) {
        var cell = row.querySelector('td[data-column-index="' + selectedIndex + '"]');
        if (!cell) {
          return;
        }
        var value = normalizeText(cell.dataset.filterValue || cell.textContent);
        if (!value || seen[value]) {
          return;
        }
        seen[value] = true;
      });

      Object.keys(seen).sort(function (a, b) {
        return a.localeCompare(b);
      }).forEach(function (value) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        if (value === state.filterValue) {
          option.selected = true;
        }
        filterValue.appendChild(option);
      });
    }

    function renderPagination(totalPages) {
      if (!paginationEl) {
        return;
      }

      var html = '';

      if (totalPages > 1) {
        html += '<button type="button" class="dt-page-btn" data-page="prev"' + (state.page === 1 ? ' disabled' : '') + '><i class="fas fa-chevron-left"></i></button>';

        var maxVisible = 7;
        var startPage = 1;
        var endPage = totalPages;

        if (totalPages > maxVisible + 2) {
          var half = Math.floor(maxVisible / 2);
          startPage = Math.max(1, state.page - half);
          endPage = Math.min(totalPages, state.page + half);

          if (startPage <= 2) {
            startPage = 1;
            endPage = maxVisible;
          }
          if (endPage >= totalPages - 1) {
            startPage = Math.max(1, totalPages - maxVisible + 1);
            endPage = totalPages;
          }
        }

        if (startPage > 1) {
          html += '<button type="button" class="dt-page-btn" data-page="1">1</button>';
          if (startPage > 2) {
            html += '<span class="dt-page-ellipsis">…</span>';
          }
        }

        for (var page = startPage; page <= endPage; page++) {
          html += '<button type="button" class="dt-page-btn' + (page === state.page ? ' active' : '') + '" data-page="' + page + '">' + page + '</button>';
        }

        if (endPage < totalPages) {
          if (endPage < totalPages - 1) {
            html += '<span class="dt-page-ellipsis">…</span>';
          }
          html += '<button type="button" class="dt-page-btn" data-page="' + totalPages + '">' + totalPages + '</button>';
        }

        html += '<button type="button" class="dt-page-btn" data-page="next"' + (state.page === totalPages ? ' disabled' : '') + '><i class="fas fa-chevron-right"></i></button>';
      }

      html += '<span class="dt-page-info">Page ' + state.page + ' of ' + totalPages + '</span>';
      paginationEl.innerHTML = html;

      paginationEl.querySelectorAll('.dt-page-btn').forEach(function (button) {
        button.addEventListener('click', function () {
          var target = button.dataset.page;
          if (target === 'prev' && state.page > 1) {
            state.page -= 1;
          } else if (target === 'next' && state.page < totalPages) {
            state.page += 1;
          } else if (target !== 'prev' && target !== 'next') {
            state.page = parseInt(target, 10);
          }
          render();
        });
      });
    }

    function render() {
      var filteredRows = sortRows(getFilteredRows());
      var totalRows = filteredRows.length;
      var totalPages = Math.max(1, Math.ceil(totalRows / state.pageSize));

      if (state.page > totalPages) {
        state.page = totalPages;
      }

      var start = (state.page - 1) * state.pageSize;
      var end = start + state.pageSize;
      var visibleRows = filteredRows.slice(start, end);

      tbody.innerHTML = '';
      visibleRows.forEach(function (row) {
        tbody.appendChild(row);
      });

      if (emptyState) {
        emptyState.hidden = totalRows !== 0;
      }

      if (infoEl) {
        if (totalRows === 0) {
          infoEl.textContent = 'No entries found';
        } else {
          infoEl.textContent = 'Showing ' + (start + 1) + ' to ' + Math.min(totalRows, end) + ' of ' + totalRows + ' entries';
        }
      }

      renderPagination(totalPages);
      updateSortIcons();
    }

    table.querySelectorAll('thead th.is-sortable').forEach(function (th) {
      th.addEventListener('click', function () {
        var index = parseInt(th.dataset.columnIndex, 10);
        if (state.sortIndex === index) {
          state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
          state.sortIndex = index;
          state.sortDir = 'asc';
        }
        state.page = 1;
        render();
      });
    });

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        state.search = lowerText(searchInput.value);
        state.page = 1;
        render();
      });
    }

    if (filterColumn) {
      filterColumn.addEventListener('change', function () {
        state.filterIndex = filterColumn.value;
        state.filterValue = '';
        updateFilterOptions();
        state.page = 1;
        render();
      });
    }

    if (filterValue) {
      filterValue.addEventListener('change', function () {
        state.filterValue = filterValue.value;
        state.page = 1;
        render();
      });
    }

    if (pageSizeSelect) {
      pageSizeSelect.value = String(state.pageSize);
      pageSizeSelect.addEventListener('change', function () {
        state.pageSize = parseInt(pageSizeSelect.value, 10) || 10;
        state.page = 1;
        render();
      });
    }

    exportButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        exportRows(table, sortRows(getFilteredRows()), button.dataset.export || 'csv');
      });
    });

    updateFilterOptions();
    render();

    if (loadingEl) {
      loadingEl.classList.add('is-ready');
    }

    managers[table.id] = {
      export: function (type) {
        exportRows(table, sortRows(getFilteredRows()), type || 'csv');
      }
    };
  }

  function initAll() {
    document.querySelectorAll('.data-table').forEach(initTable);
  }

  window.SakuragiDataTable = {
    exportTable: function (tableId, type) {
      var manager = managers[tableId];
      if (manager) {
        manager.export(type || 'csv');
        return;
      }

      var table = document.getElementById(tableId);
      if (!table) {
        return;
      }

      initTable(table);
      managers[tableId]?.export(type || 'csv');
    },
    initAll: initAll
  };

  document.addEventListener('DOMContentLoaded', initAll);
})();
