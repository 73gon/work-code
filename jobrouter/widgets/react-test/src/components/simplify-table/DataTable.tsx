import * as React from 'react';
import { type ColumnDef, flexRender, getCoreRowModel, useReactTable, type SortingState, getSortedRowModel } from '@tanstack/react-table';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip } from '@/components/ui/tooltip';
import { type JrColumn } from '@/lib/jr-utils';
import { cn } from '@/lib/utils';
import {
  ChevronUp,
  ChevronDown,
  ChevronsUpDown,
  History,
  FileText,
  FileCheck,
  CheckCircle2,
  AlertCircle,
  Clock,
  HelpCircle,
  GripVertical,
} from 'lucide-react';

interface DataTableProps {
  columns: JrColumn[];
  data: any[];
  isLoading: boolean;
  sorting: SortingState;
  setSorting: React.Dispatch<React.SetStateAction<SortingState>>;
  columnOrder: string[];
  setColumnOrder: React.Dispatch<React.SetStateAction<string[]>>;
}

export function DataTable({ columns: jrColumns, data, isLoading, sorting, setSorting, columnOrder, setColumnOrder }: DataTableProps) {
  const [dragState, setDragState] = React.useState<{
    dragging: boolean;
    draggedIndex: number | null;
    dragOverIndex: number | null;
    dropSide: 'left' | 'right' | null;
  }>({
    dragging: false,
    draggedIndex: null,
    dragOverIndex: null,
    dropSide: null,
  });

  // Get ordered columns
  const orderedJrColumns = React.useMemo(() => {
    if (columnOrder.length === 0) return jrColumns;
    return columnOrder.map((id) => jrColumns.find((col) => col.id === id)).filter((col): col is JrColumn => col !== undefined);
  }, [jrColumns, columnOrder]);

  const handleDragStart = (e: React.DragEvent, index: number) => {
    // Don't allow dragging the actions column
    if (index === 0) {
      e.preventDefault();
      return;
    }
    setDragState((prev) => ({ ...prev, dragging: true, draggedIndex: index }));
    e.dataTransfer.effectAllowed = 'move';
  };

  const handleDragOver = (e: React.DragEvent, index: number) => {
    if (!dragState.dragging || index === 0) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';

    const rect = (e.target as HTMLElement).closest('th')?.getBoundingClientRect();
    if (!rect) return;

    const midpoint = rect.left + rect.width / 2;
    const isLeftSide = e.clientX < midpoint;

    setDragState((prev) => ({
      ...prev,
      dragOverIndex: index,
      dropSide: isLeftSide ? 'left' : 'right',
    }));
  };

  const handleDragLeave = () => {
    setDragState((prev) => ({ ...prev, dragOverIndex: null, dropSide: null }));
  };

  const handleDragEnd = () => {
    setDragState({
      dragging: false,
      draggedIndex: null,
      dragOverIndex: null,
      dropSide: null,
    });
  };

  const handleDrop = (e: React.DragEvent, targetIndex: number) => {
    e.preventDefault();

    const { draggedIndex } = dragState;
    if (draggedIndex === null || draggedIndex === targetIndex || draggedIndex === 0 || targetIndex === 0) {
      handleDragEnd();
      return;
    }

    const newOrder = [...(columnOrder.length > 0 ? columnOrder : jrColumns.map((c) => c.id))];
    const [movedItem] = newOrder.splice(draggedIndex, 1);
    newOrder.splice(targetIndex, 0, movedItem);
    setColumnOrder(newOrder);
    handleDragEnd();
  };

  const columns = React.useMemo<ColumnDef<any>[]>(() => {
    return orderedJrColumns.map((col, colIndex) => ({
      id: col.id,
      accessorKey: col.id,
      header: ({ column }) => {
        if (col.id === 'actions') return <div className='text-center text-[11px] font-semibold'>Aktionen</div>;

        const isSorted = column.getIsSorted();

        return (
          <div
            className={cn(
              'flex items-center gap-1 cursor-pointer select-none group',
              col.align === 'center' && 'justify-center',
              col.align === 'right' && 'justify-end',
            )}
            onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
          >
            {colIndex > 0 && <GripVertical className='h-3 w-3 text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity cursor-grab' />}
            <span className='text-[11px] font-semibold'>{col.label}</span>
            <div className='flex flex-col items-center ml-1'>
              {isSorted === 'asc' ? (
                <ChevronUp className='h-4 w-4 text-[#ffcc0d]' />
              ) : isSorted === 'desc' ? (
                <ChevronDown className='h-4 w-4 text-[#ffcc0d]' />
              ) : (
                <ChevronsUpDown className='h-4 w-4 text-slate-500 opacity-50 group-hover:opacity-100 transition-opacity' />
              )}
            </div>
          </div>
        );
      },
      cell: ({ row }) => {
        const value = row.getValue(col.id) as string;
        const item = row.original;

        if (col.id === 'actions') {
          return (
            <div className='flex items-center justify-center gap-2'>
              {item.historyLink && (
                <Tooltip content='Vorgangshistorie anzeigen'>
                  <button
                    onClick={() => window.open(item.historyLink, '_blank', 'width=1000,height=700')}
                    className='text-[#ffcc0d] hover:text-[#e6b800] transition-colors p-1 rounded hover:bg-[#4a4d5c]'
                  >
                    <History className='h-4 w-4' />
                  </button>
                </Tooltip>
              )}
              {item.invoice && (
                <Tooltip content={`Rechnung öffnen: ${item.invoice}`}>
                  <a
                    href={`https://jobrouter.empira-invest.com/jobrouter/FIBU_URL.php?dokument=${item.invoice}`}
                    target='_blank'
                    className='text-[#ffcc0d] hover:text-[#e6b800] transition-colors p-1 rounded hover:bg-[#4a4d5c]'
                  >
                    <FileText className='h-4 w-4' />
                  </a>
                </Tooltip>
              )}
              {item.protocol && (
                <Tooltip content={`Protokoll öffnen: ${item.protocol}`}>
                  <a
                    href={`https://jobrouter.empira-invest.com/jobrouter/PROTOCOL_URL.php?dokument=${item.protocol}`}
                    target='_blank'
                    className='text-blue-400 hover:text-blue-300 transition-colors p-1 rounded hover:bg-[#4a4d5c]'
                  >
                    <FileCheck className='h-4 w-4' />
                  </a>
                </Tooltip>
              )}
            </div>
          );
        }

        if (col.type === 'status') {
          const statusValue = (value || '').toLowerCase();
          const runtime = item.runtime || '';

          if (statusValue === 'beendet' || statusValue === 'completed') {
            return (
              <Tooltip content={`Beendet${runtime ? ` - Laufzeit: ${runtime}` : ''}`}>
                <span className='flex justify-center'>
                  <CheckCircle2 className='h-5 w-5 text-emerald-500' />
                </span>
              </Tooltip>
            );
          }
          if (statusValue === 'fällig' || statusValue === 'faellig' || statusValue === 'due') {
            return (
              <Tooltip content='Fällig'>
                <span className='flex justify-center'>
                  <AlertCircle className='h-5 w-5 text-red-500' />
                </span>
              </Tooltip>
            );
          }
          if (statusValue === 'nicht fällig' || statusValue === 'nicht faellig' || statusValue === 'not_due') {
            return (
              <Tooltip content={`Nicht fällig${runtime ? ` - Laufzeit: ${runtime}` : ''}`}>
                <span className='flex justify-center'>
                  <Clock className='h-5 w-5 text-amber-500' />
                </span>
              </Tooltip>
            );
          }
          return (
            <Tooltip content={value || 'Unbekannt'}>
              <span className='flex justify-center'>
                <HelpCircle className='h-5 w-5 text-slate-400' />
              </span>
            </Tooltip>
          );
        }

        if (col.type === 'currency') {
          const num = parseFloat(value);
          if (isNaN(num)) return <span className='text-[11px]'>-</span>;
          return (
            <div className={cn('font-semibold text-[11px]', num < 0 ? 'text-red-400' : 'text-emerald-400')}>
              € {num.toLocaleString('de-DE', { minimumFractionDigits: 2 })}
            </div>
          );
        }

        if (col.type === 'date' && value) {
          return <span className='text-[11px]'>{new Date(value).toLocaleDateString('de-DE')}</span>;
        }

        return (
          <div className={cn('text-[11px]', col.align === 'center' && 'text-center', col.align === 'right' && 'text-right')}>{value || '-'}</div>
        );
      },
    }));
  }, [orderedJrColumns]);

  const table = useReactTable({
    data,
    columns,
    state: {
      sorting,
      columnOrder,
    },
    onSortingChange: setSorting,
    onColumnOrderChange: setColumnOrder,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    manualSorting: true,
  });

  return (
    <div className='flex-1 overflow-auto custom-scrollbar relative min-h-0'>
      <Table className='w-full'>
        <TableHeader className='bg-[#252730] sticky top-0 z-10'>
          {table.getHeaderGroups().map((headerGroup) => (
            <TableRow key={headerGroup.id} className='hover:bg-transparent border-b-2 border-[#4a4d5c]'>
              {headerGroup.headers.map((header, headerIndex) => (
                <TableHead
                  key={header.id}
                  draggable={headerIndex > 0}
                  onDragStart={(e) => handleDragStart(e, headerIndex)}
                  onDragOver={(e) => handleDragOver(e, headerIndex)}
                  onDragLeave={handleDragLeave}
                  onDragEnd={handleDragEnd}
                  onDrop={(e) => handleDrop(e, headerIndex)}
                  className={cn(
                    'text-slate-400 font-bold uppercase tracking-wider h-10 px-3 border-x border-[#4a4d5c]/50 first:border-l-0 last:border-r-0 whitespace-nowrap',
                    headerIndex > 0 && 'cursor-grab active:cursor-grabbing',
                    dragState.dragging && dragState.draggedIndex === headerIndex && 'opacity-50',
                    dragState.dragOverIndex === headerIndex && dragState.dropSide === 'left' && 'border-l-2 border-l-[#ffcc0d]',
                    dragState.dragOverIndex === headerIndex && dragState.dropSide === 'right' && 'border-r-2 border-r-[#ffcc0d]',
                  )}
                >
                  {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                </TableHead>
              ))}
            </TableRow>
          ))}
        </TableHeader>
        <TableBody>
          {isLoading ? (
            Array.from({ length: 10 }).map((_, i) => (
              <TableRow key={i} className='animate-pulse border-[#4a4d5c]'>
                {columns.map((_, j) => (
                  <TableCell key={j} className='h-10 px-3 border-x border-[#4a4d5c]/50 first:border-l-0 last:border-r-0'>
                    <div className='h-3 bg-slate-700/50 rounded w-full' />
                  </TableCell>
                ))}
              </TableRow>
            ))
          ) : table.getRowModel().rows?.length ? (
            table.getRowModel().rows.map((row) => {
              const status = (row.original.status || '').toLowerCase();
              const isDue = status === 'fällig' || status === 'faellig' || status === 'due';
              const isNotDue = status === 'nicht fällig' || status === 'nicht faellig' || status === 'not_due';

              return (
                <TableRow
                  key={row.id}
                  className={cn(
                    'border-[#4a4d5c] hover:bg-[#4a4d5c] transition-colors whitespace-nowrap',
                    isDue && 'bg-red-500/10 hover:bg-red-500/20',
                    isNotDue && 'bg-amber-500/10 hover:bg-amber-500/20',
                  )}
                >
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id} className='px-3 py-2 text-[11px] border-x border-[#4a4d5c]/50 first:border-l-0 last:border-r-0'>
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              );
            })
          ) : (
            <TableRow>
              <TableCell colSpan={columns.length} className='h-100 text-center text-slate-400'>
                <div className='flex flex-col items-center gap-4'>
                  <FileText className='h-12 w-12 opacity-20' />
                  <p className='text-sm'>Es wurden keine Einträge gefunden</p>
                </div>
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
    </div>
  );
}
