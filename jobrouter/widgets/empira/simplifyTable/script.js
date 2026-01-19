// ============================================
// CONFIGURATION - Data from PHP
// ============================================

// Read data from PHP (injected via template.hbs)
var COLUMNS = [];
var DROPDOWN_OPTIONS = {};
var INITIAL_DATA = [];
var CURRENT_USER = '';
var DATA_ENDPOINT = 'dashboard/MyWidgets/SimplifyTable/query.php';
var SAVE_PREFERENCES_ENDPOINT = 'dashboard/MyWidgets/SimplifyTable/savePreferences.php';
var USER_PREFERENCES = null;

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
        return { id: opt.id, label: opt.label !== undefined ? opt.label : opt.id };
      }
      return { id: opt, label: opt };
    });

  return {
    status: mapOptionArray(options.status),
    schritt: mapOptionArray(options.schritt),
    laufzeit: mapOptionArray(options.laufzeit),
    coor: mapOptionArray(options.coor),
    weiterbelasten: mapOptionArray(options.weiterbelasten),
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

    const preferencesDiv = document.getElementById('simplifyTable_userPreferences');
    if (preferencesDiv && preferencesDiv.textContent) {
      try {
        USER_PREFERENCES = JSON.parse(preferencesDiv.textContent);
      } catch (e) {
        console.warn('Could not parse user preferences:', e);
      }
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
    schritt: [],
    kreditor: '',
    rechnungsdatumFrom: '',
    rechnungsdatumTo: '',
    status: 'all',
    weiterbelasten: 'all',
    gesellschaft: [],
    rolle: '',
    rechnungstyp: '',
    bruttobetragFrom: '',
    bruttobetragTo: '',
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
  columnOrder: [],
  dragState: {
    dragging: false,
    draggedColumn: null,
    dragOverColumn: null,
  },
  isInitialLoad: true,
  zoomLevel: 1.0,
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

function getOrderedColumns() {
  if (appState.columnOrder.length === 0) {
    return COLUMNS;
  }
  return appState.columnOrder.map((id) => COLUMNS.find((col) => col.id === id)).filter((col) => col !== undefined);
}

function saveColumnOrder() {
  try {
    localStorage.setItem('simplifyTable_columnOrder', JSON.stringify(appState.columnOrder));
  } catch (e) {
    console.warn('Could not save column order:', e);
  }
  // Also save to database
  savePreferencesToDatabase();
}

function savePreferencesToDatabase() {
  const preferences = {
    username: CURRENT_USER,
    filter: JSON.stringify(appState.filters),
    column_order: JSON.stringify(appState.columnOrder),
    sort_column: appState.sortColumn,
    sort_direction: appState.sortDirection,
    current_page: appState.currentPage,
    entries_per_page: appState.itemsPerPage,
    zoom_level: appState.zoomLevel,
  };

  $j.ajax({
    url: SAVE_PREFERENCES_ENDPOINT,
    type: 'POST',
    data: preferences,
    dataType: 'json',
  })
    .done((response) => {
      if (!response.success) {
        console.warn('Failed to save preferences:', response.message);
      }
    })
    .fail((jqXHR, textStatus, errorThrown) => {
      console.warn('Error saving preferences:', textStatus, errorThrown);
    });
}

function loadColumnOrder() {
  // Helper to validate and migrate column order
  const validateAndMigrateOrder = (order) => {
    if (!Array.isArray(order)) return null;

    // Get current valid column IDs
    const validIds = COLUMNS.map((col) => col.id);

    // Filter out old/invalid column IDs and migrate old ones
    let migratedOrder = order.filter((id) => id !== 'historyLink' && id !== 'invoice' && id !== 'protocol').filter((id) => validIds.includes(id));

    // Ensure 'actions' column is first if it exists in COLUMNS
    if (validIds.includes('actions') && !migratedOrder.includes('actions')) {
      migratedOrder.unshift('actions');
    }

    // Add any missing columns at the end
    validIds.forEach((id) => {
      if (!migratedOrder.includes(id)) {
        migratedOrder.push(id);
      }
    });

    return migratedOrder.length === validIds.length ? migratedOrder : null;
  };

  // First try to load from database (user preferences)
  if (USER_PREFERENCES && USER_PREFERENCES.column_order) {
    try {
      const order = USER_PREFERENCES.column_order;
      const migratedOrder = validateAndMigrateOrder(order);
      if (migratedOrder) {
        appState.columnOrder = migratedOrder;
        return;
      }
    } catch (e) {
      console.warn('Could not load column order from preferences:', e);
    }
  }

  // Fallback to localStorage
  try {
    const saved = localStorage.getItem('simplifyTable_columnOrder');
    if (saved) {
      const order = JSON.parse(saved);
      const migratedOrder = validateAndMigrateOrder(order);
      if (migratedOrder) {
        appState.columnOrder = migratedOrder;
        return;
      }
    }
  } catch (e) {
    console.warn('Could not load column order from localStorage:', e);
  }

  // Default: use original column order
  if (appState.columnOrder.length === 0) {
    appState.columnOrder = COLUMNS.map((col) => col.id);
  }
}

function loadUserPreferences() {
  if (!USER_PREFERENCES) return;

  // Load filters
  if (USER_PREFERENCES.filter) {
    // Parse filter if it's a string (from database)
    const filterData = typeof USER_PREFERENCES.filter === 'string' ? JSON.parse(USER_PREFERENCES.filter) : USER_PREFERENCES.filter;

    // Ensure array fields are always arrays
    const arrayFields = ['schritt', 'gesellschaft', 'fonds'];
    arrayFields.forEach((field) => {
      if (filterData[field] !== undefined) {
        if (!Array.isArray(filterData[field])) {
          // Convert string or other value to array
          filterData[field] = filterData[field] ? [filterData[field]] : [];
        }
      }
    });

    appState.filters = { ...appState.filters, ...filterData };
  }

  // Load sort column and direction
  if (USER_PREFERENCES.sort_column) {
    appState.sortColumn = USER_PREFERENCES.sort_column;
  }
  if (USER_PREFERENCES.sort_direction) {
    appState.sortDirection = USER_PREFERENCES.sort_direction;
  }

  // Load pagination
  if (USER_PREFERENCES.current_page) {
    appState.currentPage = USER_PREFERENCES.current_page;
  }
  if (USER_PREFERENCES.entries_per_page) {
    appState.itemsPerPage = USER_PREFERENCES.entries_per_page;
  }

  // Load zoom level
  if (USER_PREFERENCES.zoom_level) {
    appState.zoomLevel = parseFloat(USER_PREFERENCES.zoom_level);
  }
}

function reorderColumn(fromIndex, toIndex) {
  // Prevent moving the actions column (always first)
  if (fromIndex === 0 || toIndex === 0) {
    return;
  }
  const newOrder = [...appState.columnOrder];
  const [movedItem] = newOrder.splice(fromIndex, 1);
  newOrder.splice(toIndex, 0, movedItem);
  appState.columnOrder = newOrder;
  saveColumnOrder();
}

function setLoading(isLoading) {
  appState.loading = isLoading;
  const tbody = document.getElementById('simplifyTable_table-body');
  const tableWrapper = tbody ? tbody.closest('.simplifyTable_table-wrapper') : null;
  const table = tableWrapper ? tableWrapper.querySelector('.simplifyTable_data-table') : null;

  if (isLoading && tbody) {
    // Show table and skeleton rows during loading
    if (table) table.style.display = '';

    // Hide no-results message during loading
    if (tableWrapper) {
      const noResults = tableWrapper.querySelector('.simplifyTable_no-results');
      if (noResults) noResults.style.display = 'none';
    }

    // Show skeleton rows
    tbody.innerHTML = '';
    const skeletonRowCount = 25; // Number of skeleton rows to show

    for (let i = 0; i < skeletonRowCount; i++) {
      const row = document.createElement('tr');
      row.className = 'simplifyTable_skeleton-row';

      const orderedColumns = getOrderedColumns();
      orderedColumns.forEach((column) => {
        const td = document.createElement('td');
        td.className = 'simplifyTable_table-cell';
        if (column.align) {
          td.style.textAlign = column.align;
        }

        const skeleton = document.createElement('div');
        skeleton.className = 'simplifyTable_skeleton';

        // Vary skeleton widths for visual interest
        const widths = ['60%', '80%', '70%', '90%', '50%', '75%'];
        skeleton.style.width = widths[Math.floor(Math.random() * widths.length)];

        td.appendChild(skeleton);
        row.appendChild(td);
      });

      tbody.appendChild(row);
    }

    // Hide no-results message during loading
    if (tableWrapper) {
      const noResults = tableWrapper.querySelector('.simplifyTable_no-results');
      if (noResults) noResults.style.display = 'none';
    }
  }
}

function setZoom(level) {
  // Clamp zoom level between 0.5 and 2.0
  const newLevel = Math.max(0.5, Math.min(2.0, level));
  appState.zoomLevel = newLevel;

  const app = document.getElementById('simplifyTable_app');
  if (app) {
    app.style.transform = `scale(${newLevel})`;
    app.style.transformOrigin = 'top left';
    app.style.width = `${100 / newLevel}%`;
    app.style.height = `${100 / newLevel}%`;
  }
  if (!appState.isInitialLoad) {
    savePreferencesToDatabase();
  }
}

// ============================================
// UI CREATION FUNCTIONS
// ============================================

function createZoomControls() {
  const controls = document.createElement('div');
  controls.className = 'simplifyTable_zoom-controls';

  const zoomOutBtn = document.createElement('button');
  zoomOutBtn.className = 'simplifyTable_zoom-btn';
  zoomOutBtn.innerHTML = '<i class="fas fa-minus"></i>';
  zoomOutBtn.title = 'Zoom Out';
  zoomOutBtn.onclick = () => setZoom(appState.zoomLevel - 0.1);

  const resetBtn = document.createElement('button');
  resetBtn.className = 'simplifyTable_zoom-btn';
  resetBtn.innerHTML = '<i class="fas fa-undo"></i>';
  resetBtn.title = 'Reset Zoom';
  resetBtn.onclick = () => setZoom(1.0);

  const zoomInBtn = document.createElement('button');
  zoomInBtn.className = 'simplifyTable_zoom-btn';
  zoomInBtn.innerHTML = '<i class="fas fa-plus"></i>';
  zoomInBtn.title = 'Zoom In';
  zoomInBtn.onclick = () => setZoom(appState.zoomLevel + 0.1);

  controls.appendChild(zoomOutBtn);
  controls.appendChild(resetBtn);
  controls.appendChild(zoomInBtn);

  return controls;
}

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
      // Support both {id, label} objects and plain strings
      if (typeof opt === 'object' && opt !== null) {
        option.value = opt.id;
        option.textContent = opt.label;
      } else {
        option.value = opt;
        option.textContent = opt;
      }
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
  } else if (type === 'numberrange') {
    const numberRangeContainer = document.createElement('div');
    numberRangeContainer.className = 'simplifyTable_date-range-container';

    const fromInput = document.createElement('input');
    fromInput.type = 'number';
    fromInput.id = options.fromId;
    fromInput.className = 'simplifyTable_filter-input simplifyTable_date-range-input';
    fromInput.placeholder = 'Von';
    fromInput.step = '0.01';

    const separator = document.createElement('span');
    separator.className = 'simplifyTable_date-range-separator';
    separator.textContent = '-';

    const toInput = document.createElement('input');
    toInput.type = 'number';
    toInput.id = options.toId;
    toInput.className = 'simplifyTable_filter-input simplifyTable_date-range-input';
    toInput.placeholder = 'Bis';
    toInput.step = '0.01';

    numberRangeContainer.appendChild(fromInput);
    numberRangeContainer.appendChild(separator);
    numberRangeContainer.appendChild(toInput);

    wrapper.appendChild(labelEl);
    wrapper.appendChild(numberRangeContainer);
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
    { label: 'Schritt', type: 'autocomplete', id: 'simplifyTable_schritt-filter', key: 'schritt', options: DROPDOWN_OPTIONS.schritt },
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
    {
      label: 'Bruttobetrag',
      type: 'numberrange',
      id: 'simplifyTable_bruttobetrag-filter',
      key: 'bruttobetrag',
      options: { fromId: 'simplifyTable_bruttobetrag-from-filter', toId: 'simplifyTable_bruttobetrag-to-filter' },
    },

    // Additional Options
    {
      label: 'Weiterbelasten',
      type: 'dropdown',
      id: 'simplifyTable_weiterbelasten-filter',
      key: 'weiterbelasten',
      options: DROPDOWN_OPTIONS.weiterbelasten,
    },
    { label: 'Laufzeit', type: 'dropdown', id: 'simplifyTable_laufzeit-filter', key: 'laufzeit', options: DROPDOWN_OPTIONS.laufzeit },
    { label: 'Coor', type: 'dropdown', id: 'simplifyTable_coor-filter', key: 'coor', options: DROPDOWN_OPTIONS.coor },
  ];

  filters.forEach((filter) => {
    const wrapper = createFilterItem(filter.label, filter.type, filter.id, filter.key, filter.options);
    filterContainer.appendChild(wrapper);
  });

  const actionsRow = document.createElement('div');
  actionsRow.className = 'simplifyTable_filter-actions';

  // Preset buttons container (left side)
  const presetButtonsContainer = document.createElement('div');
  presetButtonsContainer.className = 'simplifyTable_preset-buttons';

  // Define filter presets - easily add more here
  // Use the 'id' values from dropdown options (check SimplifyTable.php for available IDs)
  const filterPresets = [
    {
      label: 'Überfällige Rechnungen',
      icon: 'fa-exclamation-triangle',
      filters: { status: 'faellig' },
      sort: { column: 'dueDate', direction: 'asc' },
    },
    {
      label: 'Coor Schnittstelle',
      icon: 'fa-exchange-alt',
      filters: { status: 'aktiv_alle', schritt: ['4001'] },
    },
  ];

  filterPresets.forEach((preset) => {
    const presetButton = document.createElement('button');
    presetButton.className = 'simplifyTable_preset-button';
    presetButton.innerHTML = `<i class="fas ${preset.icon}"></i> ${preset.label}`;
    presetButton.addEventListener('click', () => applyFilterPreset(preset));
    presetButtonsContainer.appendChild(presetButton);
  });

  // Action buttons container (right side)
  const actionButtonsContainer = document.createElement('div');
  actionButtonsContainer.className = 'simplifyTable_action-buttons';

  const applyButton = document.createElement('button');
  applyButton.id = 'simplifyTable_apply-filter-button';
  applyButton.className = 'simplifyTable_apply-filter-button';
  applyButton.innerHTML = '<i class="fas fa-check"></i> Filter anwenden';

  const resetButton = document.createElement('button');
  resetButton.id = 'simplifyTable_reset-button';
  resetButton.className = 'simplifyTable_reset-button';
  resetButton.innerHTML = '<i class="fas fa-redo"></i> Filter zurücksetzen';

  actionButtonsContainer.appendChild(applyButton);
  actionButtonsContainer.appendChild(resetButton);

  actionsRow.appendChild(presetButtonsContainer);
  actionsRow.appendChild(actionButtonsContainer);
  filterContainer.appendChild(actionsRow);

  return filterContainer;
}

function createTableRow(item) {
  const row = document.createElement('tr');
  row.className = 'simplifyTable_table-row';

  // Add row background class based on status
  const statusValue = (item.status || '').toLowerCase();
  if (statusValue === 'fällig' || statusValue === 'faellig' || statusValue === 'due') {
    row.classList.add('simplifyTable_row-due');
  } else if (statusValue === 'nicht fällig' || statusValue === 'nicht faellig' || statusValue === 'not_due' || statusValue === 'not_faellig') {
    row.classList.add('simplifyTable_row-not-due');
  }

  const orderedColumns = getOrderedColumns();
  orderedColumns.forEach((column) => {
    const cell = document.createElement('td');
    cell.className = 'simplifyTable_table-cell';
    cell.dataset.column = column.id;

    // Apply alignment
    if (column.align) {
      cell.style.textAlign = column.align;
    }

    const value = item[column.id];

    if (column.type === 'actions') {
      cell.className += ' simplifyTable_actions-cell';
      const actionsContainer = document.createElement('div');
      actionsContainer.className = 'simplifyTable_actions-container';

      // History link
      const historyValue = item.historyLink;
      if (historyValue) {
        const historyLink = document.createElement('a');
        historyLink.href = '#';
        historyLink.dataset.url = historyValue;
        historyLink.className = 'simplifyTable_history-link';
        historyLink.title = 'Vorgangshistorie anzeigen';

        const historyIcon = document.createElement('i');
        historyIcon.className = 'fa-solid fa-clock-rotate-left simplifyTable_history-icon';

        historyLink.appendChild(historyIcon);
        actionsContainer.appendChild(historyLink);

        historyLink.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          window.open(historyLink.dataset.url, '_blank', 'width=1000,height=700,resizable=yes,scrollbars=yes');
        });
      }

      // Invoice link (Rechnung)
      const invoiceValue = item.invoice;
      if (invoiceValue) {
        const invoiceLink = document.createElement('a');
        invoiceLink.href = `https://jobrouter.empira-invest.com/jobrouter/FIBU_URL.php?dokument=${invoiceValue}`;
        invoiceLink.target = '_blank';
        invoiceLink.rel = 'noopener noreferrer';
        invoiceLink.className = 'simplifyTable_invoice-link';
        invoiceLink.title = `Rechnung öffnen: ${invoiceValue}`;

        const invoiceIcon = document.createElement('i');
        invoiceIcon.className = 'fas fa-file-invoice simplifyTable_invoice-icon';
        invoiceLink.appendChild(invoiceIcon);

        actionsContainer.appendChild(invoiceLink);
      }

      // Protocol link (Protokoll)
      const protocolValue = item.protocol;
      if (protocolValue) {
        const protocolLink = document.createElement('a');
        protocolLink.href = `https://jobrouter.empira-invest.com/jobrouter/PROTOCOL_URL.php?dokument=${protocolValue}`;
        protocolLink.target = '_blank';
        protocolLink.rel = 'noopener noreferrer';
        protocolLink.className = 'simplifyTable_protocol-link';
        protocolLink.title = `Protokoll öffnen: ${protocolValue}`;

        const protocolIcon = document.createElement('i');
        protocolIcon.className = 'fas fa-file-alt simplifyTable_protocol-icon';
        protocolLink.appendChild(protocolIcon);

        actionsContainer.appendChild(protocolLink);
      }

      cell.appendChild(actionsContainer);
    } else if (column.type === 'status') {
      const statusValue = (value || '').toLowerCase();
      const runtime = item.runtime || '';
      const dueDate = item.dueDate || '';
      const statusIcon = document.createElement('i');
      statusIcon.className = 'simplifyTable_status-icon';

      if (statusValue === 'beendet' || statusValue === 'completed') {
        statusIcon.className += ' fas fa-check-circle simplifyTable_status-completed';
        const tooltip = runtime ? `Beendet - Laufzeit: ${runtime}` : 'Beendet';
        statusIcon.title = tooltip;
        statusIcon.setAttribute('aria-label', tooltip);
      } else if (statusValue === 'fällig' || statusValue === 'faellig' || statusValue === 'due') {
        statusIcon.className += ' fas fa-exclamation-circle simplifyTable_status-due';
        let tooltip = 'Fällig';
        if (dueDate) {
          // dueDate is already eskalation + 5 days from backend
          const dueDateObj = new Date(dueDate);
          const today = new Date();
          today.setHours(0, 0, 0, 0);
          dueDateObj.setHours(0, 0, 0, 0);
          // Calculate days overdue (today - dueDate)
          const diffTime = today - dueDateObj;
          const daysOverdue = Math.floor(diffTime / (1000 * 60 * 60 * 24));
          if (daysOverdue >= 0) {
            tooltip = `Fällig seit ${daysOverdue} Tagen`;
          }
        }
        statusIcon.title = tooltip;
        statusIcon.setAttribute('aria-label', tooltip);
      } else if (statusValue === 'nicht fällig' || statusValue === 'nicht faellig' || statusValue === 'not_due' || statusValue === 'not_faellig') {
        statusIcon.className += ' fas fa-clock simplifyTable_status-not-due';
        const tooltip = runtime ? `Nicht Fällig - Laufzeit: ${runtime}` : 'Nicht Fällig';
        statusIcon.title = tooltip;
        statusIcon.setAttribute('aria-label', tooltip);
      } else {
        statusIcon.className += ' fas fa-question-circle simplifyTable_status-unknown';
        statusIcon.title = value || 'Unbekannt';
        statusIcon.setAttribute('aria-label', value || 'Unbekannt');
      }

      cell.appendChild(statusIcon);
    } else if (column.type === 'currency') {
      const numValue = parseFloat(value);
      cell.textContent = value ? `€ ${numValue.toLocaleString('de-DE', { minimumFractionDigits: 2 })}` : '-';
      cell.className += ' simplifyTable_amount-cell';
      if (numValue < 0) {
        cell.className += ' simplifyTable_negative-amount';
      }
    } else if (column.type === 'date') {
      if (value) {
        const date = new Date(value);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        cell.textContent = `${day}.${month}.${year}`;
      } else {
        cell.textContent = '-';
      }
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

  const orderedColumns = getOrderedColumns();
  orderedColumns.forEach((column, index) => {
    const isActionsColumn = column.id === 'actions';
    const th = document.createElement('th');
    th.className = 'simplifyTable_table-header';
    if (!isActionsColumn) {
      th.classList.add('simplifyTable_sortable');
    }
    th.dataset.column = column.id;
    th.dataset.columnIndex = index;
    th.draggable = true;

    if (isActionsColumn) {
      th.draggable = false;
      th.style.cursor = 'default';
      th.addEventListener('click', (e) => e.stopPropagation());
      th.addEventListener('dragstart', (e) => e.preventDefault());
    }

    // Apply alignment
    if (column.align) {
      th.style.textAlign = column.align;
    }

    const headerContent = document.createElement('span');
    headerContent.textContent = column.label;
    th.appendChild(headerContent);
    if (!isActionsColumn) {
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
    }

    // Drag and drop handlers
    th.addEventListener('dragstart', (e) => {
      appState.dragState.dragging = true;
      appState.dragState.draggedColumn = index;
      th.classList.add('simplifyTable_dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/html', th.innerHTML);
    });

    th.addEventListener('dragend', (e) => {
      appState.dragState.dragging = false;
      appState.dragState.draggedColumn = null;
      appState.dragState.dragOverColumn = null;
      th.classList.remove('simplifyTable_dragging');
      document.querySelectorAll('.simplifyTable_table-header').forEach((header) => {
        header.classList.remove('simplifyTable_drag-over-left', 'simplifyTable_drag-over-right');
      });
    });

    th.addEventListener('dragover', (e) => {
      if (!appState.dragState.dragging) return;
      // Prevent dropping on the actions column (index 0)
      if (index === 0) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';

      const draggedIndex = appState.dragState.draggedColumn;
      if (draggedIndex === null || draggedIndex === index) return;

      // Determine drop position based on mouse position
      const rect = th.getBoundingClientRect();
      const midpoint = rect.left + rect.width / 2;
      const isLeftSide = e.clientX < midpoint;

      // Clear previous indicators
      document.querySelectorAll('.simplifyTable_table-header').forEach((header) => {
        header.classList.remove('simplifyTable_drag-over-left', 'simplifyTable_drag-over-right');
      });

      // Add indicator to current target
      if (isLeftSide) {
        th.classList.add('simplifyTable_drag-over-left');
      } else {
        th.classList.add('simplifyTable_drag-over-right');
      }

      appState.dragState.dragOverColumn = index;
    });

    th.addEventListener('dragleave', (e) => {
      th.classList.remove('simplifyTable_drag-over-left', 'simplifyTable_drag-over-right');
    });

    th.addEventListener('drop', (e) => {
      e.preventDefault();
      e.stopPropagation();

      const draggedIndex = appState.dragState.draggedColumn;
      const targetIndex = index;

      // Prevent dropping on or moving the actions column
      if (draggedIndex === null || draggedIndex === targetIndex || draggedIndex === 0 || targetIndex === 0) return;

      // Perform the reorder
      reorderColumn(draggedIndex, targetIndex);

      // Rebuild the entire table to reflect new column order
      const container = document.querySelector('.simplifyTable_container');
      const oldTableWrapper = container.querySelector('.simplifyTable_table-wrapper');
      const newTableWrapper = createTable();
      container.replaceChild(newTableWrapper, oldTableWrapper);
    });

    // Click handler for sorting (only trigger if not dragging)
    /*th.addEventListener('click', (e) => {
      if (!appState.dragState.dragging) {
        sortTable(column.id);
      }
    });*/
    th.addEventListener('click', (e) => {
      if (!isActionsColumn && !appState.dragState.dragging) {
        sortTable(column.id);
      }
    });

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
    table.style.display = 'none';
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

  ['kreditor', 'rolle', 'rechnungstyp', 'dokumentId', 'bearbeiter', 'rechnungsnummer'].forEach((key) => {
    if (filters[key]) params[key] = filters[key];
  });

  if (filters.gesellschaft && filters.gesellschaft.length > 0) {
    params.gesellschaft = JSON.stringify(filters.gesellschaft);
  }
  if (filters.fonds && filters.fonds.length > 0) {
    params.fonds = JSON.stringify(filters.fonds);
  }
  if (filters.schritt && filters.schritt.length > 0) {
    params.schritt = JSON.stringify(filters.schritt);
  }
  if (filters.bruttobetragFrom) params.bruttobetragFrom = filters.bruttobetragFrom;
  if (filters.bruttobetragTo) params.bruttobetragTo = filters.bruttobetragTo;
  if (filters.status && filters.status !== 'all') params.status = filters.status;
  if (filters.laufzeit && filters.laufzeit !== 'all') params.laufzeit = filters.laufzeit;
  if (filters.coor && filters.coor !== 'all') params.coor = filters.coor;
  if (filters.weiterbelasten && filters.weiterbelasten !== 'all') params.weiterbelasten = filters.weiterbelasten;
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
      // Save preferences after successful data fetch (but not on initial load)
      if (!appState.isInitialLoad) {
        savePreferencesToDatabase();
      }
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

function applyFilterPreset(preset) {
  // Reset all filters first
  appState.filters = {
    schritt: [],
    kreditor: '',
    rechnungsdatumFrom: '',
    rechnungsdatumTo: '',
    status: 'all',
    weiterbelasten: 'all',
    gesellschaft: [],
    rolle: '',
    rechnungstyp: '',
    bruttobetragFrom: '',
    bruttobetragTo: '',
    fonds: [],
    dokumentId: '',
    bearbeiter: '',
    rechnungsnummer: '',
    laufzeit: 'all',
    coor: 'all',
  };

  // Apply preset filters
  Object.keys(preset.filters).forEach((key) => {
    appState.filters[key] = preset.filters[key];
  });

  // Apply preset sort if defined
  if (preset.sort) {
    appState.sortColumn = preset.sort.column;
    appState.sortDirection = preset.sort.direction;
  }

  // Update UI to reflect filter values
  updateFilterUI();

  // Fetch data with new filters
  appState.currentPage = 1;
  fetchData(1);
}

function updateFilterUI() {
  // Update dropdown selects
  const statusSelect = document.getElementById('simplifyTable_status-filter');
  if (statusSelect) statusSelect.value = appState.filters.status || 'all';

  // Schritt is now multiselect, render tags
  renderTags('schritt');

  const weiterbelastenSelect = document.getElementById('simplifyTable_weiterbelasten-filter');
  if (weiterbelastenSelect) weiterbelastenSelect.value = appState.filters.weiterbelasten || 'all';

  const laufzeitSelect = document.getElementById('simplifyTable_laufzeit-filter');
  if (laufzeitSelect) laufzeitSelect.value = appState.filters.laufzeit || 'all';

  const coorSelect = document.getElementById('simplifyTable_coor-filter');
  if (coorSelect) coorSelect.value = appState.filters.coor || 'all';

  // Update text inputs
  const textFields = ['kreditor', 'rolle', 'rechnungstyp', 'dokumentId', 'bearbeiter', 'rechnungsnummer'];
  textFields.forEach((field) => {
    const input = document.getElementById(`simplifyTable_${field}-filter`);
    if (input) input.value = appState.filters[field] || '';
  });

  // Update date range inputs
  const dateFrom = document.getElementById('simplifyTable_rechnungsdatum-from-filter');
  if (dateFrom) dateFrom.value = appState.filters.rechnungsdatumFrom || '';

  const dateTo = document.getElementById('simplifyTable_rechnungsdatum-to-filter');
  if (dateTo) dateTo.value = appState.filters.rechnungsdatumTo || '';

  // Update bruttobetrag range inputs
  const bruttobetragFrom = document.getElementById('simplifyTable_bruttobetrag-from-filter');
  if (bruttobetragFrom) bruttobetragFrom.value = appState.filters.bruttobetragFrom || '';

  const bruttobetragTo = document.getElementById('simplifyTable_bruttobetrag-to-filter');
  if (bruttobetragTo) bruttobetragTo.value = appState.filters.bruttobetragTo || '';

  // Update autocomplete tags
  renderTags('gesellschaft');
  renderTags('fonds');
}

function resetFilters() {
  appState.filters = {
    schritt: [],
    kreditor: '',
    rechnungsdatumFrom: '',
    rechnungsdatumTo: '',
    status: 'all',
    weiterbelasten: 'all',
    gesellschaft: [],
    rolle: '',
    rechnungstyp: '',
    bruttobetragFrom: '',
    bruttobetragTo: '',
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
  renderTags('schritt');

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
    const parsedOptions = JSON.parse(inputElement.dataset.options);
    // Keep both id and label for proper mapping
    allOptions = parsedOptions.map((opt) => {
      if (typeof opt === 'object' && opt !== null) {
        return { id: String(opt.id), label: opt.label || String(opt.id) };
      }
      return { id: String(opt), label: String(opt) };
    });
  } else {
    const uniqueValues = [...new Set(appState.data.map((item) => item[field]))].filter((v) => v);
    allOptions = uniqueValues.map((v) => ({ id: String(v), label: String(v) }));
  }

  // Filter by label (what user types/sees)
  const filtered = inputValue ? allOptions.filter((opt) => opt.label.toLowerCase().includes(inputValue)) : allOptions;

  // Ensure selectedValues is always an array (contains IDs)
  let selectedIds = appState.filters[field] || [];
  if (!Array.isArray(selectedIds)) {
    selectedIds = selectedIds ? [selectedIds] : [];
  }
  // Filter out already selected options by ID
  const availableOptions = filtered.filter((opt) => !selectedIds.includes(String(opt.id)));

  if (availableOptions.length > 0) {
    autocompleteList.innerHTML = '';
    availableOptions.forEach((opt) => {
      const item = document.createElement('div');
      item.className = 'simplifyTable_autocomplete-item';
      item.textContent = opt.label; // Display label
      item.onclick = () => {
        addTag(field, String(opt.id)); // Store ID
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
  // Ensure the filter field is an array
  if (!Array.isArray(appState.filters[field])) {
    appState.filters[field] = appState.filters[field] ? [appState.filters[field]] : [];
  }
  if (!appState.filters[field].includes(value)) {
    appState.filters[field].push(value);
    renderTags(field);
  }
}

function removeTag(field, index) {
  // Ensure the filter field is an array
  if (!Array.isArray(appState.filters[field])) {
    appState.filters[field] = appState.filters[field] ? [appState.filters[field]] : [];
  }
  appState.filters[field].splice(index, 1);
  renderTags(field);
}

function renderTags(field) {
  const container = document.getElementById(`simplifyTable_${field}-filter-container`);
  const input = document.getElementById(`simplifyTable_${field}-filter`);
  if (!container || !input) return;

  container.querySelectorAll('.simplifyTable_tag').forEach((tag) => tag.remove());

  // Ensure the filter field is an array
  let values = appState.filters[field] || [];
  if (!Array.isArray(values)) {
    values = values ? [values] : [];
    appState.filters[field] = values;
  }

  // Get options to look up labels from IDs
  let optionsMap = {};
  if (input.dataset.options) {
    try {
      const parsedOptions = JSON.parse(input.dataset.options);
      parsedOptions.forEach((opt) => {
        if (typeof opt === 'object' && opt !== null) {
          optionsMap[String(opt.id)] = opt.label || String(opt.id);
        } else {
          optionsMap[String(opt)] = String(opt);
        }
      });
    } catch (e) {
      // Ignore parse errors
    }
  }

  values.forEach((value, index) => {
    const tag = document.createElement('span');
    tag.className = 'simplifyTable_tag';

    // Look up the label from the ID, fallback to value itself
    const displayLabel = optionsMap[String(value)] || value;
    tag.title = displayLabel;

    const textSpan = document.createElement('span');
    textSpan.textContent = displayLabel;

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
  const table = tableWrapper.querySelector('.simplifyTable_data-table');
  let noResults = tableWrapper.querySelector('.simplifyTable_no-results');

  if (appState.filteredData.length === 0) {
    if (table) table.style.display = 'none';
    if (!noResults) {
      noResults = document.createElement('div');
      noResults.className = 'simplifyTable_no-results';
      noResults.innerHTML = '<i class="fas fa-clipboard-list"></i><p>Es wurden keine Einträge gefunden</p>';
      tableWrapper.appendChild(noResults);
    } else {
      noResults.style.display = '';
    }
  } else {
    if (table) table.style.display = '';
    if (noResults) {
      noResults.remove();
    }
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
  ['kreditor', 'rolle', 'rechnungstyp', 'dokumentId', 'bearbeiter', 'rechnungsnummer'].forEach((key) => {
    const input = document.getElementById(`simplifyTable_${key}-filter`);
    if (input) {
      input.addEventListener('input', (e) => {
        appState.filters[key] = e.target.value;
      });
    }
  });

  ['gesellschaft', 'fonds', 'schritt'].forEach((key) => {
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

  ['status', 'laufzeit', 'coor', 'weiterbelasten'].forEach((key) => {
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

  const bruttobetragFrom = document.getElementById('simplifyTable_bruttobetrag-from-filter');
  if (bruttobetragFrom) {
    bruttobetragFrom.addEventListener('input', (e) => {
      appState.filters.bruttobetragFrom = e.target.value;
    });
  }

  const bruttobetragTo = document.getElementById('simplifyTable_bruttobetrag-to-filter');
  if (bruttobetragTo) {
    bruttobetragTo.addEventListener('input', (e) => {
      appState.filters.bruttobetragTo = e.target.value;
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

  // Load saved column order
  loadColumnOrder();

  // Load other user preferences (filters, sort, pagination)
  loadUserPreferences();

  // Initialize application state with data from PHP
  appState.data = [];
  appState.filteredData = [];
  appState.totalItems = 0;

  // Render the UI
  render();
  attachEventListeners();

  // Update filter UI to reflect loaded preferences
  updateFilterUI();

  // Set initial zoom
  setZoom(appState.zoomLevel);

  // Fetch data with loaded preferences
  fetchData(appState.currentPage || 1);

  appState.isInitialLoad = false;
}

init();
$j('#simplifyTable_app').parent().toggleClass('simplifyTable_background', true);

(function addWidgetBackground() {
  const appEl = document.getElementById('simplifyTable_app');
  if (!appEl) return;

  const parent = $j(appEl).parent();
  if (parent.length && !parent.find('.simplifyTable_zoom-controls').length) {
    parent.append(createZoomControls());
  }

  // Try to add the background directly to the dashboard label element
  const container = appEl.closest('.grid-stack-item-content');
  const labelEl = container ? container.querySelector('.jr-dashboard-label') : null;

  if (labelEl) {
    labelEl.classList.add('simplifyTable_background-label');
  } else if (container) {
    container.classList.add('simplifyTable_background-label');
  }
})();
