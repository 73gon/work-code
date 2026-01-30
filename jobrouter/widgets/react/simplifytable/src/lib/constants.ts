import type { Column, DropdownOptions, FilterConfig, FilterPreset, TableRow } from './types';

// ============================================
// COLUMNS CONFIGURATION (from SimplifyTable.php)
// ============================================

export const DEFAULT_COLUMNS: Column[] = [
  { id: 'actions', label: '', type: 'actions', align: 'center', visible: true },
  { id: 'status', label: 'Status', type: 'status', align: 'center', visible: true },
  { id: 'entryDate', label: 'Eingangsdatum', type: 'date', align: 'left', visible: true },
  { id: 'stepLabel', label: 'Schritt', type: 'text', align: 'left', visible: true },
  { id: 'startDate', label: 'Startdatum (Schritt)', type: 'date', align: 'left', visible: true },
  { id: 'jobFunction', label: 'Rolle', type: 'text', align: 'left', visible: true },
  { id: 'fullName', label: 'Bearbeiter', type: 'text', align: 'left', visible: true },
  { id: 'documentId', label: 'DokumentId', type: 'text', align: 'left', visible: true },
  { id: 'companyName', label: 'Gesellschaft', type: 'text', align: 'left', visible: true },
  { id: 'fund', label: 'Fonds', type: 'text', align: 'left', visible: true },
  { id: 'creditorName', label: 'Kreditor', type: 'text', align: 'left', visible: true },
  { id: 'invoiceType', label: 'Rechnungstyp', type: 'text', align: 'left', visible: true },
  { id: 'invoiceNumber', label: 'Rechnungsnummer', type: 'text', align: 'left', visible: true },
  { id: 'invoiceDate', label: 'Rechnungsdatum', type: 'date', align: 'left', visible: true },
  { id: 'grossAmount', label: 'Bruttobetrag', type: 'currency', align: 'left', visible: true },
  { id: 'dueDate', label: 'Fälligkeit', type: 'date', align: 'left', visible: true },
  { id: 'orderId', label: 'Auftragsnummer', type: 'text', align: 'left', visible: true },
  { id: 'paymentAmount', label: 'Zahlbetrag', type: 'currency', align: 'left', visible: true },
  { id: 'paymentDate', label: 'Zahldatum', type: 'date', align: 'left', visible: true },
  { id: 'chargeable', label: 'Weiterbelasten', type: 'text', align: 'center', visible: true },
];

// ============================================
// DROPDOWN OPTIONS (from SimplifyTable.php - with mock data)
// ============================================

export const DEFAULT_DROPDOWN_OPTIONS: DropdownOptions = {
  status: [
    { id: 'completed', label: 'Beendet' },
    { id: 'aktiv_alle', label: 'Aktiv Alle' },
    { id: 'faellig', label: 'Aktiv Fällig' },
    { id: 'not_faellig', label: 'Aktiv Nicht Fällig' },
  ],
  schritt: [
    { id: '1000', label: 'Eingang' },
    { id: '2000', label: 'Prüfung' },
    { id: '3000', label: 'Freigabe' },
    { id: '4000', label: 'Buchhaltung' },
    { id: '4001', label: 'Coor Schnittstelle' },
    { id: '5000', label: 'Zahlung' },
  ],
  laufzeit: [
    { id: '0-5 Tage', label: '0-5 Tage' },
    { id: '6-10 Tage', label: '6-10 Tage' },
    { id: '11-20 Tage', label: '11-20 Tage' },
    { id: '21+ Tage', label: '21+ Tage' },
  ],
  coor: [
    { id: 'Ja', label: 'Ja' },
    { id: 'Nein', label: 'Nein' },
  ],
  weiterbelasten: [
    { id: 'Ja', label: 'Ja' },
    { id: 'Nein', label: 'Nein' },
  ],
  gesellschaft: [
    { id: '001', label: 'Gesellschaft A GmbH' },
    { id: '002', label: 'Gesellschaft B AG' },
    { id: '003', label: 'Gesellschaft C KG' },
    { id: '004', label: 'Holding GmbH' },
  ],
  fonds: [
    { id: 'FUND-A', label: 'FUND-A' },
    { id: 'FUND-B', label: 'FUND-B' },
    { id: 'FUND-C', label: 'FUND-C' },
    { id: 'FUND-D', label: 'FUND-D' },
  ],
};

// ============================================
// FILTER CONFIGURATION
// ============================================

export const buildFilterConfig = (dropdownOptions: DropdownOptions): FilterConfig[] => [
  { id: 'status', label: 'Status', type: 'dropdown', filterKey: 'status', options: dropdownOptions.status, visible: true },
  { id: 'schritt', label: 'Schritt', type: 'autocomplete', filterKey: 'schritt', options: dropdownOptions.schritt, visible: true },
  { id: 'dokumentId', label: 'DokumentId', type: 'text', filterKey: 'dokumentId', visible: true },
  { id: 'bearbeiter', label: 'Bearbeiter', type: 'text', filterKey: 'bearbeiter', visible: true },
  { id: 'rolle', label: 'Rolle', type: 'text', filterKey: 'rolle', visible: true },
  {
    id: 'gesellschaft',
    label: 'Gesellschaft',
    type: 'autocomplete',
    filterKey: 'gesellschaft',
    options: dropdownOptions.gesellschaft,
    visible: true,
  },
  { id: 'fonds', label: 'Fonds', type: 'autocomplete', filterKey: 'fonds', options: dropdownOptions.fonds, visible: true },
  { id: 'kreditor', label: 'Kreditor', type: 'text', filterKey: 'kreditor', visible: true },
  { id: 'rechnungsnummer', label: 'Rechnungsnummer', type: 'text', filterKey: 'rechnungsnummer', visible: true },
  { id: 'rechnungstyp', label: 'Rechnungstyp', type: 'text', filterKey: 'rechnungstyp', visible: true },
  {
    id: 'rechnungsdatum',
    label: 'Rechnungsdatum',
    type: 'daterange',
    filterKey: 'rechnungsdatum',
    options: { fromId: 'rechnungsdatumFrom', toId: 'rechnungsdatumTo' },
    visible: true,
  },
  {
    id: 'bruttobetrag',
    label: 'Bruttobetrag',
    type: 'numberrange',
    filterKey: 'bruttobetrag',
    options: { fromId: 'bruttobetragFrom', toId: 'bruttobetragTo' },
    visible: true,
  },
  {
    id: 'weiterbelasten',
    label: 'Weiterbelasten',
    type: 'dropdown',
    filterKey: 'weiterbelasten',
    options: dropdownOptions.weiterbelasten,
    visible: true,
  },
  { id: 'laufzeit', label: 'Laufzeit', type: 'dropdown', filterKey: 'laufzeit', options: dropdownOptions.laufzeit, visible: true },
  { id: 'coor', label: 'Coor', type: 'dropdown', filterKey: 'coor', options: dropdownOptions.coor, visible: true },
];

export const DEFAULT_FILTER_CONFIG = buildFilterConfig(DEFAULT_DROPDOWN_OPTIONS);

// ============================================
// DEFAULT FILTER PRESETS
// ============================================

export const DEFAULT_FILTER_PRESETS: FilterPreset[] = [
  {
    id: 'overdue',
    label: 'Überfällige Rechnungen',
    filters: { status: 'faellig' },
    sort: { column: 'dueDate', direction: 'asc' },
    visible: true,
    isCustom: false,
  },
  {
    id: 'coor',
    label: 'Coor Schnittstelle',
    filters: { status: 'aktiv_alle', schritt: ['4001'] },
    visible: true,
    isCustom: false,
  },
  {
    id: 'all_active',
    label: 'Alle Aktiven',
    filters: { status: 'aktiv_alle' },
    visible: true,
    isCustom: false,
  },
  {
    id: 'completed',
    label: 'Beendet',
    filters: { status: 'completed' },
    visible: true,
    isCustom: false,
  },
];

// ============================================
// MOCK DATA FOR TESTING
// ============================================

const generateMockDate = (daysAgo: number): string => {
  const date = new Date();
  date.setDate(date.getDate() - daysAgo);
  return date.toISOString().split('T')[0];
};

const randomFromArray = <T>(arr: T[]): T => arr[Math.floor(Math.random() * arr.length)];

export const generateMockData = (count: number = 50): TableRow[] => {
  const statuses = ['completed', 'faellig', 'not_faellig'];
  const steps = DEFAULT_DROPDOWN_OPTIONS.schritt;
  const companies = DEFAULT_DROPDOWN_OPTIONS.gesellschaft;
  const funds = DEFAULT_DROPDOWN_OPTIONS.fonds;
  const names = ['Max Müller', 'Anna Schmidt', 'Peter Weber', 'Maria Fischer', 'Thomas Wagner'];
  const roles = ['Sachbearbeiter', 'Teamleiter', 'Controller', 'Manager', 'Buchhalter'];
  const invoiceTypes = ['Eingangsrechnung', 'Gutschrift', 'Abschlagsrechnung', 'Schlussrechnung'];
  const creditors = ['Lieferant A GmbH', 'Service B AG', 'Material C KG', 'Dienstleister D', 'Zulieferer E'];

  return Array.from({ length: count }, (_, i) => {
    const status = randomFromArray(statuses);
    const step = randomFromArray(steps);
    const company = randomFromArray(companies);
    const fund = randomFromArray(funds);
    const daysAgo = Math.floor(Math.random() * 60);
    const grossAmount = (Math.random() * 50000 + 100).toFixed(2);

    return {
      id: `INV-${String(i + 1).padStart(5, '0')}`,
      status,
      incident: `INC-${String(i + 1).padStart(6, '0')}`,
      entryDate: generateMockDate(daysAgo + 5),
      stepLabel: step.label,
      startDate: generateMockDate(daysAgo),
      jobFunction: randomFromArray(roles),
      fullName: randomFromArray(names),
      documentId: `DOC-${String(Math.floor(Math.random() * 100000)).padStart(6, '0')}`,
      companyName: company.label,
      fund: fund.label,
      creditorName: randomFromArray(creditors),
      invoiceType: randomFromArray(invoiceTypes),
      invoiceNumber: `RE-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 10000)).padStart(5, '0')}`,
      invoiceDate: generateMockDate(daysAgo + 3),
      grossAmount: parseFloat(grossAmount),
      dueDate: generateMockDate(daysAgo - 10),
      orderId: Math.random() > 0.5 ? `ORD-${String(Math.floor(Math.random() * 10000)).padStart(5, '0')}` : '',
      paymentAmount: status === 'completed' ? parseFloat(grossAmount) : 0,
      paymentDate: status === 'completed' ? generateMockDate(daysAgo - 15) : '',
      chargeable: Math.random() > 0.7 ? 'Ja' : 'Nein',
      runtime: `${Math.floor(Math.random() * 30) + 1} Tage`,
      historyLink: `#history/${i + 1}`,
      invoice: `${Math.floor(Math.random() * 100000)}`,
      protocol: Math.random() > 0.5 ? `${Math.floor(Math.random() * 100000)}` : '',
    };
  });
};

export const MOCK_DATA: TableRow[] = generateMockData(100);
