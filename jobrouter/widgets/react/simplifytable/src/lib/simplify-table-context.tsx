import { createContext, useContext, useState, useCallback, useMemo, useEffect, useRef, type ReactNode } from 'react';
import type { AppState, Filters, FilterPreset, Column, FilterConfig } from './types';
import { DEFAULT_FILTERS } from './types';
import { DEFAULT_COLUMNS, DEFAULT_DROPDOWN_OPTIONS, DEFAULT_FILTER_CONFIG, DEFAULT_FILTER_PRESETS, MOCK_DATA, buildFilterConfig } from './constants';

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
  fetchData: (overrides?: {
    filters?: Filters;
    currentPage?: number;
    itemsPerPage?: number;
    sortColumn?: string | null;
    sortDirection?: 'asc' | 'desc';
  }) => void;
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
    columnOrder: DEFAULT_COLUMNS.map((c) => c.id),
    visibleColumns: DEFAULT_COLUMNS.map((c) => c.id),
    visibleFilters: DEFAULT_FILTER_CONFIG.map((f) => f.id),
    filterPresets: DEFAULT_FILTER_PRESETS,
    selectedPreset: null,
    zoomLevel: 1.0,
  }));

  const [columnsConfig, setColumnsConfig] = useState<Column[]>(DEFAULT_COLUMNS);
  const [dropdownOptions, setDropdownOptions] = useState(DEFAULT_DROPDOWN_OPTIONS);
  const currentUserRef = useRef('');
  const [isInitialized, setIsInitialized] = useState(false);
  const stateRef = useRef(state);

  useEffect(() => {
    stateRef.current = state;
  }, [state]);

  const columns = useMemo(() => {
    return state.columnOrder
      .map((id) => columnsConfig.find((c) => c.id === id))
      .filter((c): c is Column => c !== undefined)
      .map((c) => ({ ...c, visible: state.visibleColumns.includes(c.id) }));
  }, [state.columnOrder, state.visibleColumns, columnsConfig]);

  const filterConfigs = useMemo(() => {
    const baseConfig = buildFilterConfig(dropdownOptions);
    return baseConfig.map((f) => ({
      ...f,
      visible: state.visibleFilters.includes(f.id),
    }));
  }, [state.visibleFilters, dropdownOptions]);

  const buildEndpoint = useCallback((fileName: string) => {
    return new URL(fileName, window.location.href).toString();
  }, []);

  const fetchJson = useCallback(async (url: string, options?: RequestInit) => {
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
    });

    if (!response.ok) {
      throw new Error(`Request failed: ${response.status}`);
    }

    return response.json();
  }, []);

  type QuerySnapshot = Pick<AppState, 'currentPage' | 'itemsPerPage' | 'sortColumn' | 'sortDirection' | 'filters'>;

  const queryServer = useCallback(
    async (snapshot: QuerySnapshot) => {
      const url = new URL(buildEndpoint('query.php'));
      const { filters } = snapshot;

      url.searchParams.set('page', String(snapshot.currentPage));
      url.searchParams.set('perPage', String(snapshot.itemsPerPage));
      url.searchParams.set('sortColumn', snapshot.sortColumn ?? '');
      url.searchParams.set('sortDirection', snapshot.sortDirection);
      url.searchParams.set('username', currentUserRef.current);

      if (filters.kreditor) url.searchParams.set('kreditor', filters.kreditor);
      if (filters.weiterbelasten) url.searchParams.set('weiterbelasten', filters.weiterbelasten);
      if (filters.rolle) url.searchParams.set('rolle', filters.rolle);
      if (filters.rechnungstyp) url.searchParams.set('rechnungstyp', filters.rechnungstyp);
      if (filters.bruttobetragFrom) url.searchParams.set('bruttobetragFrom', filters.bruttobetragFrom);
      if (filters.bruttobetragTo) url.searchParams.set('bruttobetragTo', filters.bruttobetragTo);
      if (filters.dokumentId) url.searchParams.set('dokumentId', filters.dokumentId);
      if (filters.bearbeiter) url.searchParams.set('bearbeiter', filters.bearbeiter);
      if (filters.rechnungsnummer) url.searchParams.set('rechnungsnummer', filters.rechnungsnummer);
      if (filters.status) url.searchParams.set('status', filters.status);
      if (filters.laufzeit) url.searchParams.set('laufzeit', filters.laufzeit);
      if (filters.coor) url.searchParams.set('coor', filters.coor);
      if (filters.rechnungsdatumFrom) url.searchParams.set('rechnungsdatumFrom', filters.rechnungsdatumFrom);
      if (filters.rechnungsdatumTo) url.searchParams.set('rechnungsdatumTo', filters.rechnungsdatumTo);

      if (filters.gesellschaft.length > 0) {
        url.searchParams.set('gesellschaft', JSON.stringify(filters.gesellschaft));
      }
      if (filters.fonds.length > 0) {
        url.searchParams.set('fonds', JSON.stringify(filters.fonds));
      }
      if (filters.schritt.length > 0) {
        url.searchParams.set('schritt', JSON.stringify(filters.schritt));
      }

      return fetchJson(url.toString());
    },
    [buildEndpoint, fetchJson],
  );

  const fetchData = useCallback(
    async (overrides?: Partial<QuerySnapshot>) => {
      const snapshot: QuerySnapshot = {
        currentPage: overrides?.currentPage ?? stateRef.current.currentPage,
        itemsPerPage: overrides?.itemsPerPage ?? stateRef.current.itemsPerPage,
        sortColumn: overrides?.sortColumn ?? stateRef.current.sortColumn,
        sortDirection: overrides?.sortDirection ?? stateRef.current.sortDirection,
        filters: overrides?.filters ?? stateRef.current.filters,
      };

      setState((prev) => ({ ...prev, loading: true }));

      try {
        const response = await queryServer(snapshot);
        const data = Array.isArray(response?.data) ? response.data : [];
        const total = typeof response?.total === 'number' ? response.total : data.length;

        setState((prev) => ({
          ...prev,
          data,
          filteredData: data,
          totalItems: total,
          loading: false,
        }));
      } catch (error) {
        setState((prev) => ({ ...prev, loading: false }));
      }
    },
    [queryServer],
  );

  useEffect(() => {
    let isCancelled = false;

    const initialize = async () => {
      setState((prev) => ({ ...prev, loading: true }));

      try {
        const url = new URL(window.location.href);
        const username = url.searchParams.get('username') ?? '';
        currentUserRef.current = username;

        const initUrl = new URL(buildEndpoint('init.php'));
        if (username) {
          initUrl.searchParams.set('username', username);
        }

        const initResponse = await fetchJson(initUrl.toString());

        if (isCancelled) return;

        const initColumns = Array.isArray(initResponse?.columns) ? initResponse.columns : DEFAULT_COLUMNS;
        const initDropdowns = initResponse?.dropdownOptions ?? DEFAULT_DROPDOWN_OPTIONS;
        const preferences = initResponse?.userPreferences ?? null;

        setColumnsConfig(initColumns);
        setDropdownOptions(initDropdowns);

        const defaultColumnOrder = initColumns.map((c: Column) => c.id);
        const defaultVisibleFilters = buildFilterConfig(initDropdowns).map((f) => f.id);

        const nextFilters = preferences?.filter ? { ...DEFAULT_FILTERS, ...preferences.filter } : stateRef.current.filters;

        const nextSortColumn = preferences?.sort_column ?? stateRef.current.sortColumn;
        const nextSortDirection = preferences?.sort_direction ?? stateRef.current.sortDirection;
        const nextCurrentPage = preferences?.current_page ?? stateRef.current.currentPage;
        const nextItemsPerPage = preferences?.entries_per_page ?? stateRef.current.itemsPerPage;

        setState((prev) => ({
          ...prev,
          filters: nextFilters,
          sortColumn: nextSortColumn,
          sortDirection: nextSortDirection,
          currentPage: nextCurrentPage,
          itemsPerPage: nextItemsPerPage,
          zoomLevel: preferences?.zoom_level ?? prev.zoomLevel,
          columnOrder: preferences?.column_order?.length ? preferences.column_order : defaultColumnOrder,
          visibleColumns: preferences?.visible_columns?.length ? preferences.visible_columns : defaultColumnOrder,
          visibleFilters: preferences?.visible_filters?.length ? preferences.visible_filters : defaultVisibleFilters,
          filterPresets: preferences?.filter_presets?.length ? preferences.filter_presets : prev.filterPresets,
        }));

        await fetchData({
          filters: nextFilters,
          sortColumn: nextSortColumn,
          sortDirection: nextSortDirection,
          currentPage: nextCurrentPage,
          itemsPerPage: nextItemsPerPage,
        });
      } catch (error) {
        setState((prev) => ({ ...prev, loading: false }));
      } finally {
        if (!isCancelled) {
          setIsInitialized(true);
        }
      }
    };

    void initialize();

    return () => {
      isCancelled = true;
    };
  }, [buildEndpoint, fetchJson, fetchData]);

  const savePreferences = useCallback(async () => {
    if (!currentUserRef.current) return;

    const payload = new URLSearchParams();
    payload.set('username', currentUserRef.current);
    payload.set('filter', JSON.stringify(stateRef.current.filters));
    payload.set('column_order', JSON.stringify(stateRef.current.columnOrder));
    payload.set('sort_column', stateRef.current.sortColumn ?? '');
    payload.set('sort_direction', stateRef.current.sortDirection);
    payload.set('current_page', String(stateRef.current.currentPage));
    payload.set('entries_per_page', String(stateRef.current.itemsPerPage));
    payload.set('zoom_level', String(stateRef.current.zoomLevel));
    payload.set('visible_columns', JSON.stringify(stateRef.current.visibleColumns));
    payload.set('visible_filters', JSON.stringify(stateRef.current.visibleFilters));
    payload.set('filter_presets', JSON.stringify(stateRef.current.filterPresets));

    try {
      await fetch(buildEndpoint('savepreferences.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString(),
        credentials: 'same-origin',
      });
    } catch (error) {
      // ignore save errors for now
    }
  }, [buildEndpoint]);

  useEffect(() => {
    if (!isInitialized) return;

    const timer = window.setTimeout(() => {
      void savePreferences();
    }, 600);

    return () => window.clearTimeout(timer);
  }, [
    isInitialized,
    savePreferences,
    state.filters,
    state.columnOrder,
    state.sortColumn,
    state.sortDirection,
    state.currentPage,
    state.itemsPerPage,
    state.zoomLevel,
    state.visibleColumns,
    state.visibleFilters,
    state.filterPresets,
  ]);

  const setFilters = useCallback((newFilters: Partial<Filters>) => {
    setState((prev) => {
      const updatedFilters = { ...prev.filters, ...newFilters };
      return {
        ...prev,
        filters: updatedFilters,
        currentPage: 1,
        selectedPreset: 'individual',
      };
    });
  }, []);

  const resetFilters = useCallback(() => {
    setState((prev) => ({
      ...prev,
      filters: { ...DEFAULT_FILTERS },
      currentPage: 1,
      selectedPreset: null,
    }));

    void fetchData({ filters: { ...DEFAULT_FILTERS }, currentPage: 1 });
  }, [fetchData]);

  const setSort = useCallback(
    (column: string | null, direction: 'asc' | 'desc') => {
      setState((prev) => ({
        ...prev,
        sortColumn: column,
        sortDirection: direction,
        currentPage: 1,
      }));

      void fetchData({ sortColumn: column, sortDirection: direction, currentPage: 1 });
    },
    [fetchData],
  );

  const setPage = useCallback(
    (page: number) => {
      setState((prev) => ({ ...prev, currentPage: page }));
      void fetchData({ currentPage: page });
    },
    [fetchData],
  );

  const setItemsPerPage = useCallback(
    (count: number) => {
      setState((prev) => ({ ...prev, itemsPerPage: count, currentPage: 1 }));
      void fetchData({ itemsPerPage: count, currentPage: 1 });
    },
    [fetchData],
  );

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

        void fetchData({
          filters: newFilters,
          sortColumn,
          sortDirection,
          currentPage: 1,
        });

        return {
          ...prev,
          filters: newFilters,
          sortColumn,
          sortDirection,
          currentPage: 1,
          selectedPreset: presetId,
        };
      });
    },
    [fetchData],
  );

  const setSelectedPreset = useCallback((presetId: string | null) => {
    setState((prev) => ({ ...prev, selectedPreset: presetId }));
  }, []);

  const setZoom = useCallback((level: number) => {
    const clampedLevel = Math.max(0.5, Math.min(2.0, level));
    setState((prev) => ({ ...prev, zoomLevel: clampedLevel }));
  }, []);

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
