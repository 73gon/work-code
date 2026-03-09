import type { FilterPreset, TableRow, DropdownOptions } from './types';
import { getDefaultColumns, getDefaultFilters, buildFilterConfigFromRegistry } from './field-registry';

// ============================================
// COLUMNS — derived from field registry
// ============================================

export const DEFAULT_COLUMNS = getDefaultColumns();

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
  kostenuebernahme: [
    { id: 'Ja', label: 'Ja' },
    { id: 'Nein', label: 'Nein' },
  ],
  gesellschaft: [
    { id: '001', label: 'Gesellschaft A GmbH' },
    { id: '002', label: 'Gesellschaft B AG' },
    { id: '003', label: 'Gesellschaft C KG' },
    { id: '004', label: 'Holding GmbH' },
    { id: '005', label: 'Gesellschaft D GmbH' },
    { id: '006', label: 'Gesellschaft E AG' },
    { id: '007', label: 'Gesellschaft F KG' },
    { id: '008', label: 'Gesellschaft G GmbH' },
    { id: '009', label: 'Gesellschaft H AG' },
    { id: '010', label: 'Gesellschaft I KG' },
    { id: '011', label: 'Gesellschaft J GmbH' },
    { id: '012', label: 'Gesellschaft K AG' },
    { id: '013', label: 'Gesellschaft L KG' },
    { id: '014', label: 'Gesellschaft M GmbH' },
    { id: '015', label: 'Gesellschaft N AG' },
    { id: '016', label: 'Gesellschaft O KG' },
    { id: '017', label: 'Gesellschaft P GmbH' },
    { id: '018', label: 'Gesellschaft Q AG' },
    { id: '019', label: 'Gesellschaft R KG' },
    { id: '020', label: 'Gesellschaft S GmbH' },
    { id: '021', label: 'Gesellschaft T AG' },
    { id: '022', label: 'Gesellschaft U KG' },
    { id: '023', label: 'Gesellschaft V GmbH' },
    { id: '024', label: 'Gesellschaft W AG' },
  ],
  fonds: [
    { id: 'FUND-A', label: 'FUND-A' },
    { id: 'FUND-B', label: 'FUND-B' },
    { id: 'FUND-C', label: 'FUND-C' },
    { id: 'FUND-D', label: 'FUND-D' },
    { id: 'FUND-E', label: 'FUND-E' },
    { id: 'FUND-F', label: 'FUND-F' },
    { id: 'FUND-G', label: 'FUND-G' },
    { id: 'FUND-H', label: 'FUND-H' },
    { id: 'FUND-I', label: 'FUND-I' },
    { id: 'FUND-J', label: 'FUND-J' },
    { id: 'FUND-K', label: 'FUND-K' },
    { id: 'FUND-L', label: 'FUND-L' },
    { id: 'FUND-M', label: 'FUND-M' },
    { id: 'FUND-N', label: 'FUND-N' },
    { id: 'FUND-O', label: 'FUND-O' },
    { id: 'FUND-P', label: 'FUND-P' },
    { id: 'FUND-Q', label: 'FUND-Q' },
    { id: 'FUND-R', label: 'FUND-R' },
    { id: 'FUND-S', label: 'FUND-S' },
    { id: 'FUND-T', label: 'FUND-T' },
    { id: 'FUND-U', label: 'FUND-U' },
    { id: 'FUND-V', label: 'FUND-V' },
    { id: 'FUND-W', label: 'FUND-W' },
    { id: 'FUND-X', label: 'FUND-X' },
  ],
};

// ============================================
// FILTER CONFIGURATION — derived from field registry
// ============================================

export const buildFilterConfig = buildFilterConfigFromRegistry;

export const DEFAULT_FILTER_CONFIG = buildFilterConfig(DEFAULT_DROPDOWN_OPTIONS);

// ============================================
// DEFAULT FILTER VALUES — derived from field registry
// ============================================

export const DEFAULT_FILTERS = getDefaultFilters();

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
];

// ============================================
// LAZY LOADING CONFIGURATION
// ============================================

/** localStorage key for tracking the last time the user interacted with the widget */
export const INTERACTION_STORAGE_KEY = 'simplifyTable_lastInteraction';

/** Auto-load data without a click if user interacted within this many ms (3 hours) */
export const AUTO_LOAD_THRESHOLD_MS = 3 * 60 * 60 * 1000;

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
