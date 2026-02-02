import { useSimplifyTable } from '@/lib/simplify-table-context';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowLeft01Icon, ArrowRight01Icon } from '@hugeicons/core-free-icons';
import { useMemo } from 'react';

export function Pagination() {
  const { state, setPage, setItemsPerPage } = useSimplifyTable();

  const totalPages = useMemo(() => Math.max(1, Math.ceil(state.totalItems / state.itemsPerPage)), [state.totalItems, state.itemsPerPage]);

  const start = state.totalItems === 0 ? 0 : (state.currentPage - 1) * state.itemsPerPage + 1;

  const end = useMemo(() => {
    if (state.totalItems === 0) return 0;
    const pageEnd = state.currentPage * state.itemsPerPage;
    return Math.min(pageEnd, state.totalItems);
  }, [state.currentPage, state.itemsPerPage, state.totalItems]);

  const handlePrevPage = () => {
    if (state.currentPage > 1) {
      setPage(state.currentPage - 1);
    }
  };

  const handleNextPage = () => {
    if (state.currentPage < totalPages) {
      setPage(state.currentPage + 1);
    }
  };

  return (
    <div className='flex flex-wrap items-center justify-between gap-4 py-4 shrink-0'>
      {/* Items per page */}
      <div className='flex items-center gap-2'>
        <span className='text-sm text-muted-foreground'>Einträge pro Seite:</span>
        <Select value={String(state.itemsPerPage)} onValueChange={(val) => setItemsPerPage(Number(val))}>
          <SelectTrigger className='w-20'>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='10'>10</SelectItem>
            <SelectItem value='25'>25</SelectItem>
            <SelectItem value='50'>50</SelectItem>
            <SelectItem value='100'>100</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Page info */}
      <div className='text-sm text-muted-foreground'>
        {start}-{end} von {state.totalItems} Einträgen
      </div>

      {/* Pagination buttons */}
      <div className='flex items-center gap-2'>
        <Button variant='outline' size='icon' onClick={handlePrevPage} disabled={state.currentPage === 1}>
          <HugeiconsIcon icon={ArrowLeft01Icon} size={18} />
        </Button>

        <span className='text-sm px-2'>
          Seite {state.currentPage} von {totalPages}
        </span>

        <Button variant='outline' size='icon' onClick={handleNextPage} disabled={state.currentPage >= totalPages}>
          <HugeiconsIcon icon={ArrowRight01Icon} size={18} />
        </Button>
      </div>
    </div>
  );
}
