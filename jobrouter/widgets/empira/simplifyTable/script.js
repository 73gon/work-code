// ============================================
// CONFIGURATION - Data from PHP
// ============================================

// Read data from PHP (injected via template.hbs)
var COLUMNS = [];
var DROPDOWN_OPTIONS = {};
var INITIAL_DATA = [];
var CURRENT_USER = '';
var DATA_ENDPOINT = 'dashboard/MyWidgets/SimplifyTable/query.php';

function normalizeDataRows(rows) {
  return (rows || []).map((row) => {
    const normalized = {};
    Object.keys(row || {}).forEach((key) => {
      const value = row[key];
      if (value && typeof value === 'object') {
        // Prefer label when provided, otherwise use id
        normalized[key] = value.label !== undefined ? value.label : value.id;
      } else {
        normalized[key] = value;
      }
    });
    return normalized;
  });
}

function normalizeDropdownOptions(options) {
  const mapOptionArray = (arr) =>
    (arr || []).map((opt) => {
      if (typeof opt === 'object' && opt !== null) {
        return opt.label !== undefined ? opt.label : opt.id;
      }
      return opt;
    });

  return {
    status: mapOptionArray(options.status),
    schritt: mapOptionArray(options.schritt),
    laufzeit: mapOptionArray(options.laufzeit),
    coor: mapOptionArray(options.coor),
    gesellschaft: mapOptionArray(options.gesellschaft),
    fonds: mapOptionArray(options.fonds),
  };
}

// Load configuration from hidden divs
function loadConfigFromPHP() {
  try {
    const columnsDiv = document.getElementById('simplifyTable_columns');
    const dropdownDiv = document.getElementById('simplifyTable_dropdownOptions');
    const dataDiv = document.getElementById('simplifyTable_tableData');
    const userDiv = document.getElementById('simplifyTable_currentUser');

    if (columnsDiv && columnsDiv.textContent) {
      COLUMNS = JSON.parse(columnsDiv.textContent);
    }

    if (dropdownDiv && dropdownDiv.textContent) {
      DROPDOWN_OPTIONS = normalizeDropdownOptions(JSON.parse(dropdownDiv.textContent) || {});
    }

    if (dataDiv && dataDiv.textContent) {
      INITIAL_DATA = JSON.parse(dataDiv.textContent);
    }

    if (userDiv && userDiv.textContent) {
      CURRENT_USER = userDiv.textContent.trim();
    }

    if (!Array.isArray(INITIAL_DATA)) {
      console.warn('Table data from PHP was not an array; defaulting to empty list');
      INITIAL_DATA = [];
    }
  } catch (error) {
    console.error('Error loading configuration from PHP:', error);
    INITIAL_DATA = [];
  }
}

// ============================================
// APPLICATION STATE
// ============================================

var appState = {
  data: [],
  filteredData: [],
  filters: {
    schritt: 'all',
    kreditor: '',
    rechnungsdatumFrom: '',
    rechnungsdatumTo: '',
    status: 'all',
    weiterbelasten: '',
    gesellschaft: [],
    rolle: '',
    rechnungstyp: '',
    bruttobetrag: '',
    fonds: [],
    dokumentId: '',
    bearbeiter: '',
    rechnungsnummer: '',
    laufzeit: 'all',
    coor: 'all',
  },
  currentPage: 1,
  itemsPerPage: 25,
  totalItems: 0,
  sortColumn: null,
  sortDirection: 'asc',
  loading: false,
};

// ============================================
// UTILITY FUNCTIONS
// ============================================

function formatDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// Safely lowercase values to avoid runtime errors on undefined/null
function safeLower(value) {
  return value === null || value === undefined ? '' : value.toString().toLowerCase();
}

function setLoading(isLoading) {
  appState.loading = isLoading;
  const spinner = document.getElementById('simplifyTable_spinner');
  if (spinner) {
    spinner.style.display = isLoading ? 'flex' : 'none';
  }
}

// ============================================
// UI CREATION FUNCTIONS
// ============================================

function createFilterItem(label, type, id, filterKey, options = null) {
  const wrapper = document.createElement('div');
  wrapper.className = 'simplifyTable_filter-item';

  const labelEl = document.createElement('label');
  labelEl.textContent = label;
  labelEl.htmlFor = id;
  labelEl.className = 'simplifyTable_filter-label';

  if (type === 'dropdown') {
    const select = document.createElement('select');
    select.id = id;
    select.className = 'simplifyTable_filter-select';

    const allOption = document.createElement('option');
    allOption.value = 'all';
    allOption.textContent = `Alle`;
    select.appendChild(allOption);

    options.forEach((opt) => {
      const option = document.createElement('option');
      option.value = opt;
      option.textContent = opt;
      select.appendChild(option);
    });

    wrapper.appendChild(labelEl);
    wrapper.appendChild(select);
  } else if (type === 'autocomplete') {
    const searchWrapper = document.createElement('div');
    searchWrapper.className = 'simplifyTable_search-wrapper';

    const inputContainer = document.createElement('div');
    inputContainer.className = 'simplifyTable_multiselect-input-container';
    inputContainer.id = `${id}-container`;

    const input = document.createElement('input');
    input.type = 'text';
    input.id = id;
    input.className = 'simplifyTable_filter-input simplifyTable_autocomplete-input simplifyTable_multiselect-input';
    input.autocomplete = 'off';
    if (options) {
      input.dataset.options = JSON.stringify(options);
    }

    const autocompleteList = document.createElement('div');
    autocompleteList.className = 'simplifyTable_autocomplete-list';
    autocompleteList.id = `${id}-autocomplete`;
    autocompleteList.style.display = 'none';

    inputContainer.appendChild(input);
    searchWrapper.appendChild(inputContainer);
    searchWrapper.appendChild(autocompleteList);

    wrapper.appendChild(labelEl);
    wrapper.appendChild(searchWrapper);
  } else if (type === 'date') {
    const input = document.createElement('input');
    input.type = 'date';
    input.id = id;
    input.className = 'simplifyTable_filter-input';

    wrapper.appendChild(labelEl);
    wrapper.appendChild(input);
    S;
  } else if (type === 'daterange') {
    const dateRangeContainer = document.createElement('div');
    dateRangeContainer.className = 'simplifyTable_date-range-container';

    const fromInput = document.createElement('input');
    fromInput.type = 'date';
    fromInput.id = options.fromId;
    fromInput.className = 'simplifyTable_filter-input simplifyTable_date-range-input';

    const separator = document.createElement('span');
    separator.className = 'simplifyTable_date-range-separator';
    separator.textContent = '-';

    const toInput = document.createElement('input');
    toInput.type = 'date';
    toInput.id = options.toId;
    toInput.className = 'simplifyTable_filter-input simplifyTable_date-range-input';

    dateRangeContainer.appendChild(fromInput);
    dateRangeContainer.appendChild(separator);
    dateRangeContainer.appendChild(toInput);

    wrapper.appendChild(labelEl);
    wrapper.appendChild(dateRangeContainer);
  } else {
    const input = document.createElement('input');
    input.type = 'text';
    input.id = id;
    input.className = 'simplifyTable_filter-input';

    wrapper.appendChild(labelEl);
    wrapper.appendChild(input);
  }

  return wrapper;
}

function createFilters() {
  const filterContainer = document.createElement('div');
  filterContainer.className = 'simplifyTable_filter-container';

  const filters = [
    // Process & Status
    { label: 'Status', type: 'dropdown', id: 'simplifyTable_status-filter', key: 'status', options: DROPDOWN_OPTIONS.status },
    { label: 'Schritt', type: 'dropdown', id: 'simplifyTable_schritt-filter', key: 'schritt', options: DROPDOWN_OPTIONS.schritt },
    { label: 'DokumentId', type: 'text', id: 'simplifyTable_dokumentId-filter', key: 'dokumentId' },

    // People & Roles
    { label: 'Bearbeiter', type: 'text', id: 'simplifyTable_bearbeiter-filter', key: 'bearbeiter' },
    { label: 'Rolle', type: 'text', id: 'simplifyTable_rolle-filter', key: 'rolle' },

    // Company & Financial Structure
    {
      label: 'Gesellschaft',
      type: 'autocomplete',
      id: 'simplifyTable_gesellschaft-filter',
      key: 'gesellschaft',
      options: DROPDOWN_OPTIONS.gesellschaft,
    },
    { label: 'Fonds', type: 'autocomplete', id: 'simplifyTable_fonds-filter', key: 'fonds', options: DROPDOWN_OPTIONS.fonds },
    { label: 'Kreditor', type: 'text', id: 'simplifyTable_kreditor-filter', key: 'kreditor' },

    // Invoice Details
    { label: 'Rechnungsnummer', type: 'text', id: 'simplifyTable_rechnungsnummer-filter', key: 'rechnungsnummer' },
    { label: 'Rechnungstyp', type: 'text', id: 'simplifyTable_rechnungstyp-filter', key: 'rechnungstyp' },
    {
      label: 'Rechnungsdatum',
      type: 'daterange',
      id: 'simplifyTable_rechnungsdatum-filter',
      key: 'rechnungsdatum',
      options: { fromId: 'simplifyTable_rechnungsdatum-from-filter', toId: 'simplifyTable_rechnungsdatum-to-filter' },
    },
    { label: 'Bruttobetrag', type: 'text', id: 'simplifyTable_bruttobetrag-filter', key: 'bruttobetrag' },

    // Additional Options
    { label: 'Weiterbelasten', type: 'text', id: 'simplifyTable_weiterbelasten-filter', key: 'weiterbelasten' },
    { label: 'Laufzeit', type: 'dropdown', id: 'simplifyTable_laufzeit-filter', key: 'laufzeit', options: DROPDOWN_OPTIONS.laufzeit },
    { label: 'Coor', type: 'dropdown', id: 'simplifyTable_coor-filter', key: 'coor', options: DROPDOWN_OPTIONS.coor },
  ];

  filters.forEach((filter) => {
    const wrapper = createFilterItem(filter.label, filter.type, filter.id, filter.key, filter.options);
    filterContainer.appendChild(wrapper);
  });

  const actionsRow = document.createElement('div');
  actionsRow.className = 'simplifyTable_filter-actions';

  const applyButton = document.createElement('button');
  applyButton.id = 'simplifyTable_apply-filter-button';
  applyButton.className = 'simplifyTable_apply-filter-button';
  applyButton.innerHTML = '<i class="fas fa-check"></i> Filter anwenden';

  const resetButton = document.createElement('button');
  resetButton.id = 'simplifyTable_reset-button';
  resetButton.className = 'simplifyTable_reset-button';
  resetButton.innerHTML = '<i class="fas fa-redo"></i> Filter zurücksetzen';

  actionsRow.appendChild(applyButton);
  actionsRow.appendChild(resetButton);
  filterContainer.appendChild(actionsRow);

  return filterContainer;
}

function createTableRow(item) {
  const row = document.createElement('tr');
  row.className = 'simplifyTable_table-row';

  COLUMNS.forEach((column) => {
    const cell = document.createElement('td');
    cell.className = 'simplifyTable_table-cell';

    // Apply alignment
    if (column.align) {
      cell.style.textAlign = column.align;
    }

    const value = item[column.id];

    if (column.type === 'status') {
      cell.textContent = value || '-';
    } else if (column.type === 'currency') {
      const numValue = parseFloat(value);
      cell.textContent = value ? `€ ${numValue.toLocaleString('de-DE', { minimumFractionDigits: 2 })}` : '-';
      cell.className += ' simplifyTable_amount-cell';
      if (numValue < 0) {
        cell.className += ' simplifyTable_negative-amount';
      }
    } else if (column.type === 'date') {
      cell.textContent = value ? new Date(value).toLocaleDateString('de-DE') : '-';
    } else if (column.type === 'number') {
      cell.textContent = value;
    } else {
      cell.textContent = value || '-';
    }

    row.appendChild(cell);
  });

  return row;
}

function createTable() {
  const tableWrapper = document.createElement('div');
  tableWrapper.className = 'simplifyTable_table-wrapper';

  const table = document.createElement('table');
  table.className = 'simplifyTable_data-table';

  const thead = document.createElement('thead');
  const headerRow = document.createElement('tr');

  COLUMNS.forEach((column) => {
    const th = document.createElement('th');
    th.className = 'simplifyTable_table-header simplifyTable_sortable';
    th.dataset.column = column.id;
    th.style.cursor = 'pointer';

    // Apply alignment
    if (column.align) {
      th.style.textAlign = column.align;
    }

    const headerContent = document.createElement('span');
    headerContent.textContent = column.label;
    th.appendChild(headerContent);

    const sortIconContainer = document.createElement('span');
    sortIconContainer.className = 'simplifyTable_sort-icon-container';

    const sortUpIcon = document.createElement('i');
    sortUpIcon.className = 'fas fa-caret-up simplifyTable_sort-arrow simplifyTable_sort-arrow-up';
    if (appState.sortColumn === column.id && appState.sortDirection === 'asc') {
      sortUpIcon.classList.add('simplifyTable_active');
    }

    const sortDownIcon = document.createElement('i');
    sortDownIcon.className = 'fas fa-caret-down simplifyTable_sort-arrow simplifyTable_sort-arrow-down';
    if (appState.sortColumn === column.id && appState.sortDirection === 'desc') {
      sortDownIcon.classList.add('simplifyTable_active');
    }

    sortIconContainer.appendChild(sortUpIcon);
    sortIconContainer.appendChild(sortDownIcon);
    th.appendChild(sortIconContainer);

    th.addEventListener('click', () => sortTable(column.id));

    headerRow.appendChild(th);
  });

  thead.appendChild(headerRow);
  table.appendChild(thead);

  const tbody = document.createElement('tbody');
  tbody.id = 'simplifyTable_table-body';

  const paginatedData = appState.filteredData;

  paginatedData.forEach((item) => {
    const row = createTableRow(item);
    tbody.appendChild(row);
  });

  table.appendChild(tbody);
  tableWrapper.appendChild(table);

  if (appState.filteredData.length === 0) {
    const noResults = document.createElement('div');
    noResults.className = 'simplifyTable_no-results';
    noResults.innerHTML = '<i class="fas fa-clipboard-list"></i><p>Es wurden keine Einträge gefunden</p>';
    tableWrapper.appendChild(noResults);
  }

  return tableWrapper;
}

function createPagination() {
  const paginationContainer = document.createElement('div');
  paginationContainer.className = 'simplifyTable_pagination-container';

  const itemsPerPageWrapper = document.createElement('div');
  itemsPerPageWrapper.className = 'simplifyTable_items-per-page';

  const itemsLabel = document.createElement('span');
  itemsLabel.textContent = 'Einträge pro Seite:';

  const itemsSelect = document.createElement('select');
  itemsSelect.id = 'simplifyTable_items-per-page';
  itemsSelect.className = 'simplifyTable_items-per-page-select';

  [10, 25, 50, 100].forEach((value) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = value;
    option.selected = value === appState.itemsPerPage;
    itemsSelect.appendChild(option);
  });

  itemsPerPageWrapper.appendChild(itemsLabel);
  itemsPerPageWrapper.appendChild(itemsSelect);

  const totalPages = Math.max(1, Math.ceil(appState.totalItems / appState.itemsPerPage));
  const start = appState.totalItems === 0 ? 0 : (appState.currentPage - 1) * appState.itemsPerPage + 1;
  const end = appState.totalItems === 0 ? 0 : Math.min(start + appState.filteredData.length - 1, appState.totalItems);

  const paginationInfo = document.createElement('div');
  paginationInfo.className = 'simplifyTable_pagination-info';
  paginationInfo.textContent = `${start}-${end} von ${appState.totalItems} Einträgen`;

  const paginationButtons = document.createElement('div');
  paginationButtons.className = 'simplifyTable_pagination-buttons';

  const prevBtn = document.createElement('button');
  prevBtn.className = 'simplifyTable_pagination-btn';
  prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
  prevBtn.disabled = appState.currentPage === 1;
  prevBtn.onclick = () => changePage(appState.currentPage - 1);

  const pageInfo = document.createElement('span');
  pageInfo.className = 'simplifyTable_page-info';
  pageInfo.textContent = `Seite ${appState.currentPage} von ${totalPages || 1}`;

  const nextBtn = document.createElement('button');
  nextBtn.className = 'simplifyTable_pagination-btn';
  nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
  nextBtn.disabled = appState.currentPage >= totalPages;
  nextBtn.onclick = () => changePage(appState.currentPage + 1);

  paginationButtons.appendChild(prevBtn);
  paginationButtons.appendChild(pageInfo);
  paginationButtons.appendChild(nextBtn);

  paginationContainer.appendChild(itemsPerPageWrapper);
  paginationContainer.appendChild(paginationInfo);
  paginationContainer.appendChild(paginationButtons);

  return paginationContainer;
}

// ============================================
// STATE MANAGEMENT FUNCTIONS
// ============================================

function sortTable(columnId) {
  if (appState.sortColumn === columnId) {
    appState.sortDirection = appState.sortDirection === 'asc' ? 'desc' : 'asc';
  } else {
    appState.sortColumn = columnId;
    appState.sortDirection = 'asc';
  }

  appState.currentPage = 1;
  fetchData(1);
}

function changePage(page) {
  appState.currentPage = page;
  fetchData(page);
}

function changeItemsPerPage(value) {
  appState.itemsPerPage = parseInt(value);
  appState.currentPage = 1;
  fetchData(1);
}

function buildRequestParams(pageOverride) {
  const filters = appState.filters;
  const params = {
    page: pageOverride || appState.currentPage,
    perPage: appState.itemsPerPage,
    sortColumn: appState.sortColumn || '',
    sortDirection: appState.sortDirection,
    username: CURRENT_USER,
  };

  ['kreditor', 'weiterbelasten', 'rolle', 'rechnungstyp', 'bruttobetrag', 'dokumentId', 'bearbeiter', 'rechnungsnummer'].forEach((key) => {
    if (filters[key]) params[key] = filters[key];
  });

  if (filters.gesellschaft && filters.gesellschaft.length > 0) {
    params.gesellschaft = JSON.stringify(filters.gesellschaft);
  }
  if (filters.fonds && filters.fonds.length > 0) {
    params.fonds = JSON.stringify(filters.fonds);
  }
  if (filters.schritt && filters.schritt !== 'all') params.schritt = filters.schritt;
  if (filters.status && filters.status !== 'all') params.status = filters.status;
  if (filters.laufzeit && filters.laufzeit !== 'all') params.laufzeit = filters.laufzeit;
  if (filters.coor && filters.coor !== 'all') params.coor = filters.coor;
  if (filters.rechnungsdatumFrom) params.rechnungsdatumFrom = filters.rechnungsdatumFrom;
  if (filters.rechnungsdatumTo) params.rechnungsdatumTo = filters.rechnungsdatumTo;

  return params;
}

function fetchData(page = 1) {
  const params = buildRequestParams(page);
  setLoading(true);
  return $j
    .ajax({
      url: DATA_ENDPOINT,
      type: 'GET',
      data: params,
      dataType: 'json',
    })
    .done((response) => {
      appState.currentPage = response.page || page;
      appState.itemsPerPage = response.perPage || appState.itemsPerPage;
      appState.totalItems = response.total || 0;
      appState.filteredData = normalizeDataRows(response.data || []);
      updateTable();
    })
    .fail((jqXHR, textStatus, errorThrown) => {
      console.error('Error fetching data', textStatus, errorThrown);
    })
    .always(() => setLoading(false));
}

function applyFilters() {
  appState.currentPage = 1;
  fetchData(1);
}

function resetFilters() {
  appState.filters = {
    schritt: 'all',
    kreditor: '',
    rechnungsdatumFrom: '',
    rechnungsdatumTo: '',
    status: 'all',
    weiterbelasten: '',
    gesellschaft: [],
    rolle: '',
    rechnungstyp: '',
    bruttobetrag: '',
    fonds: [],
    dokumentId: '',
    bearbeiter: '',
    rechnungsnummer: '',
    laufzeit: 'all',
    coor: 'all',
  };

  document.querySelectorAll('.simplifyTable_filter-input').forEach((input) => (input.value = ''));
  document.querySelectorAll('.simplifyTable_filter-select').forEach((select) => (select.value = 'all'));

  renderTags('gesellschaft');
  renderTags('fonds');

  applyFilters();
}

// ============================================
// AUTOCOMPLETE FUNCTIONS
// ============================================

function showAutocomplete(field, inputElement) {
  const autocompleteList = document.getElementById(`simplifyTable_${field}-filter-autocomplete`);
  if (!autocompleteList) return;

  const inputValue = inputElement.value.toLowerCase();

  let allOptions = [];
  if (inputElement.dataset.options) {
    allOptions = JSON.parse(inputElement.dataset.options);
  } else {
    allOptions = [...new Set(appState.data.map((item) => item[field]))].filter((v) => v);
  }

  const filtered = inputValue ? allOptions.filter((value) => value.toLowerCase().includes(inputValue)) : allOptions;

  const selectedValues = appState.filters[field] || [];
  const availableOptions = filtered.filter((value) => !selectedValues.some((selected) => selected.toLowerCase() === value.toLowerCase()));

  if (availableOptions.length > 0) {
    autocompleteList.innerHTML = '';
    availableOptions.forEach((value) => {
      const item = document.createElement('div');
      item.className = 'simplifyTable_autocomplete-item';
      item.textContent = value;
      item.onclick = () => {
        addTag(field, value);
        inputElement.value = '';
        showAutocomplete(field, inputElement);
      };
      autocompleteList.appendChild(item);
    });
    autocompleteList.style.display = 'block';
  } else {
    autocompleteList.style.display = 'none';
  }
}

function addTag(field, value) {
  if (!appState.filters[field].includes(value)) {
    appState.filters[field].push(value);
    renderTags(field);
  }
}

function removeTag(field, index) {
  appState.filters[field].splice(index, 1);
  renderTags(field);
}

function renderTags(field) {
  const container = document.getElementById(`simplifyTable_${field}-filter-container`);
  const input = document.getElementById(`simplifyTable_${field}-filter`);
  if (!container || !input) return;

  container.querySelectorAll('.simplifyTable_tag').forEach((tag) => tag.remove());

  appState.filters[field].forEach((value, index) => {
    const tag = document.createElement('span');
    tag.className = 'simplifyTable_tag';
    tag.title = value;

    const textSpan = document.createElement('span');
    textSpan.textContent = value;

    const removeBtn = document.createElement('span');
    removeBtn.className = 'simplifyTable_tag-remove';
    removeBtn.textContent = '×';
    removeBtn.onclick = (e) => {
      e.stopPropagation();
      removeTag(field, index);
    };

    tag.appendChild(textSpan);
    tag.appendChild(removeBtn);
    container.insertBefore(tag, input);
  });
}

function hideAllAutocomplete() {
  document.querySelectorAll('.simplifyTable_autocomplete-list').forEach((list) => {
    list.style.display = 'none';
  });
}

// ============================================
// UPDATE FUNCTIONS
// ============================================

function updateTable() {
  const tbody = document.getElementById('simplifyTable_table-body');
  tbody.innerHTML = '';

  const paginatedData = appState.filteredData;

  paginatedData.forEach((item) => {
    const row = createTableRow(item);
    tbody.appendChild(row);
  });

  updatePagination();
  updateSortArrows();

  const tableWrapper = tbody.closest('.simplifyTable_table-wrapper');
  let noResults = tableWrapper.querySelector('.simplifyTable_no-results');

  if (appState.filteredData.length === 0) {
    if (!noResults) {
      noResults = document.createElement('div');
      noResults.className = 'simplifyTable_no-results';
      noResults.innerHTML = '<i class="fas fa-clipboard-list"></i><p>Es wurden keine Einträge gefunden</p>';
      tableWrapper.appendChild(noResults);
    }
  } else if (noResults) {
    noResults.remove();
  }
}

function updatePagination() {
  const totalPages = Math.max(1, Math.ceil(appState.totalItems / appState.itemsPerPage));
  const start = appState.totalItems === 0 ? 0 : (appState.currentPage - 1) * appState.itemsPerPage + 1;
  const end = appState.totalItems === 0 ? 0 : Math.min(start + appState.filteredData.length - 1, appState.totalItems);

  const paginationInfo = document.querySelector('.simplifyTable_pagination-info');
  if (paginationInfo) {
    paginationInfo.textContent = `${start}-${end} von ${appState.totalItems} Einträgen`;
  }

  const pageInfo = document.querySelector('.simplifyTable_page-info');
  if (pageInfo) {
    pageInfo.textContent = `Seite ${appState.currentPage} von ${totalPages || 1}`;
  }

  const prevBtn = document.querySelector('.simplifyTable_pagination-buttons button:first-child');
  const nextBtn = document.querySelector('.simplifyTable_pagination-buttons button:last-child');

  if (prevBtn) prevBtn.disabled = appState.currentPage === 1;
  if (nextBtn) nextBtn.disabled = appState.currentPage >= totalPages;
}

function updateSortArrows() {
  document.querySelectorAll('.simplifyTable_sort-arrow').forEach((arrow) => {
    arrow.classList.remove('simplifyTable_active');
  });

  if (appState.sortColumn) {
    const headers = document.querySelectorAll('.simplifyTable_table-header');
    headers.forEach((header) => {
      if (header.dataset.column === appState.sortColumn) {
        const upArrow = header.querySelector('.simplifyTable_sort-arrow-up');
        const downArrow = header.querySelector('.simplifyTable_sort-arrow-down');

        if (appState.sortDirection === 'asc' && upArrow) {
          upArrow.classList.add('simplifyTable_active');
        } else if (appState.sortDirection === 'desc' && downArrow) {
          downArrow.classList.add('simplifyTable_active');
        }
      }
    });
  }
}

// ============================================
// EVENT LISTENERS
// ============================================

function attachEventListeners() {
  ['kreditor', 'weiterbelasten', 'rolle', 'rechnungstyp', 'bruttobetrag', 'dokumentId', 'bearbeiter', 'rechnungsnummer'].forEach((key) => {
    const input = document.getElementById(`simplifyTable_${key}-filter`);
    if (input) {
      input.addEventListener('input', (e) => {
        appState.filters[key] = e.target.value.toLowerCase();
      });
    }
  });

  ['gesellschaft', 'fonds'].forEach((key) => {
    const input = document.getElementById(`simplifyTable_${key}-filter`);
    const container = document.getElementById(`simplifyTable_${key}-filter-container`);

    if (input) {
      input.addEventListener('input', () => {
        showAutocomplete(key, input);
      });
      input.addEventListener('focus', () => {
        showAutocomplete(key, input);
      });
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && input.value === '') {
          if (appState.filters[key].length > 0) {
            removeTag(key, appState.filters[key].length - 1);
          }
        }
      });
    }

    if (container) {
      container.addEventListener('click', () => {
        input.focus();
      });
    }
  });

  ['schritt', 'status', 'laufzeit', 'coor'].forEach((key) => {
    const select = document.getElementById(`simplifyTable_${key}-filter`);
    if (select) {
      select.addEventListener('change', (e) => {
        appState.filters[key] = e.target.value;
      });
    }
  });

  const rechnungsdatumFrom = document.getElementById('simplifyTable_rechnungsdatum-from-filter');
  if (rechnungsdatumFrom) {
    rechnungsdatumFrom.addEventListener('change', (e) => {
      appState.filters.rechnungsdatumFrom = e.target.value;
    });
  }

  const rechnungsdatumTo = document.getElementById('simplifyTable_rechnungsdatum-to-filter');
  if (rechnungsdatumTo) {
    rechnungsdatumTo.addEventListener('change', (e) => {
      appState.filters.rechnungsdatumTo = e.target.value;
    });
  }

  const itemsPerPageSelect = document.getElementById('simplifyTable_items-per-page');
  if (itemsPerPageSelect) {
    itemsPerPageSelect.addEventListener('change', (e) => {
      changeItemsPerPage(e.target.value);
    });
  }

  const applyButton = document.getElementById('simplifyTable_apply-filter-button');
  if (applyButton) {
    applyButton.addEventListener('click', () => applyFilters());
  }

  const resetButton = document.getElementById('simplifyTable_reset-button');
  if (resetButton) {
    resetButton.addEventListener('click', () => resetFilters());
  }

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.simplifyTable_search-wrapper')) {
      hideAllAutocomplete();
    }
  });

  document.querySelectorAll('.simplifyTable_filter-input, .simplifyTable_filter-select').forEach((element) => {
    element.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        applyFilters();
      }
    });
  });
}

// ============================================
// RENDER FUNCTION
// ============================================

function render() {
  const app = document.getElementById('simplifyTable_app');
  app.innerHTML = '';

  const container = document.createElement('div');
  container.className = 'simplifyTable_container';

  container.appendChild(createFilters());
  container.appendChild(createTable());
  container.appendChild(createPagination());

  const spinnerOverlay = document.createElement('div');
  spinnerOverlay.id = 'simplifyTable_spinner';
  spinnerOverlay.className = 'simplifyTable_spinner-overlay';
  spinnerOverlay.innerHTML = '<div class="simplifyTable_spinner"></div>';
  container.appendChild(spinnerOverlay);

  app.appendChild(container);
}

// ============================================
// INITIALIZATION
// ============================================
// INITIALIZATION
// ============================================

function init() {
  // Load configuration and data from PHP
  loadConfigFromPHP();

  // Initialize application state with data from PHP
  appState.data = [];
  appState.filteredData = [];
  appState.totalItems = 0;

  // Render the UI
  render();
  attachEventListeners();
  fetchData(1);
}

init();
$j('#simplifyTable_app').parent().toggleClass('simplifyTable_background', true);

(function addWidgetBackground() {
  const appEl = document.getElementById('simplifyTable_app');
  if (!appEl) return;

  // Try to add the background directly to the dashboard label element
  const container = appEl.closest('.grid-stack-item-content');
  const labelEl = container ? container.querySelector('.jr-dashboard-label') : null;

  if (labelEl) {
    labelEl.classList.add('simplifyTable_background-label');
  } else if (container) {
    container.classList.add('simplifyTable_background-label');
  }
})();
