import { createContext, useContext, useState, useCallback, useMemo, type ReactNode } from 'react';
import type { AppState, Filters, FilterPreset, TableRow, Column, FilterConfig } from './types';
import { DEFAULT_FILTERS } from './types';
import { COLUMNS, MOCK_DATA, DEFAULT_FILTER_PRESETS, FILTER_CONFIG } from './constants';

interface SimplifyTableContextType {
  state: AppState;
  columns: Column[];
  filterConfigs: FilterConfig[];

  // Actions
  setFilters: (filters: Partial<Filters>) => void;
  resetFilters: () => void;
  setSort: (column: string | null, direction: 'asc' | 'desc') => void;
  setPage: (page: number) => void;
  setItemsPerPage: (count: number) => void;
  setColumnOrder: (order: string[]) => void;
  toggleColumnVisibility: (columnId: string) => void;
  toggleFilterVisibility: (filterId: string) => void;
  setFilterPresets: (presets: FilterPreset[]) => void;
  addFilterPreset: (preset: FilterPreset) => void;
  removeFilterPreset: (presetId: string) => void;
  togglePresetVisibility: (presetId: string) => void;
  reorderPresets: (fromIndex: number, toIndex: number) => void;
  applyPreset: (presetId: string) => void;
  setSelectedPreset: (presetId: string | null) => void;
  setZoom: (level: number) => void;
  fetchData: () => void;
}

const SimplifyTableContext = createContext<SimplifyTableContextType | null>(null);

export function useSimplifyTable() {
  const context = useContext(SimplifyTableContext);
  if (!context) {
    throw new Error('useSimplifyTable must be used within a SimplifyTableProvider');
  }
  return context;
}

interface SimplifyTableProviderProps {
  children: ReactNode;
}

export function SimplifyTableProvider({ children }: SimplifyTableProviderProps) {
  const [state, setState] = useState<AppState>(() => ({
    data: MOCK_DATA,
    filteredData: MOCK_DATA,
    filters: { ...DEFAULT_FILTERS },
    currentPage: 1,
    itemsPerPage: 25,
    totalItems: MOCK_DATA.length,
    sortColumn: null,
    sortDirection: 'asc',
    loading: false,
    columnOrder: COLUMNS.map((c) => c.id),
    visibleColumns: COLUMNS.map((c) => c.id),
    visibleFilters: FILTER_CONFIG.map((f) => f.id),
    filterPresets: DEFAULT_FILTER_PRESETS,
    selectedPreset: null,
    zoomLevel: 1.0,
  }));

  const columns = useMemo(() => {
    return state.columnOrder
      .map((id) => COLUMNS.find((c) => c.id === id))
      .filter((c): c is Column => c !== undefined)
      .map((c) => ({ ...c, visible: state.visibleColumns.includes(c.id) }));
  }, [state.columnOrder, state.visibleColumns]);

  const filterConfigs = useMemo(() => {
    return FILTER_CONFIG.map((f) => ({
      ...f,
      visible: state.visibleFilters.includes(f.id),
    }));
  }, [state.visibleFilters]);

  // Apply filters and sorting to data
  const applyFiltersAndSort = useCallback(
    (data: TableRow[], filters: Filters, sortColumn: string | null, sortDirection: 'asc' | 'desc'): TableRow[] => {
      let result = [...data];

      // Apply filters
      if (filters.status !== 'all') {
        result = result.filter((row) => {
          const status = (row.status || '').toLowerCase();
          if (filters.status === 'completed') return status === 'completed' || status === 'beendet';
          if (filters.status === 'aktiv_alle') return status !== 'completed' && status !== 'beendet';
          if (filters.status === 'faellig') return status === 'faellig' || status === 'fällig' || status === 'due';
          if (filters.status === 'not_faellig') return status === 'not_faellig' || status === 'nicht fällig' || status === 'not_due';
          return true;
        });
      }

      if (filters.schritt.length > 0) {
        result = result.filter((row) => {
          const stepLabel = (row.stepLabel || '').toLowerCase();
          return filters.schritt.some((s) => stepLabel.includes(s.toLowerCase()));
        });
      }

      if (filters.kreditor) {
        result = result.filter((row) => (row.creditorName || '').toLowerCase().includes(filters.kreditor.toLowerCase()));
      }

      if (filters.dokumentId) {
        result = result.filter((row) => (row.documentId || '').toLowerCase().includes(filters.dokumentId.toLowerCase()));
      }

      if (filters.bearbeiter) {
        result = result.filter((row) => (row.fullName || '').toLowerCase().includes(filters.bearbeiter.toLowerCase()));
      }

      if (filters.rolle) {
        result = result.filter((row) => (row.jobFunction || '').toLowerCase().includes(filters.rolle.toLowerCase()));
      }

      if (filters.rechnungsnummer) {
        result = result.filter((row) => (row.invoiceNumber || '').toLowerCase().includes(filters.rechnungsnummer.toLowerCase()));
      }

      if (filters.rechnungstyp) {
        result = result.filter((row) => (row.invoiceType || '').toLowerCase().includes(filters.rechnungstyp.toLowerCase()));
      }

      if (filters.gesellschaft.length > 0) {
        result = result.filter((row) => filters.gesellschaft.some((g) => (row.companyName || '').toLowerCase().includes(g.toLowerCase())));
      }

      if (filters.fonds.length > 0) {
        result = result.filter((row) => filters.fonds.some((f) => (row.fund || '').toLowerCase().includes(f.toLowerCase())));
      }

      if (filters.rechnungsdatumFrom) {
        result = result.filter((row) => row.invoiceDate && row.invoiceDate >= filters.rechnungsdatumFrom);
      }

      if (filters.rechnungsdatumTo) {
        result = result.filter((row) => row.invoiceDate && row.invoiceDate <= filters.rechnungsdatumTo);
      }

      if (filters.bruttobetragFrom) {
        const minAmount = parseFloat(filters.bruttobetragFrom);
        result = result.filter((row) => {
          const amount = typeof row.grossAmount === 'number' ? row.grossAmount : parseFloat(row.grossAmount || '0');
          return amount >= minAmount;
        });
      }

      if (filters.bruttobetragTo) {
        const maxAmount = parseFloat(filters.bruttobetragTo);
        result = result.filter((row) => {
          const amount = typeof row.grossAmount === 'number' ? row.grossAmount : parseFloat(row.grossAmount || '0');
          return amount <= maxAmount;
        });
      }

      if (filters.weiterbelasten !== 'all') {
        result = result.filter((row) => (row.chargeable || '').toLowerCase() === filters.weiterbelasten.toLowerCase());
      }

      if (filters.coor !== 'all') {
        result = result.filter((row) => {
          const hasCoor = !!row.orderId;
          return filters.coor === 'Ja' ? hasCoor : !hasCoor;
        });
      }

      // Apply sorting
      if (sortColumn) {
        result.sort((a, b) => {
          const aVal = a[sortColumn];
          const bVal = b[sortColumn];

          if (aVal === null || aVal === undefined) return 1;
          if (bVal === null || bVal === undefined) return -1;

          let comparison = 0;
          if (typeof aVal === 'number' && typeof bVal === 'number') {
            comparison = aVal - bVal;
          } else {
            comparison = String(aVal).localeCompare(String(bVal), 'de');
          }

          return sortDirection === 'asc' ? comparison : -comparison;
        });
      }

      return result;
    },
    [],
  );

  const setFilters = useCallback(
    (newFilters: Partial<Filters>) => {
      setState((prev) => {
        const updatedFilters = { ...prev.filters, ...newFilters };
        const filteredData = applyFiltersAndSort(prev.data, updatedFilters, prev.sortColumn, prev.sortDirection);
        return {
          ...prev,
          filters: updatedFilters,
          filteredData,
          totalItems: filteredData.length,
          currentPage: 1,
          selectedPreset: 'individual', // Mark as individual when filters change
        };
      });
    },
    [applyFiltersAndSort],
  );

  const resetFilters = useCallback(() => {
    setState((prev) => {
      const filteredData = applyFiltersAndSort(prev.data, DEFAULT_FILTERS, prev.sortColumn, prev.sortDirection);
      return {
        ...prev,
        filters: { ...DEFAULT_FILTERS },
        filteredData,
        totalItems: filteredData.length,
        currentPage: 1,
        selectedPreset: null,
      };
    });
  }, [applyFiltersAndSort]);

  const setSort = useCallback(
    (column: string | null, direction: 'asc' | 'desc') => {
      setState((prev) => {
        const filteredData = applyFiltersAndSort(prev.data, prev.filters, column, direction);
        return {
          ...prev,
          sortColumn: column,
          sortDirection: direction,
          filteredData,
          currentPage: 1,
        };
      });
    },
    [applyFiltersAndSort],
  );

  const setPage = useCallback((page: number) => {
    setState((prev) => ({ ...prev, currentPage: page }));
  }, []);

  const setItemsPerPage = useCallback((count: number) => {
    setState((prev) => ({ ...prev, itemsPerPage: count, currentPage: 1 }));
  }, []);

  const setColumnOrder = useCallback((order: string[]) => {
    setState((prev) => ({ ...prev, columnOrder: order }));
  }, []);

  const toggleColumnVisibility = useCallback((columnId: string) => {
    // Don't allow hiding the actions column
    if (columnId === 'actions') return;

    setState((prev) => {
      const isVisible = prev.visibleColumns.includes(columnId);
      const visibleColumns = isVisible ? prev.visibleColumns.filter((id) => id !== columnId) : [...prev.visibleColumns, columnId];
      return { ...prev, visibleColumns };
    });
  }, []);

  const toggleFilterVisibility = useCallback((filterId: string) => {
    setState((prev) => {
      const isVisible = prev.visibleFilters.includes(filterId);
      const visibleFilters = isVisible ? prev.visibleFilters.filter((id) => id !== filterId) : [...prev.visibleFilters, filterId];
      return { ...prev, visibleFilters };
    });
  }, []);

  const setFilterPresets = useCallback((presets: FilterPreset[]) => {
    setState((prev) => ({ ...prev, filterPresets: presets }));
  }, []);

  const addFilterPreset = useCallback((preset: FilterPreset) => {
    setState((prev) => ({
      ...prev,
      filterPresets: [...prev.filterPresets, preset],
      selectedPreset: preset.id,
    }));
  }, []);

  const removeFilterPreset = useCallback((presetId: string) => {
    setState((prev) => ({
      ...prev,
      filterPresets: prev.filterPresets.filter((p) => p.id !== presetId),
      selectedPreset: prev.selectedPreset === presetId ? null : prev.selectedPreset,
    }));
  }, []);

  const togglePresetVisibility = useCallback((presetId: string) => {
    setState((prev) => ({
      ...prev,
      filterPresets: prev.filterPresets.map((p) => (p.id === presetId ? { ...p, visible: !p.visible } : p)),
    }));
  }, []);

  const reorderPresets = useCallback((fromIndex: number, toIndex: number) => {
    setState((prev) => {
      const newPresets = [...prev.filterPresets];
      const [moved] = newPresets.splice(fromIndex, 1);
      newPresets.splice(toIndex, 0, moved);
      return { ...prev, filterPresets: newPresets };
    });
  }, []);

  const applyPreset = useCallback(
    (presetId: string) => {
      setState((prev) => {
        const preset = prev.filterPresets.find((p) => p.id === presetId);
        if (!preset) return prev;

        const newFilters = { ...DEFAULT_FILTERS, ...preset.filters };
        const sortColumn = preset.sort?.column ?? prev.sortColumn;
        const sortDirection = preset.sort?.direction ?? prev.sortDirection;
        const filteredData = applyFiltersAndSort(prev.data, newFilters, sortColumn, sortDirection);

        return {
          ...prev,
          filters: newFilters,
          sortColumn,
          sortDirection,
          filteredData,
          totalItems: filteredData.length,
          currentPage: 1,
          selectedPreset: presetId,
        };
      });
    },
    [applyFiltersAndSort],
  );

  const setSelectedPreset = useCallback((presetId: string | null) => {
    setState((prev) => ({ ...prev, selectedPreset: presetId }));
  }, []);

  const setZoom = useCallback((level: number) => {
    const clampedLevel = Math.max(0.5, Math.min(2.0, level));
    setState((prev) => ({ ...prev, zoomLevel: clampedLevel }));
  }, []);

  const fetchData = useCallback(() => {
    setState((prev) => {
      const filteredData = applyFiltersAndSort(prev.data, prev.filters, prev.sortColumn, prev.sortDirection);
      return {
        ...prev,
        filteredData,
        totalItems: filteredData.length,
      };
    });
  }, [applyFiltersAndSort]);

  const value = useMemo(
    () => ({
      state,
      columns,
      filterConfigs,
      setFilters,
      resetFilters,
      setSort,
      setPage,
      setItemsPerPage,
      setColumnOrder,
      toggleColumnVisibility,
      toggleFilterVisibility,
      setFilterPresets,
      addFilterPreset,
      removeFilterPreset,
      togglePresetVisibility,
      reorderPresets,
      applyPreset,
      setSelectedPreset,
      setZoom,
      fetchData,
    }),
    [
      state,
      columns,
      filterConfigs,
      setFilters,
      resetFilters,
      setSort,
      setPage,
      setItemsPerPage,
      setColumnOrder,
      toggleColumnVisibility,
      toggleFilterVisibility,
      setFilterPresets,
      addFilterPreset,
      removeFilterPreset,
      togglePresetVisibility,
      reorderPresets,
      applyPreset,
      setSelectedPreset,
      setZoom,
      fetchData,
    ],
  );

  return <SimplifyTableContext.Provider value={value}>{children}</SimplifyTableContext.Provider>;
}
