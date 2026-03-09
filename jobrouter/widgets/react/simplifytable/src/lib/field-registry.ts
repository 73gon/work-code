import type { Column, DropdownOption, FilterConfig } from './types';

// ============================================
// FIELD REGISTRY — Single source of truth
// ============================================
//
// To add a new column:
//   1. Add an entry to FIELD_REGISTRY below
//   2. In query.php: add 1 line to getFieldMap()
//   3. In init.php: add 1 line to getColumns()
//
// To also add a filter for that column:
//   1. Include a `filter` property in the registry entry
//   2. In query.php: add 1 line to getFilterDefs() or getListFilterDefs()
//   3. If dropdown with DB-sourced options: add to init.php fetchDropdownOptionsFromDB()
//
// That's it — types, DEFAULT_FILTERS, DEFAULT_COLUMNS, buildFilterConfig,
// and queryServer URL params are all auto-derived from this registry.

export type ColumnType = 'actions' | 'status' | 'date' | 'text' | 'currency' | 'number';
export type FilterType = 'text' | 'dropdown' | 'autocomplete' | 'daterange' | 'numberrange';

export interface FieldFilter {
  /** Filter ID shown in the UI (defaults to key) */
  id?: string;
  /** Filter label shown in the UI (defaults to field label) */
  label?: string;
  /** Filter input type */
  type: FilterType;
  /** Key used in state.filters (may differ from column id) */
  key: string;
  /** Default value when filters are reset */
  defaultValue: string | string[];
  /** Key in dropdownOptions for dropdown/autocomplete filters */
  optionsKey?: string;
  /** For daterange/numberrange: the two state keys */
  rangeIds?: { fromId: string; toId: string };
}

export interface FieldEntry {
  /** Column id — used as key in row data, column order, visibility */
  id: string;
  /** Column header label */
  label: string;
  /** Render type (controls CellContent rendering) */
  type: ColumnType;
  /** Text alignment */
  align: 'left' | 'center' | 'right';
  /** Optional filter associated with this column */
  filter?: FieldFilter;
}

// ============================================
// THE REGISTRY
// ============================================

export const FIELD_REGISTRY: FieldEntry[] = [
  // -- Actions & Status (special render types) --
  { id: 'actions', label: '', type: 'actions', align: 'center' },
  {
    id: 'status',
    label: 'Status',
    type: 'status',
    align: 'center',
    filter: { type: 'dropdown', key: 'status', defaultValue: 'all', optionsKey: 'status' },
  },

  // -- Text columns with filters --
  { id: 'incident', label: 'Vorgangsnummer', type: 'text', align: 'left', filter: { type: 'text', key: 'incident', defaultValue: '' } },
  { id: 'entryDate', label: 'Eingangsdatum', type: 'date', align: 'left' },
  {
    id: 'stepLabel',
    label: 'Schritt',
    type: 'text',
    align: 'left',
    filter: { id: 'schritt', label: 'Schritt', type: 'autocomplete', key: 'schritt', defaultValue: [], optionsKey: 'schritt' },
  },
  { id: 'startDate', label: 'Startdatum (Schritt)', type: 'date', align: 'left' },
  {
    id: 'jobFunction',
    label: 'Rolle',
    type: 'text',
    align: 'left',
    filter: { id: 'rolle', label: 'Rolle', type: 'text', key: 'rolle', defaultValue: '' },
  },
  {
    id: 'fullName',
    label: 'Bearbeiter',
    type: 'text',
    align: 'left',
    filter: { id: 'bearbeiter', label: 'Bearbeiter', type: 'text', key: 'bearbeiter', defaultValue: '' },
  },
  {
    id: 'documentId',
    label: 'DokumentId',
    type: 'text',
    align: 'left',
    filter: { id: 'dokumentId', label: 'DokumentId', type: 'text', key: 'dokumentId', defaultValue: '' },
  },
  {
    id: 'companyName',
    label: 'Gesellschaft',
    type: 'text',
    align: 'left',
    filter: { id: 'gesellschaft', label: 'Gesellschaft', type: 'autocomplete', key: 'gesellschaft', defaultValue: [], optionsKey: 'gesellschaft' },
  },
  {
    id: 'fund',
    label: 'Fonds',
    type: 'text',
    align: 'left',
    filter: { id: 'fonds', label: 'Fonds', type: 'autocomplete', key: 'fonds', defaultValue: [], optionsKey: 'fonds' },
  },
  {
    id: 'creditorName',
    label: 'Kreditor',
    type: 'text',
    align: 'left',
    filter: { id: 'kreditor', label: 'Kreditor', type: 'text', key: 'kreditor', defaultValue: '' },
  },
  {
    id: 'invoiceType',
    label: 'Rechnungstyp',
    type: 'text',
    align: 'left',
    filter: { id: 'rechnungstyp', label: 'Rechnungstyp', type: 'text', key: 'rechnungstyp', defaultValue: '' },
  },
  {
    id: 'invoiceNumber',
    label: 'Rechnungsnummer',
    type: 'text',
    align: 'left',
    filter: { id: 'rechnungsnummer', label: 'Rechnungsnummer', type: 'text', key: 'rechnungsnummer', defaultValue: '' },
  },
  {
    id: 'invoiceDate',
    label: 'Rechnungsdatum',
    type: 'date',
    align: 'left',
    filter: {
      id: 'rechnungsdatum',
      label: 'Rechnungsdatum',
      type: 'daterange',
      key: 'rechnungsdatum',
      defaultValue: '',
      rangeIds: { fromId: 'rechnungsdatumFrom', toId: 'rechnungsdatumTo' },
    },
  },
  {
    id: 'grossAmount',
    label: 'Bruttobetrag',
    type: 'currency',
    align: 'left',
    filter: {
      id: 'bruttobetrag',
      label: 'Bruttobetrag',
      type: 'numberrange',
      key: 'bruttobetrag',
      defaultValue: '',
      rangeIds: { fromId: 'bruttobetragFrom', toId: 'bruttobetragTo' },
    },
  },
  { id: 'dueDate', label: 'Fälligkeit', type: 'date', align: 'left' },
  { id: 'orderId', label: 'Auftragsnummer', type: 'text', align: 'left' },
  { id: 'paymentAmount', label: 'Zahlbetrag', type: 'currency', align: 'left' },
  { id: 'paymentDate', label: 'Zahldatum', type: 'date', align: 'left' },
  {
    id: 'chargeable',
    label: 'Weiterbelasten',
    type: 'text',
    align: 'center',
    filter: {
      id: 'weiterbelasten',
      label: 'Weiterbelasten',
      type: 'dropdown',
      key: 'weiterbelasten',
      defaultValue: 'all',
      optionsKey: 'weiterbelasten',
    },
  },

  // -- Additional filters not tied to a visible column --
  // (laufzeit and coor are standalone filters — their columns are hidden or virtual)

  // -- New field: Kostenübernahme --
  {
    id: 'kostenuebernahme',
    label: 'Kostenübernahme',
    type: 'text',
    align: 'center',
    filter: { type: 'dropdown', key: 'kostenuebernahme', defaultValue: 'all', optionsKey: 'kostenuebernahme' },
  },
];

// Standalone filters that don't correspond to a column in the registry.
// These are rendered in the filter bar but have no matching column entry.
export const STANDALONE_FILTERS: FieldFilter[] = [
  { id: 'laufzeit', label: 'Laufzeit', type: 'dropdown', key: 'laufzeit', defaultValue: 'all', optionsKey: 'laufzeit' },
  { id: 'coor', label: 'Coor', type: 'dropdown', key: 'coor', defaultValue: 'all', optionsKey: 'coor' },
];

// ============================================
// DERIVED HELPERS
// ============================================

/** Derive DEFAULT_COLUMNS from the registry */
export function getDefaultColumns(): Column[] {
  return FIELD_REGISTRY.map((f) => ({
    id: f.id,
    label: f.label,
    type: f.type,
    align: f.align,
    visible: true,
  }));
}

/** Derive DEFAULT_FILTERS from the registry (all filter keys + their defaults) */
export function getDefaultFilters(): Record<string, string | string[]> {
  const filters: Record<string, string | string[]> = {};

  const allFilters = [...FIELD_REGISTRY.filter((f) => f.filter).map((f) => f.filter!), ...STANDALONE_FILTERS];

  for (const filter of allFilters) {
    if (filter.rangeIds) {
      filters[filter.rangeIds.fromId] = '';
      filters[filter.rangeIds.toId] = '';
    } else {
      filters[filter.key] = filter.defaultValue;
    }
  }

  return filters;
}

/** Build FilterConfig[] from registry + dropdown options (replaces old buildFilterConfig) */
export function buildFilterConfigFromRegistry(dropdownOptions: Record<string, DropdownOption[]>): FilterConfig[] {
  const allFilters = [
    ...FIELD_REGISTRY.filter((f) => f.filter).map((f) => ({
      filter: f.filter!,
      fieldLabel: f.label,
    })),
    ...STANDALONE_FILTERS.map((f) => ({ filter: f, fieldLabel: f.label ?? '' })),
  ];

  return allFilters.map(({ filter, fieldLabel }) => {
    const config: FilterConfig = {
      id: filter.id ?? filter.key,
      label: filter.label ?? fieldLabel,
      type: filter.type,
      filterKey: filter.key,
      visible: true,
    };

    if (filter.type === 'dropdown' || filter.type === 'autocomplete') {
      config.options = dropdownOptions[filter.optionsKey ?? filter.key] ?? [];
    } else if (filter.rangeIds) {
      config.options = filter.rangeIds;
    }

    return config;
  });
}

/** Serialize all filter values to URL search params (used by queryServer) */
export function serializeFiltersToParams(url: URL, filters: Record<string, string | string[]>): void {
  const allFilters = [...FIELD_REGISTRY.filter((f) => f.filter).map((f) => f.filter!), ...STANDALONE_FILTERS];

  for (const filter of allFilters) {
    if (filter.rangeIds) {
      const fromVal = filters[filter.rangeIds.fromId];
      const toVal = filters[filter.rangeIds.toId];
      if (fromVal) url.searchParams.set(filter.rangeIds.fromId, fromVal as string);
      if (toVal) url.searchParams.set(filter.rangeIds.toId, toVal as string);
    } else {
      const val = filters[filter.key];
      if (Array.isArray(val)) {
        if (val.length > 0) url.searchParams.set(filter.key, JSON.stringify(val));
      } else if (val) {
        url.searchParams.set(filter.key, val);
      }
    }
  }
}
