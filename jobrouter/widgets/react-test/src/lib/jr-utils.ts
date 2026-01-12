export function getJrConfig<T>(id: string, defaultValue: T): T {
  const el = document.getElementById(id);
  if (!el || !el.textContent) return defaultValue;
  try {
    return JSON.parse(el.textContent) as T;
  } catch (e) {
    console.warn(`Could not parse JobRouter config for ${id}:`, e);
    return defaultValue;
  }
}

export function getJrText(id: string, defaultValue: string): string {
  const el = document.getElementById(id);
  return el?.textContent?.trim() || defaultValue;
}

export interface JrColumn {
  id: string;
  label: string;
  type?: 'actions' | 'status' | 'currency' | 'date' | 'number' | 'text';
  align?: 'left' | 'center' | 'right';
}

export interface JrDropdownOption {
  id: string;
  label: string;
}

export interface JrDropdownOptions {
  status: JrDropdownOption[];
  schritt: JrDropdownOption[];
  laufzeit: JrDropdownOption[];
  coor: JrDropdownOption[];
  weiterbelasten: JrDropdownOption[];
  gesellschaft: JrDropdownOption[];
  fonds: JrDropdownOption[];
}

export interface JrUserPreferences {
  filter?: string;
  column_order?: string;
  sort_column?: string;
  sort_direction?: 'asc' | 'desc';
  current_page?: number;
  entries_per_page?: number;
  zoom_level?: number;
}
