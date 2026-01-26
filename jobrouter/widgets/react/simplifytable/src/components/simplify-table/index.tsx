import { SimplifyTableProvider, useSimplifyTable } from '@/lib/simplify-table-context';
import { FilterBar } from './filter-bar';
import { DataTable } from './data-table';
import { Pagination } from './pagination';
import { TableHeader } from './table-header';

function SimplifyTableContent() {
  const { state } = useSimplifyTable();

  return (
    <div
      className='p-4 h-full flex flex-col bg-background'
      style={{
        transform: `scale(${state.zoomLevel})`,
        transformOrigin: 'top left',
        width: `${100 / state.zoomLevel}%`,
        height: `${100 / state.zoomLevel}%`,
      }}
    >
      <TableHeader />
      <FilterBar />
      <div className='flex-1 min-h-0'>
        <DataTable />
      </div>
      <Pagination />
    </div>
  );
}

export function SimplifyTable() {
  return (
    <SimplifyTableProvider>
      <div className='w-full h-screen overflow-hidden'>
        <SimplifyTableContent />
      </div>
    </SimplifyTableProvider>
  );
}

export { FilterBar } from './filter-bar';
export { DataTable } from './data-table';
export { Pagination } from './pagination';
export { TableHeader } from './table-header';
export { SettingsModal } from './settings-modal';
