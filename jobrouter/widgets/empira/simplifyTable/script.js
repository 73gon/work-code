// ============================================
// CONFIGURATION - Data from PHP
// ============================================

// ❌ REMOVED - Now comes from PHP via template.hbs
// These variables are now loaded from hidden divs in the template
// const COLUMNS = [...];
// const DROPDOWN_OPTIONS = {...};
// const SAMPLE_DATA = [...];

// Read data from PHP (injected via template.hbs)
let COLUMNS = [];
let DROPDOWN_OPTIONS = {};
let INITIAL_DATA = [];

// Load configuration from hidden divs
function loadConfigFromPHP() {
  try {
    const columnsDiv = document.getElementById('simplifyTable_columns');
    const dropdownDiv = document.getElementById('simplifyTable_dropdownOptions');
    const dataDiv = document.getElementById('simplifyTable_tableData');

    if (columnsDiv && columnsDiv.textContent) {
      COLUMNS = JSON.parse(columnsDiv.textContent);
    }

    if (dropdownDiv && dropdownDiv.textContent) {
      DROPDOWN_OPTIONS = JSON.parse(dropdownDiv.textContent);
    }

    if (dataDiv && dataDiv.textContent) {
      INITIAL_DATA = JSON.parse(dataDiv.textContent);
    }

    // If no data from PHP, generate sample data for testing
    if (INITIAL_DATA.length === 0) {
      console.warn('No data from PHP, generating sample data for testing');
      INITIAL_DATA = generateSampleData();
    }
  } catch (error) {
    console.error('Error loading configuration from PHP:', error);
    // Fallback: use sample data
    INITIAL_DATA = generateSampleData();
  }
}

// ============================================
// APPLICATION STATE
// ============================================

let appState = {
  data: [],
  filteredData: [],
  filters: {
    vorgang: '',
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
  itemsPerPage: 50,
  sortColumn: null,
  sortDirection: 'asc',
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

// ❌ REMOVED - Sample data generation (only used as fallback now)
// This function is kept only for testing when PHP doesn't provide data
function generateSampleData() {
  // Sample base data
  const SAMPLE_DATA = [
    {
      id: 1,
      status: 'Aktiv',
      vorgang: 'RE-2024-001',
      eingangsdatum: '2024-01-15',
      schritt: 'Schritt 1',
      startdatum: '2024-01-16',
      rolle: 'Prüfer',
      bearbeiter: 'Max Mustermann',
      dokumentId: 'DOK-001',
      gesellschaft: 'Firma A GmbH',
      kreditor: 'Lieferant XYZ',
      rechnungstyp: 'Standard',
      rechnungsnummer: 'R-2024-1001',
      rechnungsdatum: '2024-01-10',
      bruttobetrag: -15000.5,
      faelligkeit: '2024-02-10',
      auftragsnummer: 'AUF-2024-100',
      zahlbetrag: 15000.5,
      zahldatum: '2024-02-08',
      dauer: '5 Tage',
      rechnung: 'Rechnung-001.pdf',
      protokoll: 'Protokoll-001.pdf',
      weiterbelasten: 'Ja',
      fonds: 'Fonds A',
      laufzeit: '0-5 Tage',
      coor: 'Ja',
    },
    {
      id: 2,
      status: 'Ausstehend',
      vorgang: 'RE-2024-002',
      eingangsdatum: '2024-01-20',
      schritt: 'Schritt 2',
      startdatum: '2024-01-21',
      rolle: 'Genehmiger',
      bearbeiter: 'Anna Schmidt',
      dokumentId: 'DOK-002',
      gesellschaft: 'Firma B AG',
      kreditor: 'Lieferant ABC',
      rechnungstyp: 'Eilig',
      rechnungsnummer: 'R-2024-1002',
      rechnungsdatum: '2024-01-18',
      bruttobetrag: 8750.0,
      faelligkeit: '2024-02-18',
      auftragsnummer: 'AUF-2024-101',
      zahlbetrag: 8750.0,
      zahldatum: '',
      dauer: '12 Tage',
      rechnung: 'Rechnung-002.pdf',
      protokoll: 'Protokoll-002.pdf',
      weiterbelasten: 'Nein',
      fonds: 'Fonds B',
      laufzeit: '11-20 Tage',
      coor: 'Ausstehend',
    },
  ];

  const additionalData = [];
  const statuses = DROPDOWN_OPTIONS.status || ['Aktiv', 'Inaktiv', 'Ausstehend', 'Abgeschlossen'];
  const schritte = DROPDOWN_OPTIONS.schritt || ['Schritt 1', 'Schritt 2', 'Schritt 4', 'Schritt 5'];
  const laufzeiten = DROPDOWN_OPTIONS.laufzeit || ['0-5 Tage', '6-10 Tage', '11-20 Tage', '21+ Tage'];
  const coors = DROPDOWN_OPTIONS.coor || ['Ja', 'Nein', 'Ausstehend'];
  const rollen = ['Prüfer', 'Genehmiger', 'Sachbearbeiter', 'Controller'];
  const bearbeiter = ['Max Mustermann', 'Anna Schmidt', 'Peter Weber', 'Sarah Fischer', 'Thomas Müller', 'Laura Wagner'];
  const gesellschaften = DROPDOWN_OPTIONS.gesellschaft || ['Firma A GmbH', 'Firma B AG', 'Firma C KG', 'Firma D SE'];
  const kreditoren = ['Lieferant XYZ', 'Lieferant ABC', 'Lieferant DEF', 'Lieferant GHI'];
  const rechnungstypen = ['Standard', 'Eilig', 'Kredit', 'Anzahlung'];
  const fonds = DROPDOWN_OPTIONS.fonds || ['Fonds A', 'Fonds B', 'Fonds C', 'Fonds D'];
  const weiterbelastenOptions = ['Ja', 'Nein'];

  for (let i = 0; i < 48; i++) {
    const id = i + 3;
    const eingangsdatumDate = new Date(new Date(2024, 0, 1).getTime() + Math.random() * (new Date().getTime() - new Date(2024, 0, 1).getTime()));
    const startdatumDate = new Date(eingangsdatumDate);
    startdatumDate.setDate(startdatumDate.getDate() + 1);
    const rechnungsdatumDate = new Date(eingangsdatumDate);
    rechnungsdatumDate.setDate(rechnungsdatumDate.getDate() - Math.floor(Math.random() * 10));
    const faelligkeitDate = new Date(eingangsdatumDate);
    faelligkeitDate.setDate(faelligkeitDate.getDate() + 30);
    const zahldatumDate = new Date(eingangsdatumDate);
    zahldatumDate.setDate(zahldatumDate.getDate() + 35);

    additionalData.push({
      id: id,
      status: statuses[Math.floor(Math.random() * statuses.length)],
      vorgang: `RE-2024-${String(id).padStart(3, '0')}`,
      eingangsdatum: formatDate(eingangsdatumDate),
      schritt: schritte[Math.floor(Math.random() * schritte.length)],
      startdatum: formatDate(startdatumDate),
      rolle: rollen[Math.floor(Math.random() * rollen.length)],
      bearbeiter: bearbeiter[Math.floor(Math.random() * bearbeiter.length)],
      dokumentId: `DOK-${String(id).padStart(3, '0')}`,
      gesellschaft: gesellschaften[Math.floor(Math.random() * gesellschaften.length)],
      kreditor: kreditoren[Math.floor(Math.random() * kreditoren.length)],
      rechnungstyp: rechnungstypen[Math.floor(Math.random() * rechnungstypen.length)],
      rechnungsnummer: `R-2024-${1000 + id}`,
      rechnungsdatum: formatDate(rechnungsdatumDate),
      bruttobetrag: (Math.random() > 0.8 ? -1 : 1) * (Math.random() * 50000).toFixed(2),
      faelligkeit: formatDate(faelligkeitDate),
      auftragsnummer: `AUF-2024-${100 + id}`,
      zahlbetrag: (Math.random() > 0.8 ? -1 : 1) * (Math.random() * 50000).toFixed(2),
      zahldatum: Math.random() > 0.3 ? formatDate(zahldatumDate) : '',
      dauer: `${Math.floor(Math.random() * 30)} Tage`,
      rechnung: `Rechnung-${String(id).padStart(3, '0')}.pdf`,
      protokoll: `Protokoll-${String(id).padStart(3, '0')}.pdf`,
      weiterbelasten: weiterbelastenOptions[Math.floor(Math.random() * weiterbelastenOptions.length)],
      fonds: fonds[Math.floor(Math.random() * fonds.length)],
      laufzeit: laufzeiten[Math.floor(Math.random() * laufzeiten.length)],
      coor: coors[Math.floor(Math.random() * coors.length)],
    });
  }

  return [...SAMPLE_DATA, ...additionalData];
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
    allOption.textContent = `Alle ${label}`;
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
    { label: 'Vorgang', type: 'text', id: 'simplifyTable_vorgang-filter', key: 'vorgang' },
    { label: 'Status', type: 'dropdown', id: 'simplifyTable_status-filter', key: 'status', options: DROPDOWN_OPTIONS.status },
    { label: 'Schritt', type: 'dropdown', id: 'simplifyTable_schritt-filter', key: 'schritt', options: DROPDOWN_OPTIONS.schritt },
    { label: 'DokumentId', type: 'text', id: 'simplifyTable_dokumentId-filter', key: 'dokumentId' },

    // People & Roles
    { label: 'Bearbeiter', type: 'text', id: 'simplifyTable_bearbeiter-filter', key: 'bearbeiter' },
    { label: 'Rolle', type: 'text', id: 'simplifyTable_rolle-filter', key: 'rolle' },

    // Company & Financial Structure
    { label: 'Gesellschaft', type: 'autocomplete', id: 'simplifyTable_gesellschaft-filter', key: 'gesellschaft', options: DROPDOWN_OPTIONS.gesellschaft },
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

  const start = (appState.currentPage - 1) * appState.itemsPerPage;
  const end = start + appState.itemsPerPage;
  const paginatedData = appState.filteredData.slice(start, end);

  paginatedData.forEach((item) => {
    const row = createTableRow(item);
    tbody.appendChild(row);
  });

  table.appendChild(tbody);
  tableWrapper.appendChild(table);

  if (appState.filteredData.length === 0) {
    const noResults = document.createElement('div');
    noResults.className = 'simplifyTable_no-results';
    noResults.innerHTML = '<i class="fas fa-search"></i><p>Keine Ergebnisse gefunden</p>';
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

  const totalPages = Math.ceil(appState.filteredData.length / appState.itemsPerPage);
  const start = (appState.currentPage - 1) * appState.itemsPerPage + 1;
  const end = Math.min(start + appState.itemsPerPage - 1, appState.filteredData.length);

  const paginationInfo = document.createElement('div');
  paginationInfo.className = 'simplifyTable_pagination-info';
  paginationInfo.textContent = `${start}-${end} von ${appState.filteredData.length} Einträgen`;

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

  appState.filteredData.sort((a, b) => {
    let aVal = a[columnId];
    let bVal = b[columnId];

    if (aVal === null || aVal === undefined) aVal = '';
    if (bVal === null || bVal === undefined) bVal = '';

    const column = COLUMNS.find((col) => col.id === columnId);
    if (column && (column.type === 'number' || column.type === 'currency')) {
      aVal = parseFloat(aVal) || 0;
      bVal = parseFloat(bVal) || 0;
    } else if (column && column.type === 'date') {
      aVal = new Date(aVal).getTime() || 0;
      bVal = new Date(bVal).getTime() || 0;
    } else {
      aVal = aVal.toString().toLowerCase();
      bVal = bVal.toString().toLowerCase();
    }

    if (aVal < bVal) return appState.sortDirection === 'asc' ? -1 : 1;
    if (aVal > bVal) return appState.sortDirection === 'asc' ? 1 : -1;
    return 0;
  });

  appState.currentPage = 1;
  updateTable();
}

function changePage(page) {
  appState.currentPage = page;
  updateTable();
}

function changeItemsPerPage(value) {
  appState.itemsPerPage = parseInt(value);
  appState.currentPage = 1;
  updateTable();
}

function applyFilters() {
  appState.filteredData = appState.data.filter((item) => {
    if (appState.filters.vorgang && !item.vorgang.toLowerCase().includes(appState.filters.vorgang)) return false;
    if (appState.filters.kreditor && !item.kreditor.toLowerCase().includes(appState.filters.kreditor)) return false;
    if (appState.filters.weiterbelasten && !item.weiterbelasten.toLowerCase().includes(appState.filters.weiterbelasten)) return false;
    if (appState.filters.rolle && !item.rolle.toLowerCase().includes(appState.filters.rolle)) return false;
    if (appState.filters.rechnungstyp && !item.rechnungstyp.toLowerCase().includes(appState.filters.rechnungstyp)) return false;
    if (appState.filters.bruttobetrag && !item.bruttobetrag.toString().includes(appState.filters.bruttobetrag)) return false;
    if (appState.filters.dokumentId && !item.dokumentId.toLowerCase().includes(appState.filters.dokumentId)) return false;
    if (appState.filters.bearbeiter && !item.bearbeiter.toLowerCase().includes(appState.filters.bearbeiter)) return false;
    if (appState.filters.rechnungsnummer && !item.rechnungsnummer.toLowerCase().includes(appState.filters.rechnungsnummer)) return false;

    if (appState.filters.gesellschaft.length > 0) {
      const itemGesellschaft = item.gesellschaft.toLowerCase();
      const hasMatch = appState.filters.gesellschaft.some((g) => itemGesellschaft.includes(g.toLowerCase()));
      if (!hasMatch) return false;
    }
    if (appState.filters.fonds.length > 0) {
      const itemFonds = item.fonds.toLowerCase();
      const hasMatch = appState.filters.fonds.some((f) => itemFonds.includes(f.toLowerCase()));
      if (!hasMatch) return false;
    }

    if (appState.filters.schritt !== 'all' && item.schritt !== appState.filters.schritt) return false;
    if (appState.filters.status !== 'all' && item.status !== appState.filters.status) return false;
    if (appState.filters.laufzeit !== 'all' && item.laufzeit !== appState.filters.laufzeit) return false;
    if (appState.filters.coor !== 'all' && item.coor !== appState.filters.coor) return false;

    if (appState.filters.rechnungsdatumFrom && item.rechnungsdatum < appState.filters.rechnungsdatumFrom) return false;
    if (appState.filters.rechnungsdatumTo && item.rechnungsdatum > appState.filters.rechnungsdatumTo) return false;

    return true;
  });

  appState.currentPage = 1;
  updateTable();
}

function resetFilters() {
  appState.filters = {
    vorgang: '',
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

  const start = (appState.currentPage - 1) * appState.itemsPerPage;
  const end = start + appState.itemsPerPage;
  const paginatedData = appState.filteredData.slice(start, end);

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
      noResults.innerHTML = '<i class="fas fa-search"></i><p>Keine Ergebnisse gefunden</p>';
      tableWrapper.appendChild(noResults);
    }
  } else if (noResults) {
    noResults.remove();
  }
}

function updatePagination() {
  const totalPages = Math.ceil(appState.filteredData.length / appState.itemsPerPage);
  const start = (appState.currentPage - 1) * appState.itemsPerPage + 1;
  const end = Math.min(start + appState.itemsPerPage - 1, appState.filteredData.length);

  const paginationInfo = document.querySelector('.simplifyTable_pagination-info');
  if (paginationInfo) {
    paginationInfo.textContent = `${start}-${end} von ${appState.filteredData.length} Einträgen`;
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
  ['vorgang', 'kreditor', 'weiterbelasten', 'rolle', 'rechnungstyp', 'bruttobetrag', 'dokumentId', 'bearbeiter', 'rechnungsnummer'].forEach((key) => {
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

  // Initialize application state with data from PHP (or fallback sample data)
  appState.data = INITIAL_DATA;
  appState.filteredData = [...appState.data];

  // Render the UI
  render();
  attachEventListeners();
}

document.addEventListener('DOMContentLoaded', init);
