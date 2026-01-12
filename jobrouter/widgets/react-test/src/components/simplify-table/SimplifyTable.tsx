import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getJrConfig, getJrText, type JrColumn, type JrDropdownOptions, type JrUserPreferences } from '@/lib/jr-utils';
import { FilterBar } from './FilterBar';
import { DataTable } from './DataTable';
import { Pagination } from './Pagination';
import { Button } from '@/components/ui/button';
import { Minus, Plus, RotateCcw, Settings, X } from 'lucide-react';
import { Tooltip } from '@/components/ui/tooltip';

// --- Types ---

export interface FilterState {
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

const initialFilters: FilterState = {
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

// --- Zoom Controls Component ---
function ZoomControls({ zoomLevel, onZoomChange, onClose }: { zoomLevel: number; onZoomChange: (level: number) => void; onClose: () => void }) {
  const handleZoomIn = () => onZoomChange(Math.min(2.0, zoomLevel + 0.1));
  const handleZoomOut = () => onZoomChange(Math.max(0.5, zoomLevel - 0.1));
  const handleReset = () => onZoomChange(1.0);

  return (
    <div className='absolute top-4 right-14 z-50 flex items-center gap-1 bg-[#3b3e4d] border border-[#4a4d5c] rounded-lg p-1 shadow-lg animate-in fade-in slide-in-from-right-4 duration-200'>
      <Tooltip content='Verkleinern'>
        <Button variant='ghost' size='sm' onClick={handleZoomOut} className='h-8 w-8 p-0 text-slate-400 hover:text-[#ffcc0d] hover:bg-[#4a4d5c]'>
          <Minus className='h-4 w-4' />
        </Button>
      </Tooltip>
      <Tooltip content='Zoom zurücksetzen'>
        <Button variant='ghost' size='sm' onClick={handleReset} className='h-8 w-8 p-0 text-slate-400 hover:text-[#ffcc0d] hover:bg-[#4a4d5c]'>
          <RotateCcw className='h-4 w-4' />
        </Button>
      </Tooltip>
      <Tooltip content='Vergrößern'>
        <Button variant='ghost' size='sm' onClick={handleZoomIn} className='h-8 w-8 p-0 text-slate-400 hover:text-[#ffcc0d] hover:bg-[#4a4d5c]'>
          <Plus className='h-4 w-4' />
        </Button>
      </Tooltip>
      <span className='text-[10px] text-slate-400 px-2 min-w-10 text-center'>{Math.round(zoomLevel * 100)}%</span>
      <div className='w-px h-4 bg-[#4a4d5c] mx-1' />
      <Button variant='ghost' size='sm' onClick={onClose} className='h-8 w-8 p-0 text-slate-400 hover:text-red-400 hover:bg-[#4a4d5c]'>
        <X className='h-4 w-4' />
      </Button>
    </div>
  );
}

// --- Main Component ---

export function SimplifyTable() {
  const queryClient = useQueryClient();

  // 1. Load Initial Data from DOM (Hydration)
  const columns = React.useMemo(() => getJrConfig<JrColumn[]>('simplifyTable_columns', []), []);
  const dropdownOptions = React.useMemo(
    () =>
      getJrConfig<JrDropdownOptions>('simplifyTable_dropdownOptions', {
        status: [],
        schritt: [],
        laufzeit: [],
        coor: [],
        weiterbelasten: [],
        gesellschaft: [],
        fonds: [],
      }),
    [],
  );
  const currentUser = React.useMemo(() => getJrText('simplifyTable_currentUser', ''), []);
  const userPreferences = React.useMemo(() => getJrConfig<JrUserPreferences>('simplifyTable_userPreferences', {}), []);

  // 2. Local State
  const [showZoom, setShowZoom] = React.useState(false);
  const [filters, setFilters] = React.useState<FilterState>(() => {
    if (userPreferences.filter) {
      try {
        const parsed = JSON.parse(userPreferences.filter);
        return { ...initialFilters, ...parsed };
      } catch (e) {
        return initialFilters;
      }
    }
    return initialFilters;
  });

  const [pagination, setPagination] = React.useState({
    pageIndex: (userPreferences.current_page || 1) - 1,
    pageSize: userPreferences.entries_per_page || 25,
  });

  const [sorting, setSorting] = React.useState<{ id: string; desc: boolean }[]>(() => {
    if (userPreferences.sort_column) {
      return [{ id: userPreferences.sort_column, desc: userPreferences.sort_direction === 'desc' }];
    }
    return [];
  });

  const [columnOrder, setColumnOrder] = React.useState<string[]>(() => {
    if (userPreferences.column_order) {
      try {
        return JSON.parse(userPreferences.column_order);
      } catch (e) {
        return columns.map((c) => c.id);
      }
    }
    return columns.map((c) => c.id);
  });

  const [zoomLevel, setZoomLevel] = React.useState<number>(() => {
    return Number(userPreferences.zoom_level) || 1.0;
  });

  // 3. Data Fetching
  const queryParams = React.useMemo(() => {
    const params: any = {
      page: pagination.pageIndex + 1,
      perPage: pagination.pageSize,
      sortColumn: sorting[0]?.id || '',
      sortDirection: sorting[0]?.desc ? 'desc' : 'asc',
      username: currentUser,
      ...filters,
    };

    // Convert arrays to JSON strings for PHP backend
    ['schritt', 'gesellschaft', 'fonds'].forEach((key) => {
      if (Array.isArray(params[key]) && params[key].length > 0) {
        params[key] = JSON.stringify(params[key]);
      } else {
        delete params[key];
      }
    });

    return params;
  }, [filters, pagination, sorting, currentUser]);

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['simplifyTableData', queryParams],
    queryFn: async () => {
      const searchParams = new URLSearchParams();
      Object.entries(queryParams).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== 'all' && value !== '') {
          searchParams.append(key, String(value));
        }
      });

      const response = await fetch(`dashboard/MyWidgets/SimplifyTable/query.php?${searchParams.toString()}`);
      if (!response.ok) throw new Error('Network response was not ok');
      return response.json();
    },
    placeholderData: (prev) => prev, // Keep old data while fetching
  });

  // 4. Mutations
  const savePreferences = useMutation({
    mutationFn: async (prefs: any) => {
      const formData = new FormData();
      Object.entries(prefs).forEach(([key, value]) => {
        formData.append(key, String(value));
      });

      const response = await fetch('dashboard/MyWidgets/SimplifyTable/savePreferences.php', {
        method: 'POST',
        body: formData,
      });
      return response.json();
    },
  });

  const handleSavePrefs = React.useCallback(() => {
    savePreferences.mutate({
      username: currentUser,
      filter: JSON.stringify(filters),
      column_order: JSON.stringify(columnOrder),
      sort_column: sorting[0]?.id || '',
      sort_direction: sorting[0]?.desc ? 'desc' : 'asc',
      current_page: pagination.pageIndex + 1,
      entries_per_page: pagination.pageSize,
      zoom_level: zoomLevel,
    });
  }, [currentUser, filters, columnOrder, sorting, pagination, zoomLevel]);

  // Sync prefs when important things change (debounced in real app, but let's keep it simple)
  React.useEffect(() => {
    if (!isLoading) {
      const timer = setTimeout(handleSavePrefs, 2000);
      return () => clearTimeout(timer);
    }
  }, [filters, sorting, pagination, columnOrder, zoomLevel]);

  return (
    <div className='relative flex flex-col gap-3 p-3 min-h-0 h-full bg-[#252730] text-slate-100 font-sans overflow-hidden'>
      <Button
        variant='ghost'
        size='icon'
        onClick={() => setShowZoom(!showZoom)}
        className='absolute top-3.7 right-3 z-60 h-8 w-8 p-0 text-slate-400 hover:text-[#ffcc0d] hover:bg-[#4a4d5c] rounded-full'
      >
        <Settings className='h-5 w-5' />
      </Button>

      {showZoom && <ZoomControls zoomLevel={zoomLevel} onZoomChange={setZoomLevel} onClose={() => setShowZoom(false)} />}

      <div
        className='flex flex-col gap-3 min-h-0 h-full origin-top-left transition-transform'
        style={{
          transform: `scale(${zoomLevel})`,
          width: `${100 / zoomLevel}%`,
          height: `${100 / zoomLevel}%`,
        }}
      >
        <FilterBar
          filters={filters}
          setFilters={setFilters}
          dropdownOptions={dropdownOptions}
          onApply={() => queryClient.invalidateQueries({ queryKey: ['simplifyTableData'] })}
          onReset={() => setFilters(initialFilters)}
        />

        <div className='flex-1 min-h-0 bg-[#3b3e4d] rounded-xl border border-[#4a4d5c] shadow-2xl overflow-hidden flex flex-col'>
          <DataTable
            columns={columns}
            data={data?.data || []}
            isLoading={isLoading || isFetching}
            sorting={sorting}
            setSorting={setSorting}
            columnOrder={columnOrder}
            setColumnOrder={setColumnOrder}
          />

          <Pagination totalItems={data?.total || 0} pagination={pagination} setPagination={setPagination} />
        </div>
      </div>
    </div>
  );
}
