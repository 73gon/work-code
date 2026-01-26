// ============================================
// TYPE DEFINITIONS
// ============================================

export interface Column {
  id: string;
  label: string;
  type: 'actions' | 'status' | 'date' | 'text' | 'currency' | 'number';
  align: 'left' | 'center' | 'right';
  visible?: boolean;
}

export interface DropdownOption {
  id: string;
  label: string;
}

export interface DropdownOptions {
  status: DropdownOption[];
  schritt: DropdownOption[];
  laufzeit: DropdownOption[];
  coor: DropdownOption[];
  weiterbelasten: DropdownOption[];
  gesellschaft: DropdownOption[];
  fonds: DropdownOption[];
}

export interface Filters {
  schritt: string[];
  kreditor: string;
  rechnungsdatumFrom: string;
  rechnungsdatumTo: string;
  status: string;
  weiterbelasten: string;
  gesellschaft: string[];
  rolle: string;
  rechnungstyp: string;
  bruttobetragFrom: string;
  bruttobetragTo: string;
  fonds: string[];
  dokumentId: string;
  bearbeiter: string;
  rechnungsnummer: string;
  laufzeit: string;
  coor: string;
}

export interface FilterPreset {
  id: string;
  label: string;
  filters: Partial<Filters>;
  sort?: {
    column: string;
    direction: 'asc' | 'desc';
  };
  visible?: boolean;
  isCustom?: boolean;
}

export interface FilterConfig {
  id: string;
  label: string;
  type: 'dropdown' | 'text' | 'autocomplete' | 'daterange' | 'numberrange';
  filterKey: string;
  options?: DropdownOption[] | { fromId: string; toId: string };
  visible?: boolean;
}

export interface TableRow {
  id: string;
  status: string;
  incident?: string;
  entryDate?: string;
  stepLabel?: string;
  startDate?: string;
  jobFunction?: string;
  fullName?: string;
  documentId?: string;
  companyName?: string;
  fund?: string;
  creditorName?: string;
  invoiceType?: string;
  invoiceNumber?: string;
  invoiceDate?: string;
  grossAmount?: number | string;
  dueDate?: string;
  orderId?: string;
  paymentAmount?: number | string;
  paymentDate?: string;
  chargeable?: string;
  runtime?: string;
  historyLink?: string;
  invoice?: string;
  protocol?: string;
  [key: string]: unknown;
}

export interface UserPreferences {
  filter?: Filters;
  column_order?: string[];
  sort_column?: string;
  sort_direction?: 'asc' | 'desc';
  current_page?: number;
  entries_per_page?: number;
  zoom_level?: number;
  visible_columns?: string[];
  visible_filters?: string[];
  filter_presets?: FilterPreset[];
}

export interface AppState {
  data: TableRow[];
  filteredData: TableRow[];
  filters: Filters;
  currentPage: number;
  itemsPerPage: number;
  totalItems: number;
  sortColumn: string | null;
  sortDirection: 'asc' | 'desc';
  loading: boolean;
  columnOrder: string[];
  visibleColumns: string[];
  visibleFilters: string[];
  filterPresets: FilterPreset[];
  selectedPreset: string | null;
  zoomLevel: number;
}

export const DEFAULT_FILTERS: Filters = {
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
